<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use App\Services\PremiumCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyFlowTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ], $overrides);
    }

    public function test_premium_matches_assignment_example(): void
    {
        $result = app(PremiumCalculator::class)->calculate($this->payload());
        $this->assertSame(5, $result['travel_days']);
        $this->assertSame('225.00', $result['premium']);
    }

    public function test_invalid_dates_and_unsupported_plan_are_rejected(): void
    {
        $this->postJson('/api/policy-requests', $this->payload([
            'trip_start' => '2026-11-15', 'trip_end' => '2026-11-14',
        ]))->assertUnprocessable();

        $this->postJson('/api/policy-requests', $this->payload([
            'plan_code' => 'GOLD',
        ]))->assertUnprocessable();
    }

    public function test_duplicate_intake_returns_same_request(): void
    {
        $first = $this->postJson('/api/policy-requests', $this->payload())->assertCreated();
        $second = $this->postJson('/api/policy-requests', $this->payload())->assertOk();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('policy_requests', 1);
    }

    public function test_successful_issuance_creates_one_policy_number(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->payload())->json('data.id');
        $this->postJson("/api/policy-requests/{$id}/submit", ['scenario' => 'SUCCESS'])
            ->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->postJson("/api/policy-requests/{$id}/submit", ['scenario' => 'SUCCESS'])
            ->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->assertDatabaseCount('policy_requests', 1);
        $this->assertSame(1, PolicyRequest::findOrFail($id)->attempt_count);
        $this->assertNotEmpty(PolicyRequest::findOrFail($id)->policy_number);
    }

    public function test_timeout_after_issue_is_reconciled_without_second_submission(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->payload())->json('data.id');
        $this->postJson("/api/policy-requests/{$id}/submit", ['scenario' => 'TIMEOUT_AFTER_ISSUE'])
            ->assertOk()->assertJsonPath('data.status', 'NEEDS_RECONCILIATION');
        $this->postJson("/api/policy-requests/{$id}/submit", ['scenario' => 'SUCCESS'])
            ->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $policy = PolicyRequest::findOrFail($id);
        $this->assertSame(1, $policy->attempt_count);
        $this->assertNotEmpty($policy->policy_number);
    }

    public function test_duplicate_callback_is_processed_once_and_cannot_regress_issued_state(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->payload())->json('data.id');
        $this->postJson("/api/policy-requests/{$id}/submit", ['scenario' => 'DUPLICATE_CALLBACK'])
            ->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $policy = PolicyRequest::findOrFail($id);
        $this->assertSame('ISSUED', $policy->status);
        $this->assertDatabaseCount('provider_events', 1);
    }
}

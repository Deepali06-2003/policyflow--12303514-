<?php

namespace App\Services;

use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Models\ProviderEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PolicyWorkflow
{
    public function __construct(private readonly MockInsurer $insurer) {}

    public function transition(PolicyRequest $request, string $to, string $reason, array $metadata = []): void
    {
        $from = $request->status;
        $allowed = [
            'RECEIVED' => ['VALIDATED', 'FAILED'],
            'VALIDATED' => ['SUBMITTED', 'FAILED'],
            'SUBMITTED' => ['PENDING_CONFIRMATION', 'ISSUED', 'FAILED', 'NEEDS_RECONCILIATION'],
            'PENDING_CONFIRMATION' => ['ISSUED', 'FAILED', 'NEEDS_RECONCILIATION'],
            'NEEDS_RECONCILIATION' => ['ISSUED', 'FAILED', 'PENDING_CONFIRMATION'],
            'FAILED' => ['SUBMITTED'],
            'ISSUED' => [],
        ];
        if ($from === $to) return;
        if (!in_array($to, $allowed[$from] ?? [], true)) {
            throw new \DomainException("Invalid policy state transition: {$from} -> {$to}");
        }
        $request->status = $to;
        $request->save();
        PolicyStateHistory::create([
            'policy_request_id' => $request->id, 'from_status' => $from,
            'to_status' => $to, 'reason' => $reason, 'metadata' => $metadata,
        ]);
    }

    public function submit(PolicyRequest $request, string $scenario): PolicyRequest
    {
        return DB::transaction(function () use ($request, $scenario) {
            $request = PolicyRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            // Never submit a request already confirmed as issued.
            if ($request->status === 'ISSUED') return $request;

            // Timeout recovery: inspect provider-side record before making another call.
            if ($request->status === 'NEEDS_RECONCILIATION' || $request->status === 'PENDING_CONFIRMATION') {
                $providerRecord = $this->insurer->findForRequest($request->id);
                if ($providerRecord && isset($providerRecord['policy_number'])) {
                    $this->markIssued($request, $providerRecord, 'Reconciled provider status');
                    return $request->fresh();
                }
                if ($request->status === 'PENDING_CONFIRMATION') return $request;
            }

            if ($request->status === 'RECEIVED') {
                $this->transition($request, 'VALIDATED', 'Request passed intake validation');
            }
            if (in_array($request->status, ['VALIDATED', 'FAILED'], true)) {
                $this->transition($request, 'SUBMITTED', 'Issuance submission started');
            }

            $request->attempt_count++;
            $request->save();
            $attempt = IssuanceAttempt::create([
                'policy_request_id' => $request->id,
                'attempt_number' => $request->attempt_count,
                'scenario' => $scenario,
                'request_payload' => ['premium' => $request->premium, 'plan_code' => $request->plan_code],
                'outcome' => 'STARTED',
            ]);

            $response = $this->insurer->submit($request, $scenario);
            $request->last_provider_response = $response;
            $request->provider_reference = $response['provider_reference'] ?? $request->provider_reference;
            $attempt->response_payload = $response;
            $attempt->outcome = $response['outcome'];
            $attempt->save();
            $request->save();

            if ($response['outcome'] === 'SUCCESS') {
                $this->markIssued($request, $response, 'Mock insurer confirmed issuance');
            } elseif ($response['outcome'] === 'TIMEOUT') {
                $this->transition($request, 'NEEDS_RECONCILIATION', 'Timeout; provider may have issued policy', $response);
            } elseif ($response['outcome'] === 'PENDING') {
                $this->transition($request, 'PENDING_CONFIRMATION', 'Awaiting provider callback', $response);
                if (($response['callback_scenario'] ?? '') === 'DUPLICATE_CALLBACK') {
                    $this->handleCallback([
                        'provider_event_id' => 'EVT-' . $request->id . '-DUPLICATE',
                        'provider_reference' => $request->provider_reference,
                        'event_type' => 'ISSUED',
                        'policy_number' => $response['policy_number'],
                    ]);
                    $this->handleCallback([
                        'provider_event_id' => 'EVT-' . $request->id . '-DUPLICATE',
                        'provider_reference' => $request->provider_reference,
                        'event_type' => 'ISSUED',
                        'policy_number' => $response['policy_number'],
                    ]);
                }
            } else {
                $this->transition($request, 'FAILED', 'Temporary provider failure', $response);
            }
            return $request->fresh();
        });
    }

    public function handleCallback(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            $request = PolicyRequest::where('provider_reference', $data['provider_reference'])
                ->lockForUpdate()->first();
            if (!$request) return false;

            $event = ProviderEvent::firstOrCreate(
                ['provider_event_id' => $data['provider_event_id']],
                [
                    'policy_request_id' => $request->id,
                    'event_type' => $data['event_type'],
                    'payload' => $data,
                    'processed_at' => now(),
                ]
            );
            if (!$event->wasRecentlyCreated) return true;
            if ($request->status === 'ISSUED') return true;

            if ($data['event_type'] === 'ISSUED' && !empty($data['policy_number'])) {
                $this->markIssued($request, $data, 'Provider callback confirmed issuance');
            } elseif ($data['event_type'] === 'FAILED') {
                $this->transition($request, 'FAILED', 'Provider callback reported failure', $data);
            }
            return true;
        });
    }

    private function markIssued(PolicyRequest $request, array $response, string $reason): void
    {
        if ($request->status === 'ISSUED') return;
        $request->policy_number = $response['policy_number'];
        $request->provider_reference = $response['provider_reference'] ?? $request->provider_reference;
        $request->save();
        $this->transition($request, 'ISSUED', $reason, $response);
    }
}

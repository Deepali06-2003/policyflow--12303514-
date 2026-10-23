<?php

namespace App\Services;

use App\Models\PolicyRequest;
use Illuminate\Support\Str;

class MockInsurer
{
    /**
     * Scenarios: SUCCESS, TEMPORARY_FAILURE, TIMEOUT_AFTER_ISSUE,
     * DELAYED_CALLBACK, DUPLICATE_CALLBACK.
     * This is a local deterministic mock, never a real insurer.
     */
    public function submit(PolicyRequest $request, string $scenario): array
    {
        $existing = cache()->get($this->cacheKey($request->id));
        if ($existing && isset($existing['policy_number'])) {
            return $existing;
        }

        if ($scenario === 'TEMPORARY_FAILURE') {
            return ['outcome' => 'TEMPORARY_FAILURE', 'message' => 'Simulated temporary provider failure'];
        }

        $providerReference = $existing['provider_reference'] ?? 'MOCK-' . Str::uuid();
        $result = [
            'outcome' => 'SUCCESS',
            'provider_reference' => $providerReference,
            'policy_number' => 'POL-' . strtoupper(Str::random(10)),
        ];
        cache()->forever($this->cacheKey($request->id), $result);
        cache()->forever('mock_insurer_provider_' . $providerReference, $result);

        if ($scenario === 'TIMEOUT_AFTER_ISSUE') {
            // Provider has created the policy, but the submitter receives a timeout.
            return ['outcome' => 'TIMEOUT', 'provider_reference' => $providerReference,
                'message' => 'Simulated timeout after provider-side issuance'];
        }

        if ($scenario === 'DELAYED_CALLBACK' || $scenario === 'DUPLICATE_CALLBACK') {
            return ['outcome' => 'PENDING', 'provider_reference' => $providerReference,
                'policy_number' => $result['policy_number'], 'callback_scenario' => $scenario];
        }

        return $result;
    }

    public function status(string $providerReference): ?array
    {
        return cache()->get('mock_insurer_provider_' . $providerReference);
    }

    public function findForRequest(int $requestId): ?array
    {
        return cache()->get($this->cacheKey($requestId));
    }

    private function cacheKey(int $id): string
    {
        return 'mock_insurer_request_' . $id;
    }
}

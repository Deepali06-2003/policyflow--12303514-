<?php

namespace App\Http\Controllers;

use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Services\PolicyWorkflow;
use App\Services\PremiumCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PolicyRequestController extends Controller
{
    public function store(Request $request, PremiumCalculator $calculator)
    {
        $data = $request->validate([
            'client_request_id' => ['required', 'string', 'max:100'],
            'agent_id' => ['required', 'string', 'max:100'],
            'customer_name' => ['required', 'string', 'max:200'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'destination' => ['required', Rule::in(['DOMESTIC', 'INTERNATIONAL'])],
            'trip_start' => ['required', 'date'],
            'trip_end' => ['required', 'date', 'after_or_equal:trip_start'],
            'plan_code' => ['required', Rule::in(['BASIC', 'PLUS', 'PREMIUM'])],
            'sum_insured' => ['required', 'integer', Rule::in([100000, 300000, 500000])],
        ]);

        $calculation = $calculator->calculate($data);

        try {
            $policy = DB::transaction(function () use ($data, $calculation) {
                $existing = PolicyRequest::where('agent_id', $data['agent_id'])
                    ->where('client_request_id', $data['client_request_id'])->first();
                if ($existing) return [$existing, false];

                $policy = PolicyRequest::create($data + [
                    'travel_days' => $calculation['travel_days'],
                    'premium' => $calculation['premium'],
                    'status' => 'RECEIVED',
                    'attempt_count' => 0,
                ]);
                PolicyStateHistory::create([
                    'policy_request_id' => $policy->id, 'from_status' => null,
                    'to_status' => 'RECEIVED', 'reason' => 'Request accepted',
                ]);
                return [$policy, true];
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Handles a race where two concurrent requests pass the pre-check.
            $existing = PolicyRequest::where('agent_id', $data['agent_id'])
                ->where('client_request_id', $data['client_request_id'])->first();
            if (!$existing) throw $e;
            return response()->json(['data' => $existing, 'duplicate' => true], 200);
        }

        [$policy, $created] = $policy;
        return response()->json(['data' => $policy, 'duplicate' => !$created], $created ? 201 : 200);
    }

    public function index(Request $request)
    {
        $query = PolicyRequest::query()->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        return response()->json($query->paginate(min(max((int) $request->query('per_page', 15), 1), 100)));
    }

    public function show(PolicyRequest $policyRequest)
    {
        return response()->json([
            'data' => $policyRequest,
            'attempts' => $policyRequest->attempts,
            'state_history' => $policyRequest->stateHistory,
        ]);
    }

    public function submit(Request $request, PolicyRequest $policyRequest, PolicyWorkflow $workflow)
    {
        $data = $request->validate([
            'scenario' => ['nullable', Rule::in([
                'SUCCESS', 'TEMPORARY_FAILURE', 'TIMEOUT_AFTER_ISSUE',
                'DELAYED_CALLBACK', 'DUPLICATE_CALLBACK',
            ])],
        ]);
        $policy = $workflow->submit($policyRequest, $data['scenario'] ?? 'SUCCESS');
        return response()->json(['data' => $policy, 'state_history' => $policy->stateHistory]);
    }
}

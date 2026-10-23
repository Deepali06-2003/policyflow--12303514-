<?php

namespace App\Http\Controllers;

use App\Models\PolicyRequest;
use App\Services\MockInsurer;
use App\Services\PolicyWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MockInsurerController extends Controller
{
    public function callback(Request $request, PolicyWorkflow $workflow)
    {
        $data = $request->validate([
            'provider_event_id' => ['required', 'string', 'max:150'],
            'provider_reference' => ['required', 'string', 'max:150'],
            'event_type' => ['required', Rule::in(['ISSUED', 'FAILED'])],
            'policy_number' => ['required_if:event_type,ISSUED', 'nullable', 'string', 'max:150'],
        ]);
        $found = $workflow->handleCallback($data);
        return response()->json(['accepted' => $found], $found ? 200 : 404);
    }

    public function status(string $providerReference, MockInsurer $insurer)
    {
        $record = $insurer->status($providerReference);
        return $record
            ? response()->json(['data' => $record])
            : response()->json(['message' => 'Provider reference not found'], 404);
    }
}

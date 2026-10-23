<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyRequest extends Model
{
    protected $fillable = [
        'client_request_id', 'agent_id', 'customer_name', 'date_of_birth',
        'destination', 'trip_start', 'trip_end', 'plan_code', 'sum_insured',
        'travel_days', 'premium', 'status', 'provider_reference',
        'last_provider_response', 'attempt_count', 'policy_number',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'trip_start' => 'date:Y-m-d',
        'trip_end' => 'date:Y-m-d',
        'premium' => 'decimal:2',
        'last_provider_response' => 'array',
        'attempt_count' => 'integer',
    ];

    public function attempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class);
    }

    public function stateHistory(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class)->latest();
    }

    public function providerEvents(): HasMany
    {
        return $this->hasMany(ProviderEvent::class);
    }
}

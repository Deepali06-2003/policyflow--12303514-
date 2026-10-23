<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssuanceAttempt extends Model
{
    protected $fillable = [
        'policy_request_id', 'attempt_number', 'scenario', 'request_payload',
        'response_payload', 'outcome',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];
}

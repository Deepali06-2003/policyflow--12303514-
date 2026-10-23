<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderEvent extends Model
{
    protected $fillable = [
        'policy_request_id', 'provider_event_id', 'event_type', 'payload', 'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PolicyStateHistory extends Model
{
    protected $fillable = ['policy_request_id', 'from_status', 'to_status', 'reason', 'metadata'];

    protected $casts = ['metadata' => 'array'];
}

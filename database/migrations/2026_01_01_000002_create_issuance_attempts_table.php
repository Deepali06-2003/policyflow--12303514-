<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('issuance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('scenario', 40);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('outcome', 40);
            $table->timestamps();
            $table->unique(['policy_request_id', 'attempt_number']);
        });
    }
    public function down(): void { Schema::dropIfExists('issuance_attempts'); }
};

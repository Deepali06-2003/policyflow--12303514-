<?php

use App\Http\Controllers\MockInsurerController;
use App\Http\Controllers\PolicyRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/policy-requests', [PolicyRequestController::class, 'store']);
Route::get('/policy-requests', [PolicyRequestController::class, 'index']);
Route::get('/policy-requests/{policyRequest}', [PolicyRequestController::class, 'show']);
Route::post('/policy-requests/{policyRequest}/submit', [PolicyRequestController::class, 'submit']);
Route::post('/mock-insurer/callback', [MockInsurerController::class, 'callback']);
Route::get('/mock-insurer/policies/{providerReference}', [MockInsurerController::class, 'status']);

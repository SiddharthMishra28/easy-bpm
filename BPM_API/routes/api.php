<?php

use App\Http\Controllers\Api\FlowController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    // Flow Management
    Route::post('/flows', [FlowController::class, 'store']);
    Route::get('/flows/{id}', [FlowController::class, 'show']);
    Route::put('/flows/{id}', [FlowController::class, 'update']);
    Route::delete('/flows/{id}', [FlowController::class, 'destroy']);
    Route::get('/flows/{id}/versions', [FlowController::class, 'versions']);

    // ... Ticket routes to follow
});

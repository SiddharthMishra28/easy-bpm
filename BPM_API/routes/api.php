<?php

use App\Http\Controllers\Api\FlowController;
use App\Http\Controllers\Api\TicketController;
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

    // Ticket Management
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::put('/tickets/{ticket}/data', [TicketController::class, 'updateData']);
    Route::put('/tickets/{ticket}/tasks/{taskId}', [TicketController::class, 'updateTaskStatus']);

    // Core Advancement Endpoint (to be implemented in Step 11)
    Route::post('/tickets/{ticket}/advance', [TicketController::class, 'advanceTicket']);
});

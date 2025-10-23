<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Models\Ticket;
use App\Models\TicketTaskStatus;
use App\Models\TicketHistory; // For logging updates
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    /**
     * POST /tickets - Create a new Ticket instance.
     */
    public function store(Request $request)
    {
        $request->validate([
            'flow_id' => 'required|exists:flows,id',
            'ticket_data' => 'nullable|array',
            // 'created_by_user_id' can be taken from auth if user is logged in
        ]);

        $flow = Flow::with('latestVersion.stages')->findOrFail($request->flow_id);
        $latestVersion = $flow->latestVersion;

        // 1. Find the starting stage ID
        $startStage = $latestVersion->stages->firstWhere('is_start_stage', true);

        if (!$startStage) {
            return response()->json(['message' => 'Flow version has no defined starting stage.'], 400);
        }

        // 2. Create the ticket linked to the immutable version
        $ticket = Ticket::create([
            'flow_id' => $flow->id,
            'flow_version_id' => $latestVersion->id,
            'current_stage_id' => $startStage->id,
            'ticket_data' => $request->ticket_data ?? [],
            // 'created_by_user_id' => auth()->id(), // Use authenticated user ID
        ]);

        // 3. Log the initial history event
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'event_type' => 'STAGE_ENTERED',
            'details' => json_encode(['stage_id' => $startStage->id, 'stage_name' => $startStage->name]),
            // 'actor_user_id' => auth()->id(),
        ]);

        return response()->json($ticket, 201);
    }

    /**
     * GET /tickets/{id} - Retrieve a Ticket and its current flow context.
     */
    public function show(Ticket $ticket)
    {
        // Eager load the immutable version and current stage for context
        $ticket->load(['flowVersion', 'currentStage']);
        return response()->json($ticket, 200);
    }

    /**
     * PUT /tickets/{id}/data - Update the flexible JSON payload.
     */
    public function updateData(Request $request, Ticket $ticket)
    {
        $request->validate(['data' => 'required|array']);

        $ticket->ticket_data = array_merge($ticket->ticket_data, $request->data);
        $ticket->save();

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'event_type' => 'DATA_UPDATE',
            'details' => json_encode(['updated_keys' => array_keys($request->data)]),
            // 'actor_user_id' => auth()->id(),
        ]);

        return response()->json($ticket, 200);
    }

    /**
     * PUT /tickets/{id}/tasks/{task_id} - Mark a specific task as complete/incomplete.
     */
    public function updateTaskStatus(Request $request, Ticket $ticket, $taskId)
    {
        $request->validate(['is_complete' => 'required|boolean']);

        $taskStatus = TicketTaskStatus::updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'stage_task_id' => $taskId, // Ensure this task ID belongs to the ticket's current flow version
            ],
            [
                'is_complete' => $request->is_complete,
                'completed_at' => $request->is_complete ? now() : null,
                // 'completed_by_user_id' => auth()->id(),
            ]
        );

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'event_type' => 'TASK_COMPLETE',
            'details' => json_encode(['task_id' => $taskId, 'status' => $request->is_complete ? 'Complete' : 'Incomplete']),
            // 'actor_user_id' => auth()->id(),
        ]);

        return response()->json($taskStatus, 200);
    }
}

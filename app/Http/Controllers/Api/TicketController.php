<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Models\Ticket;
use App\Models\TicketTaskStatus;
use App\Models\TicketHistory;
use App\Models\StageTask;
use App\Services\RuleEngineService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    protected $ruleEngineService;

    public function __construct(RuleEngineService $ruleEngineService)
    {
        $this->ruleEngineService = $ruleEngineService;
    }

    /**
     * @OA\Post(
     *     path="/tickets",
     *     summary="Create a new ticket instance.",
     *     tags={"Tickets"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"flow_id"},
     *             @OA\Property(property="flow_id", type="integer", example=1),
     *             @OA\Property(property="ticket_data", type="object", example={"priority": "High"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Ticket created successfully."),
     *     @OA\Response(response=400, description="Bad request, e.g., flow has no start stage.")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'flow_id' => 'required|exists:flows,id',
            'ticket_data' => 'nullable|array',
        ]);

        $flow = Flow::with('latestVersion.stages')->findOrFail($request->flow_id);
        $latestVersion = $flow->latestVersion;

        $startStage = $latestVersion->stages->firstWhere('is_start_stage', true);

        if (!$startStage) {
            return response()->json(['message' => 'Flow version has no defined starting stage.'], 400);
        }

        $ticket = Ticket::create([
            'flow_id' => $flow->id,
            'flow_version_id' => $latestVersion->id,
            'current_stage_id' => $startStage->id,
            'ticket_data' => $request->ticket_data ?? [],
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'event_type' => 'STAGE_ENTERED',
            'details' => json_encode(['stage_id' => $startStage->id, 'stage_name' => $startStage->name]),
        ]);

        return response()->json($ticket, 201);
    }

    /**
     * @OA\Get(
     *     path="/tickets/{ticket}",
     *     summary="Retrieve a ticket and its current flow context.",
     *     tags={"Tickets"},
     *     @OA\Parameter(name="ticket", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation.")
     * )
     */
    public function show(Ticket $ticket)
    {
        $ticket->load(['flowVersion', 'currentStage']);
        return response()->json($ticket, 200);
    }

    /**
     * @OA\Put(
     *     path="/tickets/{ticket}/data",
     *     summary="Update the flexible JSON payload of a ticket.",
     *     tags={"Tickets"},
     *     @OA\Parameter(name="ticket", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"data"},
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Ticket data updated successfully.")
     * )
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
        ]);

        return response()->json($ticket, 200);
    }

    /**
     * @OA\Put(
     *     path="/tickets/{ticket}/tasks/{taskId}",
     *     summary="Mark a specific task as complete/incomplete.",
     *     tags={"Tickets"},
     *     @OA\Parameter(name="ticket", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="taskId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_complete"},
     *             @OA\Property(property="is_complete", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Task status updated successfully.")
     * )
     */
    public function updateTaskStatus(Request $request, Ticket $ticket, $taskId)
    {
        $request->validate(['is_complete' => 'required|boolean']);

        $taskStatus = TicketTaskStatus::updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'stage_task_id' => $taskId,
            ],
            [
                'is_complete' => $request->is_complete,
                'completed_at' => $request->is_complete ? now() : null,
            ]
        );

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'event_type' => 'TASK_COMPLETE',
            'details' => json_encode(['task_id' => $taskId, 'status' => $request->is_complete ? 'Complete' : 'Incomplete']),
        ]);

        return response()->json($taskStatus, 200);
    }

    /**
     * @OA\Post(
     *     path="/tickets/{ticket}/advance",
     *     summary="Advance a ticket to the next stage using the Flow's rule engine.",
     *     tags={"Tickets"},
     *     @OA\Parameter(name="ticket", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Ticket successfully advanced to the next stage/status.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Ticket successfully advanced."),
     *             @OA\Property(property="current_stage_id", type="integer", example=2),
     *             @OA\Property(property="status", type="string", example="IN_PROGRESS"),
     *             @OA\Property(
     *                 property="summary",
     *                 type="object",
     *                 @OA\Property(property="rule_passed", type="string", example="High Priority Routing")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Advancement blocked due to missing mandatory tasks/checkpoints.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Advancement failed. Mandatory checkpoints are incomplete."),
     *             @OA\Property(
     *                 property="validation_errors",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="task_id", type="integer", example=1),
     *                     @OA\Property(property="task_name", type="string", example="Verify User Identity"),
     *                     @OA\Property(property="stage_id", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Advancement blocked because no routing rules qualified for the ticket data.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Advancement failed. No routing rules qualified for the ticket data."),
     *             @OA\Property(
     *                 property="routing_failures",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="rule", type="string", example="High Priority Routing"),
     *                     @OA\Property(
     *                         property="errors",
     *                         type="array",
     *                         @OA\Items(type="string", example="Group 'Priority Check' failed: Condition priority_level == 'High' was not met.")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function advanceTicket(Ticket $ticket): JsonResponse
    {
        if ($ticket->status !== 'IN_PROGRESS') {
             return response()->json(['message' => 'Ticket is not in an IN_PROGRESS status and cannot be advanced.'], 409);
        }

        $currentStage = $ticket->currentStage()->first();
        if (!$currentStage) {
            return response()->json(['message' => 'Ticket is not assigned to a valid stage.'], 500);
        }

        $mandatoryTasks = StageTask::where('flow_stage_id', $currentStage->id)
                                   ->where('is_mandatory', true)
                                   ->get();

        $completedTaskIds = $ticket->taskStatuses()
                                   ->where('is_complete', true)
                                   ->pluck('stage_task_id')
                                   ->toArray();

        $missingCheckpoints = [];
        foreach ($mandatoryTasks as $task) {
            if (!in_array($task->id, $completedTaskIds)) {
                $missingCheckpoints[] = [
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'stage_id' => $currentStage->id
                ];
            }
        }

        if (!empty($missingCheckpoints)) {
            return response()->json([
                'message' => 'Advancement failed. Mandatory checkpoints are incomplete.',
                'validation_errors' => $missingCheckpoints
            ], 400);
        }

        return $this->processRuleEvaluation($ticket, $currentStage, $completedTaskIds);
    }

    protected function processRuleEvaluation(Ticket $ticket, $currentStage, array $completedTaskIds): JsonResponse
    {
        $stageRules = $currentStage->rules()->get();
        $successfulRouting = false;
        $executionSummary = [];

        foreach ($stageRules as $rule) {
            $ruleDefinition = json_decode($rule->rule_definition_json, true);

            $evaluationResult = $this->ruleEngineService->evaluate(
                $ruleDefinition,
                $ticket->ticket_data,
                $completedTaskIds
            );

            if ($evaluationResult['success']) {
                $executionSummary['rule_passed'] = $ruleDefinition['rule_name'] ?? 'Unnamed Rule';
                $this->executeActions($ticket, $evaluationResult['actions']);
                $successfulRouting = true;
                break;
            }

            $executionSummary['rule_failures'][] = [
                'rule' => $ruleDefinition['rule_name'] ?? 'Unnamed Rule',
                'errors' => $evaluationResult['failed_groups']
            ];

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'event_type' => 'RULE_EVAL_FAIL',
                'details' => json_encode(['rule_id' => $rule->id, 'reason' => $evaluationResult['failed_groups']]),
            ]);
        }

        if ($successfulRouting) {
            return response()->json([
                'message' => 'Ticket successfully advanced.',
                'current_stage_id' => $ticket->current_stage_id,
                'status' => $ticket->status,
                'summary' => $executionSummary
            ], 200);
        }

        return response()->json([
            'message' => 'Advancement failed. No routing rules qualified for the ticket data.',
            'routing_failures' => $executionSummary['rule_failures'] ?? []
        ], 409);
    }

    private function executeActions(Ticket $ticket, array $actions): void
    {
        foreach ($actions as $action) {
            $type = $action['action_type'] ?? null;

            if ($type === 'ROUTE_TO_STAGE' && ($stageId = $action['target_stage_id'] ?? null)) {
                $ticket->current_stage_id = $stageId;
                $ticket->save();

                TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'event_type' => 'STAGE_ENTERED',
                    'details' => json_encode(['stage_id' => $stageId]),
                ]);
            }

            if ($type === 'SET_TICKET_STATUS' && ($status = $action['status_value'] ?? null)) {
                $ticket->status = $status;
                $ticket->save();

                 TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'event_type' => 'STATUS_CHANGE',
                    'details' => json_encode(['new_status' => $status]),
                ]);
            }
        }
    }
}

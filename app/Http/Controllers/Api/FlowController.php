<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Models\Ticket;
use App\Services\FlowVersionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlowController extends Controller
{
    protected $flowVersionService;

    public function __construct(FlowVersionService $flowVersionService)
    {
        $this->flowVersionService = $flowVersionService;
    }

    /**
     * @OA\Get(
     *     path="/flows",
     *     summary="Display a listing of the resource.",
     *     tags={"Flows"},
     *     @OA\Response(response=200, description="Successful operation.")
     * )
     */
    public function index()
    {
        return Flow::with('latestVersion')->get();
    }

    /**
     * @OA\Post(
     *     path="/flows",
     *     summary="Store a newly created resource in storage.",
     *     tags={"Flows"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "flow_structure"},
     *             @OA\Property(property="name", type="string", example="New Flow"),
     *             @OA\Property(property="description", type="string", example="A description for the new flow."),
     *             @OA\Property(property="flow_structure", type="object")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Flow created successfully.")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'flow_structure' => 'required|array',
        ]);

        $flow = Flow::create($request->only('name', 'description'));

        $flowVersion = $this->flowVersionService->createNewVersion($flow, $request->flow_structure);

        return response()->json($flow->load('latestVersion'), 201);
    }

    /**
     * @OA\Put(
     *     path="/flows/{id}",
     *     summary="Update a flow and create a new version.",
     *     tags={"Flows"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "flow_structure"},
     *             @OA\Property(property="name", type="string", example="Updated Flow Name"),
     *             @OA\Property(property="description", type="string", example="Updated description."),
     *             @OA\Property(property="flow_structure", type="object")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Flow updated successfully.")
     * )
     */
    public function update(Request $request, Flow $flow)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'flow_structure' => 'sometimes|required|array',
        ]);

        $flow->update($request->only('name', 'description'));

        if ($request->has('flow_structure')) {
            $this->flowVersionService->createNewVersion($flow, $request->flow_structure);
        }

        return response()->json($flow->load('latestVersion'), 200);
    }

    /**
     * @OA\Delete(
     *     path="/flows/{id}",
     *     summary="Conditional Soft Delete a Flow.",
     *     tags={"Flows"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Flow deleted successfully."),
     *     @OA\Response(response=409, description="Conflict, in-flight tickets exist.")
     * )
     */
    public function destroy(Flow $flow)
    {
        $inFlightTicketsCount = Ticket::where('flow_version_id', $flow->latest_version_id)
                                      ->where('status', 'IN_PROGRESS')
                                      ->count();

        if ($inFlightTicketsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete flow. In-flight tickets are linked to the current version.',
                'in_flight_count' => $inFlightTicketsCount
            ], 409);
        }

        try {
            $flow->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete flow.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/flows/{id}",
     *     summary="Retrieve the master Flow and its latest version definition.",
     *     tags={"Flows"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation.")
     * )
     */
    public function show(Flow $flow)
    {
        $flow->load('latestVersion');
        return response()->json($flow, 200);
    }

    /**
     * @OA\Get(
     *     path="/flows/{id}/versions",
     *     summary="Retrieve all immutable flow versions.",
     *     tags={"Flows"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation.")
     * )
     */
    public function versions(Flow $flow)
    {
        $versions = $flow->versions()->orderByDesc('version_number')->get();
        return response()->json($versions, 200);
    }
}

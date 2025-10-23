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
     * Display a listing of the resource.
     */
    public function index()
    {
        return Flow::with('latestVersion')->get();
    }

    /**
     * Store a newly created resource in storage.
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
     * Conditional Soft Delete a Flow.
     * Only allowed if no in-flight tickets reference the latest version.
     */
    public function destroy(Flow $flow)
    {
        // 1. Check for in-flight tickets linked to the LATEST version
        $inFlightTicketsCount = Ticket::where('flow_version_id', $flow->latest_version_id)
                                      ->where('status', 'IN_PROGRESS')
                                      ->count();

        if ($inFlightTicketsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete flow. In-flight tickets are linked to the current version.',
                'in_flight_count' => $inFlightTicketsCount
            ], 409); // HTTP 409 Conflict
        }

        try {
            // 2. Perform Soft Delete (deleted_at will be set)
            $flow->delete();

            // Optional: You may also soft-delete related FlowVersions,
            // but the prompt only strictly requires the master Flow deletion.

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete flow.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve the master Flow and its latest version definition.
     */
    public function show(Flow $flow)
    {
        // Eager load the latest version to show the current structure
        $flow->load('latestVersion');

        return response()->json($flow, 200);
    }

    /**
     * Retrieve all immutable flow versions.
     */
    public function versions(Flow $flow)
    {
        // Retrieve all associated FlowVersions
        $versions = $flow->versions()->orderByDesc('version_number')->get();

        return response()->json($versions, 200);
    }
}

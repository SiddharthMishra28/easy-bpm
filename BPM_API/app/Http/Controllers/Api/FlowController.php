<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Services\FlowVersionService;
use Illuminate\Http\Request;

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
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Flow::with('latestVersion')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'flow_structure' => 'required|array',
        ]);

        $flow = Flow::findOrFail($id);

        if ($request->has('name') || $request->has('description')) {
            $flow->update($request->only('name', 'description'));
        }

        $this->flowVersionService->createNewVersion($flow, $request->flow_structure);

        return response()->json($flow->load('latestVersion'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $flow = Flow::findOrFail($id);
        $flow->delete();

        return response()->json(null, 204);
    }

    /**
     * Display a listing of the versions for the specified resource.
     */
    public function versions(string $id)
    {
        $flow = Flow::findOrFail($id);
        return $flow->versions()->orderBy('version_number', 'desc')->get();
    }
}

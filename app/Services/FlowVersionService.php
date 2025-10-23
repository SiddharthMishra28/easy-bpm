<?php

namespace App\Services;

use App\Models\Flow;
use App\Models\FlowVersion;
// ... (Include other models: FlowStage, StageTask, StageRule)

class FlowVersionService
{
    /**
     * Creates a new immutable FlowVersion and links it to the master Flow.
     */
    public function createNewVersion(Flow $flow, array $newFlowStructure): FlowVersion
    {
        // 1. Determine the next version number
        $latestVersion = $flow->versions()->latest('version_number')->first();
        $newVersionNumber = $latestVersion ? $latestVersion->version_number + 1 : 1;

        // 2. Create the immutable FlowVersion record
        $flowVersion = FlowVersion::create([
            'flow_id' => $flow->id,
            'version_number' => $newVersionNumber,
            'definition_json' => json_encode($newFlowStructure), // Store the snapshot
        ]);

        // 3. Persist the structural components linked to this new version
        $this->persistFlowStructure($flowVersion, $newFlowStructure);

        // 4. Update the master Flow to point to the new version
        $flow->latest_version_id = $flowVersion->id;
        $flow->save();

        return $flowVersion;
    }

    /**
     * Persists stages, tasks, and rules as separate records linked to the new FlowVersion.
     * This is required for `tickets.current_stage_id` and `ticket_task_status.stage_task_id`
     * to reference specific database IDs rather than relying solely on the JSON snapshot.
     */
    private function persistFlowStructure(FlowVersion $version, array $structure): void
    {
        // Assume $structure['stages'] is an array of stage definitions
        foreach ($structure['stages'] ?? [] as $stageData) {
            $stage = $version->stages()->create([
                'name' => $stageData['name'],
                'sequence' => $stageData['sequence'],
                'is_start_stage' => $stageData['is_start_stage'] ?? false,
                'is_end_stage' => $stageData['is_end_stage'] ?? false,
            ]);

            // Persist Tasks
            foreach ($stageData['tasks'] ?? [] as $taskData) {
                $task = $stage->tasks()->create($taskData); // Assuming simple array merge works
                // Important: Update the snapshot JSON with the new task ID for rule lookups
                // (Advanced: requires updating the snapshot *after* all IDs are generated)
            }

            // Persist Rules
            foreach ($stageData['rules'] ?? [] as $ruleData) {
                $stage->rules()->create([
                    'rule_type' => $ruleData['rule_type'] ?? 'ROUTING',
                    'rule_definition_json' => json_encode($ruleData['rule_definition']),
                ]);
            }
        }
    }
}

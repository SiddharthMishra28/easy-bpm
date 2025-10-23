<?php

namespace App\Services;

use Illuminate\Support\Arr;

class RuleEngineService
{
    /**
     * Entry point to evaluate the Rule Definition JSON against ticket data.
     *
     * @param array $ruleDefinition The decoded JSON array from stage_rules.rule_definition_json
     * @param array $ticketData The decoded JSON array from tickets.ticket_data
     * @param array $completedTasks Array of completed StageTask IDs for checkpoint checks
     * @return array ['success' => bool, 'failed_groups' => array, 'actions' => array]
     */
    public function evaluate(array $ruleDefinition, array $ticketData, array $completedTasks): array
    {
        $allGroupsPass = true;
        $failedGroups = [];

        foreach ($ruleDefinition['groups'] ?? [] as $group) {
            $groupResult = $this->processGroup($group, $ticketData, $completedTasks);

            if (!$groupResult['passes']) {
                $allGroupsPass = false;
                $failedGroups[] = [
                    'description' => $group['description'] ?? 'Unnamed Group',
                    'errors' => $groupResult['errors']
                ];
            }
        }

        return [
            'success' => $allGroupsPass,
            'failed_groups' => $failedGroups,
            'actions' => $allGroupsPass ? ($ruleDefinition['actions'] ?? []) : []
        ];
    }

    /**
     * Recursively processes a single rule group (which can contain conditions or sub-groups).
     */
    private function processGroup(array $group, array $ticketData, array $completedTasks): array
    {
        $operator = strtoupper($group['operator'] ?? 'AND');
        $groupPasses = ($operator === 'AND'); // Start AND with true, OR with false
        $errors = [];

        foreach ($group['children'] ?? [] as $child) {
            $result = ['passes' => false, 'errors' => []];

            if (($child['type'] ?? '') === 'GROUP') {
                $result = $this->processGroup($child, $ticketData, $completedTasks);
            } elseif (($child['type'] ?? '') === 'CONDITION') {
                $result = $this->evaluateCondition($child, $ticketData);
            } elseif (($child['type'] ?? '') === 'CHECKPOINT') {
                $result = $this->evaluateCheckpoint($child, $completedTasks);
            }

            // Apply Boolean Logic
            if ($operator === 'AND') {
                $groupPasses = $groupPasses && $result['passes'];
                if (!$result['passes']) {
                    $errors[] = $result['errors']; // Collect all failing reasons
                    // Optimization: For AND, we can stop immediately upon first failure
                    // break;
                }
            } elseif ($operator === 'OR') {
                $groupPasses = $groupPasses || $result['passes'];
                if ($result['passes']) {
                    // Optimization: For OR, we can stop immediately upon first success
                    return ['passes' => true, 'errors' => []];
                }
                if (!$result['passes']) {
                    $errors[] = $result['errors']; // Collect all failing reasons
                }
            }
        }

        // If OR fails, we return the accumulated errors. If AND fails, we return all errors.
        return ['passes' => $groupPasses, 'errors' => Arr::flatten($errors)];
    }

    /**
     * Evaluates a single data condition (e.g., ticket_data.priority == 'High').
     */
    private function evaluateCondition(array $condition, array $ticketData): array
    {
        $field = $condition['field'] ?? null;
        $operator = strtoupper($condition['operator'] ?? '');
        $value = $condition['value'] ?? null;

        if (!$field) {
            return ['passes' => false, 'errors' => ['Rule definition missing target field.']];
        }

        // Use Arr::get to safely access nested JSON data using dot notation
        $dataValue = Arr::get($ticketData, str_replace('ticket_data.', '', $field));

        $result = match ($operator) {
            'EQUALS' => $dataValue == $value,
            'NOT_EQUALS' => $dataValue != $value,
            'GT' => is_numeric($dataValue) && is_numeric($value) && $dataValue > $value,
            'LT' => is_numeric($dataValue) && is_numeric($value) && $dataValue < $value,
            // Simple string containment check
            'CONTAINS' => is_string($dataValue) && str_contains($dataValue, $value),
            // Checks if the value is empty (null, empty string, empty array)
            'IS_EMPTY' => empty($dataValue),
            default => false,
        };

        if ($result) {
            return ['passes' => true];
        }

        $errorMsg = "Condition failed: $field $operator $value (Actual: " . json_encode($dataValue) . ")";
        return ['passes' => false, 'errors' => [$errorMsg]];
    }

    /**
     * Evaluates a checkpoint condition (i.e., mandatory task completion).
     */
    private function evaluateCheckpoint(array $checkpoint, array $completedTasks): array
    {
        $taskId = $checkpoint['task_id'] ?? null;
        $condition = strtoupper($checkpoint['condition'] ?? '');

        if (!$taskId) {
            return ['passes' => false, 'errors' => ['Checkpoint definition missing task ID.']];
        }

        $taskIsComplete = in_array($taskId, $completedTasks);

        // We only support IS_COMPLETED for routing rules
        if ($condition === 'IS_COMPLETED') {
            if ($taskIsComplete) {
                return ['passes' => true];
            } else {
                return ['passes' => false, 'errors' => ["Mandatory checkpoint (Task ID: $taskId) is incomplete."]];
            }
        }

        return ['passes' => false, 'errors' => ["Unsupported checkpoint condition: $condition."]];
    }
}

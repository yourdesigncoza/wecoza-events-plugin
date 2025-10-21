<?php
declare(strict_types=1);

namespace WeCozaEvents\Services;

use WeCozaEvents\Models\TaskCollection;

use function apply_filters;
use function strtolower;

final class TaskTemplateRegistry
{
    public function getTemplateForOperation(string $operation): TaskCollection
    {
        $operation = strtolower($operation);
        $defaults = $this->getDefaults();
        $templates = apply_filters('wecoza_events_task_templates', $defaults, $operation);

        $tasks = $templates[$operation] ?? [];
        return TaskCollection::fromArray($tasks);
    }

    /**
     * @return array<string, array<int, array{id:string,label:string}>>
     */
    private function getDefaults(): array
    {
        return [
            'insert' => [
               //  ['id' => 'create-class', 'label' => 'Create new class'],
                ['id' => 'agent-order', 'label' => 'Agent order Number'],
                ['id' => 'load-learners', 'label' => 'Load learners'],
                ['id' => 'training-schedule', 'label' => 'Training schedule'],
                ['id' => 'material-delivery', 'label' => 'Material delivery'],
                ['id' => 'agent-paperwork', 'label' => 'Agent paperwork'],
            ],
            'update' => [
                ['id' => 'review-update', 'label' => 'Review class update'],
                ['id' => 'notify-agents', 'label' => 'Notify agents of changes'],
                ['id' => 'adjust-materials', 'label' => 'Adjust materials or schedule'],
            ],
            'delete' => [
                ['id' => 'inform-stakeholders', 'label' => 'Inform stakeholders'],
                ['id' => 'archive-records', 'label' => 'Archive class records'],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Exports\Generators;

use App\Domains\Exports\Contracts\ExportGeneratorInterface;
use App\Domains\Exports\Enums\ExportType;
use App\Domains\Exports\Models\ExportJob;
use App\Domains\Tasks\Models\Task;
use League\Csv\Writer;

class TasksCsvGenerator implements ExportGeneratorInterface
{
    public function supports(ExportJob $job): bool
    {
        return $job->export_type === ExportType::Tasks && $job->format === 'csv';
    }

    public function generate(ExportJob $job): array
    {
        $params = $job->input_params ?? [];

        $tasks = Task::query()
            ->when($job->organization_id, fn ($q, $id) => $q->where('organization_id', $id))
            ->when($params['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($params['date_from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($params['date_to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->with(['assignee:id,name'])
            ->get();

        $csv = Writer::createFromString();
        $csv->setOutputBOM(Writer::BOM_UTF8);

        $csv->insertOne([
            'شماره', 'عنوان', 'وضعیت', 'اولویت',
            'مسئول', 'مهلت', 'پیشرفت', 'سطح Escalation', 'ایجاد',
        ]);

        foreach ($tasks as $task) {
            $csv->insertOne([
                $task->task_number,
                $task->title,
                $task->status?->value ?? '',
                $task->priority?->value ?? '',
                $task->assignee?->name ?? '—',
                $task->due_date?->format('Y-m-d') ?? '',
                $task->progress_percent . '%',
                $task->escalation_level,
                $task->created_at?->format('Y-m-d H:i'),
            ]);
        }

        return [
            'content' => $csv->toString(),
            'mime' => 'text/csv; charset=utf-8',
            'extension' => 'csv',
            'filename' => 'tasks_' . now()->format('Ymd_His') . '.csv',
            'row_count' => $tasks->count(),
        ];
    }
}

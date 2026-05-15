@php
    /** @var \App\Domains\Workflow\Models\ProcessInstance|null $record */
    $record = $getRecord ?? null;
    $record = is_callable($record) ? $record() : ($record ?? $this->getRecord() ?? null);
    $processKey = $record->process_key ?? null;
    $status = $record->status ?? null;
    $bpmnXml = $record->bpmn_xml ?? ($record->definition->bpmn_xml ?? null);
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
    <div class="mb-3 flex items-center justify-between">
        <div class="font-semibold">نمای BPMN</div>
        <div class="text-xs text-gray-500">
            @if ($processKey) <span>کلید: {{ $processKey }}</span> @endif
            @if ($status) <span class="ms-2">وضعیت: {{ $status }}</span> @endif
        </div>
    </div>

    @if ($bpmnXml)
        <details>
            <summary class="cursor-pointer text-xs text-gray-500">نمایش XML</summary>
            <pre class="mt-2 max-h-96 overflow-auto rounded bg-gray-50 p-2 text-xs dark:bg-gray-800">{{ $bpmnXml }}</pre>
        </details>
    @else
        <div class="text-xs text-gray-500">
            نمایش گرافیکی BPMN در این نسخه فعال نیست. برای فعال‌سازی، رندرر bpmn-js را به این view اضافه کنید.
        </div>
    @endif
</div>

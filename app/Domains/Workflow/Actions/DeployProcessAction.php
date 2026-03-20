<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Actions;

use App\Domains\Audit\Services\AuditService;
use App\Domains\Workflow\Enums\ProcessDefinitionStatus;
use App\Domains\Workflow\Models\ProcessDefinition;
use App\Domains\Workflow\Models\ProcessElement;
use App\Domains\Workflow\Services\Parser\BpmnXmlParser;
use Illuminate\Support\Facades\DB;

/**
 * Deploy یک فرایند: پارس، اعتبارسنجی و انتشار.
 *
 * در صورت وجود نسخه قبلی همان process_key:
 *  - شماره نسخه ++
 *  - نسخه‌های قبلی is_latest=false می‌شوند
 *  - این نسخه به‌عنوان latest تنظیم می‌شود
 */
class DeployProcessAction
{
    public function __construct(
        private readonly BpmnXmlParser $parser,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * @param array $data
     *   - organization_id
     *   - process_key
     *   - name
     *   - description?
     *   - category?
     *   - bpmn_xml
     *   - start_form_schema?
     *   - variables_schema?
     *   - creator_user_id?
     *   - publish_immediately? (bool)
     */
    public function execute(array $data): ProcessDefinition
    {
        // پارس و اعتبارسنجی
        $parsed = $this->parser->parse($data['bpmn_xml']);
        $this->parser->validate($parsed);

        return DB::transaction(function () use ($data, $parsed) {
            $organizationId = $data['organization_id'];
            $processKey = $data['process_key'];

            // محاسبه شماره نسخه
            $lastVersion = ProcessDefinition::where('organization_id', $organizationId)
                ->where('process_key', $processKey)
                ->withTrashed()
                ->max('version');
            $newVersion = ($lastVersion ?? 0) + 1;

            $publishImmediately = (bool) ($data['publish_immediately'] ?? false);

            // علامت is_latest نسخه‌های قبلی
            if ($publishImmediately) {
                ProcessDefinition::where('organization_id', $organizationId)
                    ->where('process_key', $processKey)
                    ->update(['is_latest' => false]);
            }

            $definition = ProcessDefinition::create([
                'organization_id' => $organizationId,
                'process_key' => $processKey,
                'version' => $newVersion,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'bpmn_xml' => $data['bpmn_xml'],
                'bpmn_xml_hash' => hash('sha256', $data['bpmn_xml']),
                'parsed_metadata' => $parsed,
                'start_form_schema' => $data['start_form_schema'] ?? null,
                'variables_schema' => $data['variables_schema'] ?? null,
                'status' => $publishImmediately
                    ? ProcessDefinitionStatus::Published
                    : ProcessDefinitionStatus::Draft,
                'is_latest' => $publishImmediately,
                'published_by_user_id' => $publishImmediately ? auth()->id() : null,
                'published_at' => $publishImmediately ? now() : null,
                'creator_user_id' => $data['creator_user_id'] ?? auth()->id(),
            ]);

            // ذخیره elements
            foreach ($parsed['elements'] as $index => $elem) {
                ProcessElement::create([
                    'process_definition_id' => $definition->id,
                    'element_id' => $elem['element_id'],
                    'element_type' => $elem['element_type'],
                    'name' => $elem['name'],
                    'properties' => $elem['properties'],
                    'form_schema' => $elem['form_schema'],
                    'service_task_class' => $elem['service_task_class'],
                    'service_task_config' => $elem['service_task_config'],
                    'sort_order' => $index,
                ]);
            }

            $this->auditService->log(
                event: $publishImmediately ? 'process_published' : 'process_deployed_as_draft',
                auditable: $definition,
                description: sprintf(
                    "فرایند '%s' نسخه %d %s شد",
                    $definition->process_key,
                    $newVersion,
                    $publishImmediately ? 'منتشر' : 'ذخیره',
                ),
                context: [
                    'version' => $newVersion,
                    'elements_count' => count($parsed['elements']),
                ],
                severity: 'notice',
            );

            return $definition;
        });
    }
}

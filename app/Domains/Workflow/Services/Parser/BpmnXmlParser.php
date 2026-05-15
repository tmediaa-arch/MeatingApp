<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Parser;

use App\Domains\Workflow\Enums\ElementType;
use App\Domains\Workflow\Exceptions\WorkflowException;
use SimpleXMLElement;

/**
 * پارسر BPMN 2.0 XML.
 *
 * این کلاس XML را به یک ساختار آرایه‌ای normalize می‌کند که
 * موتور runtime با آن کار کند.
 *
 * توجه:
 * - فقط زیرمجموعه‌ای از BPMN 2.0 پشتیبانی می‌شود (فهرست در ElementType)
 * - extension elements با namespace `mms:` برای پراپرتی‌های اختصاصی
 *   (مثل assignee، due_date، service task class)
 */
class BpmnXmlParser
{
    private const BPMN_NS = 'http://www.omg.org/spec/BPMN/20100524/MODEL';
    private const MMS_NS = 'http://mms.local/bpmn';

    /**
     * پارس یک XML و برگرداندن ساختار normalized.
     *
     * خروجی:
     * [
     *   'process_id' => string,
     *   'process_name' => string,
     *   'documentation' => string,
     *   'elements' => [
     *     [
     *       'element_id' => string,
     *       'element_type' => string,
     *       'name' => ?string,
     *       'properties' => array,
     *       'form_schema' => ?array,
     *       'service_task_class' => ?string,
     *       'service_task_config' => ?array,
     *     ],
     *     ...
     *   ],
     *   'flows' => [
     *     ['id' => , 'source' => , 'target' => , 'condition' => ?, 'default' => bool]
     *   ],
     * ]
     */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $simple = simplexml_load_string($xml);
            if ($simple === false) {
                $errors = libxml_get_errors();
                $messages = array_map(fn ($e) => trim($e->message), $errors);
                throw WorkflowException::bpmnParseError(implode('; ', $messages));
            }

            $simple->registerXPathNamespace('bpmn', self::BPMN_NS);
            $simple->registerXPathNamespace('mms', self::MMS_NS);

            $process = $this->findProcess($simple);

            // namespace را روی process node هم register کن — هر SimpleXMLElement جداگانه است
            $process->registerXPathNamespace('bpmn', self::BPMN_NS);
            $process->registerXPathNamespace('mms', self::MMS_NS);

            return [
                'process_id' => (string) $this->attr($process, 'id', ''),
                'process_name' => (string) $this->attr($process, 'name', ''),
                'documentation' => $this->extractDocumentation($process),
                'elements' => $this->extractElements($process),
                'flows' => $this->extractFlows($process),
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * اعتبارسنجی ساختاری BPMN.
     */
    public function validate(array $parsed): void
    {
        $elements = $parsed['elements'];

        // باید دقیقاً یک startEvent داشته باشد
        $startEvents = array_filter(
            $elements,
            fn ($e) => $e['element_type'] === ElementType::StartEvent->value,
        );
        if (count($startEvents) === 0) {
            throw WorkflowException::noStartEvent();
        }
        if (count($startEvents) > 1) {
            throw WorkflowException::multipleStartEvents();
        }

        // باید حداقل یک endEvent داشته باشد
        $endEvents = array_filter(
            $elements,
            fn ($e) => $e['element_type'] === ElementType::EndEvent->value,
        );
        if (count($endEvents) === 0) {
            throw WorkflowException::bpmnParseError('فرایند هیچ end event ندارد.');
        }

        // هر element non-end باید outgoing flow داشته باشد
        foreach ($elements as $elem) {
            if (empty($elem['element_id'])) continue; // عناصر بدون id نادیده گرفته می‌شوند
            $type = ElementType::from($elem['element_type']);
            if ($type === ElementType::EndEvent) continue;
            if ($type->isFlow()) continue;

            $outgoing = $elem['properties']['outgoing'] ?? [];
            if (empty($outgoing)) {
                throw WorkflowException::noOutgoingFlow($elem['element_id']);
            }
        }

        // اعتبارسنجی exclusive gateway: حداقل ۲ خروجی، حداقل یکی default یا condition
        foreach ($elements as $elem) {
            if ($elem['element_type'] !== ElementType::ExclusiveGateway->value) continue;
            $outgoing = $elem['properties']['outgoing'] ?? [];
            if (count($outgoing) < 2) continue; // gateway با ۱ خروجی pass-through است
            $flows = array_filter($parsed['flows'], fn ($f) => in_array($f['id'], $outgoing, true));
            $hasDefault = false;
            $allHaveCondition = true;
            foreach ($flows as $f) {
                if ($f['default']) $hasDefault = true;
                if (empty($f['condition'])) $allHaveCondition = false;
            }
            if (!$hasDefault && !$allHaveCondition) {
                throw WorkflowException::bpmnParseError(
                    "ExclusiveGateway '{$elem['element_id']}' یا باید یک default داشته باشد یا همه شرایط condition داشته باشند.",
                );
            }
        }
    }

    // ─────────── Private ───────────

    private function ns(SimpleXMLElement $node): SimpleXMLElement
    {
        $node->registerXPathNamespace('bpmn', self::BPMN_NS);
        $node->registerXPathNamespace('mms', self::MMS_NS);
        return $node;
    }

    /**
     * خواندن یک attribute بدون namespace از یک node.
     *
     * دسترسی آرایه‌ای ($node['id']) روی node هایی که از children(NS)
     * گرفته شده‌اند، attribute های بدون namespace را برنمی‌گرداند.
     * این helper با attributes() صریح آن مشکل را حل می‌کند.
     */
    private function attr(SimpleXMLElement $node, string $name, ?string $default = null): ?string
    {
        $attributes = $node->attributes();
        if ($attributes !== null && isset($attributes[$name])) {
            return (string) $attributes[$name];
        }

        return $default;
    }

    private function findProcess(SimpleXMLElement $root): SimpleXMLElement
    {
        $processes = $this->ns($root)->xpath('//bpmn:process');
        if (empty($processes)) {
            throw WorkflowException::bpmnParseError('هیچ <bpmn:process> در XML یافت نشد.');
        }
        if (count($processes) > 1) {
            throw WorkflowException::bpmnParseError('بیش از یک <bpmn:process> پشتیبانی نمی‌شود.');
        }
        return $processes[0];
    }

    private function extractDocumentation(SimpleXMLElement $process): string
    {
        $docs = $this->ns($process)->xpath('./bpmn:documentation');
        return $docs ? trim((string) $docs[0]) : '';
    }

    /**
     * استخراج تمام عناصر داخل process.
     */
    private function extractElements(SimpleXMLElement $process): array
    {
        $elements = [];
        $supportedTypes = collect(ElementType::cases())
            ->map(fn ($t) => $t->value)
            ->reject(fn ($t) => $t === ElementType::SequenceFlow->value) // flows جداگانه
            ->values()
            ->all();

        foreach ($process->children(self::BPMN_NS) as $child) {
            $localName = $child->getName();

            if ($localName === 'sequenceFlow') continue; // در extractFlows
            if ($localName === 'documentation') continue;
            if ($localName === 'laneSet') continue;
            if ($localName === 'lane') continue;
            if ($localName === 'textAnnotation') continue;
            if ($localName === 'association') continue;

            // skip اگر id ندارد (مثل برخی annotation ها)
            $elemId = (string) $this->attr($child, 'id', '');
            if ($elemId === '') continue;

            if (!in_array($localName, $supportedTypes, true)) {
                throw WorkflowException::unsupportedElement($localName);
            }

            $element = [
                'element_id' => $elemId,
                'element_type' => $localName,
                'name' => $this->attr($child, 'name'),
                'properties' => $this->extractElementProperties($child, $localName),
                'form_schema' => $this->extractFormSchema($child),
                'service_task_class' => null,
                'service_task_config' => null,
            ];

            if ($localName === ElementType::ServiceTask->value) {
                $element['service_task_class'] = $this->extractServiceTaskClass($child);
                $element['service_task_config'] = $this->extractServiceTaskConfig($child);
            }

            $elements[] = $element;
        }

        return $this->linkFlows($elements, $process);
    }

    /**
     * ارتباط incoming/outgoing flows را به هر element متصل می‌کند.
     */
    private function linkFlows(array $elements, SimpleXMLElement $process): array
    {
        $bySource = [];
        $byTarget = [];

        foreach ($this->ns($process)->xpath('./bpmn:sequenceFlow') as $flow) {
            $flowId = (string) $this->attr($flow, 'id', '');
            $source = (string) $this->attr($flow, 'sourceRef', '');
            $target = (string) $this->attr($flow, 'targetRef', '');

            $bySource[$source][] = $flowId;
            $byTarget[$target][] = $flowId;
        }

        foreach ($elements as &$elem) {
            $id = $elem['element_id'];
            $elem['properties']['outgoing'] = $bySource[$id] ?? [];
            $elem['properties']['incoming'] = $byTarget[$id] ?? [];
        }

        return $elements;
    }

    private function extractElementProperties(SimpleXMLElement $element, string $type): array
    {
        $props = [];

        // ── extension elements با namespace mms ──
        $mmsElements = $element->children(self::MMS_NS);

        if (isset($mmsElements->assignee)) {
            $props['assignee'] = (string) $mmsElements->assignee;
        }
        if (isset($mmsElements->candidateUsers)) {
            $props['candidate_users'] = (string) $mmsElements->candidateUsers;
        }
        if (isset($mmsElements->candidateGroups)) {
            $props['candidate_groups'] = (string) $mmsElements->candidateGroups;
        }
        if (isset($mmsElements->dueDate)) {
            $props['due_date'] = (string) $mmsElements->dueDate;
        }
        if (isset($mmsElements->priority)) {
            $props['priority'] = (string) $mmsElements->priority;
        }

        // ── Timer events ──
        if ($type === ElementType::IntermediateCatchEvent->value
            || $type === ElementType::BoundaryEvent->value
        ) {
            $bpmn = $element->children(self::BPMN_NS);
            if (isset($bpmn->timerEventDefinition)) {
                $timer = $bpmn->timerEventDefinition;
                if (isset($timer->timeDuration)) {
                    $props['timer_duration'] = (string) $timer->timeDuration;
                }
                if (isset($timer->timeDate)) {
                    $props['timer_date'] = (string) $timer->timeDate;
                }
                if (isset($timer->timeCycle)) {
                    $props['timer_cycle'] = (string) $timer->timeCycle;
                }
            }
            if (isset($bpmn->messageEventDefinition)) {
                $props['message_ref'] = (string) $this->attr($bpmn->messageEventDefinition, 'messageRef', '');
            }
        }

        // ── Boundary event attachedToRef ──
        if ($type === ElementType::BoundaryEvent->value) {
            $props['attached_to'] = (string) $this->attr($element, 'attachedToRef', '');
            $props['cancel_activity'] = $this->attr($element, 'cancelActivity', 'true') === 'true';
        }

        // ── User task documentation ──
        $bpmn = $element->children(self::BPMN_NS);
        if (isset($bpmn->documentation)) {
            $props['documentation'] = trim((string) $bpmn->documentation);
        }

        return $props;
    }

    private function extractFormSchema(SimpleXMLElement $element): ?array
    {
        $mmsElements = $element->children(self::MMS_NS);
        if (!isset($mmsElements->formSchema)) return null;

        $jsonStr = trim((string) $mmsElements->formSchema);
        if ($jsonStr === '') return null;

        try {
            return json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw WorkflowException::bpmnParseError("formSchema JSON نامعتبر: {$e->getMessage()}");
        }
    }

    private function extractServiceTaskClass(SimpleXMLElement $element): ?string
    {
        $mms = $element->children(self::MMS_NS);
        return isset($mms->serviceTaskClass) ? trim((string) $mms->serviceTaskClass) : null;
    }

    private function extractServiceTaskConfig(SimpleXMLElement $element): ?array
    {
        $mms = $element->children(self::MMS_NS);
        if (!isset($mms->serviceTaskConfig)) return null;

        $config = [];
        foreach ($mms->serviceTaskConfig->children(self::MMS_NS) as $entry) {
            $config[(string) $this->attr($entry, 'key', '')] = (string) $entry;
        }
        return $config;
    }

    private function extractFlows(SimpleXMLElement $process): array
    {
        $flows = [];

        // یافتن default flows از gateways
        $defaultsByGateway = [];
        foreach (['./bpmn:exclusiveGateway', './bpmn:inclusiveGateway'] as $gatewayPath) {
            foreach ($this->ns($process)->xpath($gatewayPath) as $gateway) {
                $default = $this->attr($gateway, 'default');
                if ($default !== null && $default !== '') {
                    $defaultsByGateway[$default] = true;
                }
            }
        }

        foreach ($this->ns($process)->xpath('./bpmn:sequenceFlow') as $flow) {
            $id = (string) $this->attr($flow, 'id', '');
            $f = [
                'id' => $id,
                'name' => $this->attr($flow, 'name'),
                'source' => (string) $this->attr($flow, 'sourceRef', ''),
                'target' => (string) $this->attr($flow, 'targetRef', ''),
                'condition' => null,
                'default' => isset($defaultsByGateway[$id]),
            ];

            $bpmn = $flow->children(self::BPMN_NS);
            if (isset($bpmn->conditionExpression)) {
                $f['condition'] = trim((string) $bpmn->conditionExpression);
            }

            $flows[] = $f;
        }

        return $flows;
    }
}
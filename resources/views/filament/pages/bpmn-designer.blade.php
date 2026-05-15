<x-filament-panels::page>
    <div class="space-y-4">
        {{ $this->form }}

        <div class="rounded-lg border border-gray-200 bg-white dark:bg-gray-900 p-2"
             wire:ignore
             x-data="bpmnDesigner(@js($this->getServiceTasksJson()))"
             x-init="initModeler()">

            {{-- نوار ابزار --}}
            <div class="flex flex-wrap gap-2 mb-2 p-2 bg-gray-50 dark:bg-gray-800 rounded">
                <button type="button" @click="loadDefault()"
                        class="px-3 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600">شروع جدید</button>
                <label class="px-3 py-1 text-xs bg-gray-500 text-white rounded cursor-pointer hover:bg-gray-600">
                    وارد کردن XML
                    <input type="file" accept=".bpmn,.xml" @change="importFile($event)" class="hidden">
                </label>
                <button type="button" @click="exportXml()"
                        class="px-3 py-1 text-xs bg-purple-500 text-white rounded hover:bg-purple-600">دریافت XML</button>
                <button type="button" @click="saveToForm()"
                        class="px-3 py-1 text-xs bg-green-500 text-white rounded hover:bg-green-600">ذخیره در فرم</button>
                <button type="button" @click="autoColor()"
                        class="px-3 py-1 text-xs bg-amber-500 text-white rounded hover:bg-amber-600">رنگ‌آمیزی خودکار</button>
            </div>

            <div class="flex gap-2" style="min-height: 650px;">
                {{-- بوم طراحی --}}
                <div id="bpmn-modeler-canvas" class="flex-1" style="height: 650px; background: white; border:1px solid #e5e7eb; border-radius:6px;"></div>

                {{-- پنل کناری --}}
                <div class="w-72 shrink-0 space-y-3 overflow-y-auto" style="height: 650px;">
                    {{-- افزودن وظیفه سرویس --}}
                    <div class="rounded border border-gray-200 dark:border-gray-700">
                        <div class="px-2 py-1.5 text-xs font-bold bg-gray-100 dark:bg-gray-800 rounded-t">
                            وظایف سرویس (افزودن به فرایند)
                        </div>
                        <div class="p-2 space-y-1">
                            <template x-for="task in serviceTasks" :key="task.key">
                                <button type="button" @click="addServiceTask(task)"
                                        class="w-full text-right px-2 py-1.5 text-xs rounded bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 border border-indigo-200 dark:border-indigo-800">
                                    <span class="font-semibold" x-text="task.description || task.key"></span>
                                    <span class="block text-gray-500" x-text="task.key"></span>
                                </button>
                            </template>
                            <p x-show="serviceTasks.length === 0" class="text-xs text-gray-500">وظیفه سرویسی ثبت نشده است.</p>
                        </div>
                    </div>

                    {{-- تنظیمات عنصر انتخاب‌شده --}}
                    <div class="rounded border border-gray-200 dark:border-gray-700">
                        <div class="px-2 py-1.5 text-xs font-bold bg-gray-100 dark:bg-gray-800 rounded-t">
                            تنظیمات عنصر
                        </div>
                        <div class="p-2 space-y-2 text-xs">
                            <p x-show="!selected" class="text-gray-500">یک عنصر را روی بوم انتخاب کنید.</p>

                            <template x-if="selected">
                                <div class="space-y-2">
                                    <div>
                                        <span class="text-gray-500">نوع:</span>
                                        <span class="font-semibold" x-text="selectedTypeLabel"></span>
                                    </div>

                                    <label class="block">
                                        <span class="text-gray-500">عنوان</span>
                                        <input type="text" x-model="form.name" @change="applyName()"
                                               class="mt-1 w-full rounded border-gray-300 dark:bg-gray-800 text-xs">
                                    </label>

                                    {{-- فیلدهای UserTask --}}
                                    <template x-if="isUserTask">
                                        <div class="space-y-2">
                                            <label class="block">
                                                <span class="text-gray-500">مسئول (assignee)</span>
                                                <input type="text" x-model="form.assignee" @change="applyMms('assignee', form.assignee)"
                                                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-800 text-xs">
                                            </label>
                                            <label class="block">
                                                <span class="text-gray-500">گروه‌های مجاز (candidateGroups)</span>
                                                <input type="text" x-model="form.candidateGroups" @change="applyMms('candidateGroups', form.candidateGroups)"
                                                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-800 text-xs">
                                            </label>
                                            <label class="block">
                                                <span class="text-gray-500">مهلت (dueDate — ISO-8601)</span>
                                                <input type="text" x-model="form.dueDate" @change="applyMms('dueDate', form.dueDate)"
                                                       placeholder="P3D" class="mt-1 w-full rounded border-gray-300 dark:bg-gray-800 text-xs">
                                            </label>
                                            <label class="block">
                                                <span class="text-gray-500">اولویت</span>
                                                <input type="text" x-model="form.priority" @change="applyMms('priority', form.priority)"
                                                       class="mt-1 w-full rounded border-gray-300 dark:bg-gray-800 text-xs">
                                            </label>
                                        </div>
                                    </template>

                                    {{-- فیلدهای ServiceTask --}}
                                    <template x-if="isServiceTask">
                                        <label class="block">
                                            <span class="text-gray-500">کلاس وظیفه سرویس</span>
                                            <select x-model="form.serviceTaskClass" @change="applyMms('serviceTaskClass', form.serviceTaskClass)"
                                                    class="mt-1 w-full rounded border-gray-300 dark:bg-gray-800 text-xs">
                                                <option value="">— انتخاب —</option>
                                                <template x-for="task in serviceTasks" :key="task.key">
                                                    <option :value="task.key" x-text="task.description || task.key"></option>
                                                </template>
                                            </select>
                                        </label>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-xs text-gray-500 mt-2 p-2 bg-gray-50 dark:bg-gray-800 rounded">
                پس از طراحی، روی «ذخیره در فرم» کلیک کنید، سپس «Deploy فرایند».
            </div>
        </div>

        <div class="flex gap-2">
            <x-filament::button wire:click="save" color="success" size="lg">Deploy فرایند</x-filament::button>
        </div>
    </div>
</x-filament-panels::page>

@push('scripts')
<script src="https://unpkg.com/bpmn-js@13.0.0/dist/bpmn-modeler.production.min.js"></script>
<script>
    // ترجمه فارسی رشته‌های bpmn-js
    const bpmnFaDictionary = {
        'Activate the create/remove space tool': 'ابزار ایجاد/حذف فضا',
        'Activate the global connect tool': 'ابزار اتصال سراسری',
        'Activate the hand tool': 'ابزار حرکت',
        'Activate the lasso tool': 'ابزار انتخاب کمندی',
        'Append {type}': 'افزودن {type}',
        'Append EndEvent': 'افزودن رویداد پایان',
        'Append Gateway': 'افزودن دروازه',
        'Append Task': 'افزودن وظیفه',
        'Append intermediate/boundary event': 'افزودن رویداد میانی',
        'Add Lane above': 'افزودن خط بالا',
        'Add Lane below': 'افزودن خط پایین',
        'Change type': 'تغییر نوع',
        'Connect using Association': 'اتصال با Association',
        'Connect using Sequence/MessageFlow or Association': 'اتصال جریان',
        'Connect using DataInputAssociation': 'اتصال داده',
        'Create {type}': 'ایجاد {type}',
        'Create expanded SubProcess': 'ایجاد زیرفرایند',
        'Create EndEvent': 'ایجاد رویداد پایان',
        'Create Gateway': 'ایجاد دروازه',
        'Create StartEvent': 'ایجاد رویداد شروع',
        'Create Task': 'ایجاد وظیفه',
        'Create Pool/Participant': 'ایجاد Pool',
        'Create intermediate/boundary event': 'ایجاد رویداد میانی',
        'Remove': 'حذف',
        'Task': 'وظیفه',
        'User Task': 'وظیفه کاربری',
        'Service Task': 'وظیفه سرویس',
        'Send Task': 'وظیفه ارسال',
        'Receive Task': 'وظیفه دریافت',
        'Manual Task': 'وظیفه دستی',
        'Business Rule Task': 'وظیفه قانون کسب‌وکار',
        'Script Task': 'وظیفه اسکریپت',
        'Start Event': 'رویداد شروع',
        'End Event': 'رویداد پایان',
        'Intermediate Throw Event': 'رویداد میانی پرتاب',
        'Intermediate Catch Event': 'رویداد میانی دریافت',
        'Exclusive Gateway': 'دروازه انحصاری',
        'Parallel Gateway': 'دروازه موازی',
        'Inclusive Gateway': 'دروازه شمولی',
        'Event based Gateway': 'دروازه رویدادمحور',
        'Sub Process': 'زیرفرایند',
        'Sub Process (collapsed)': 'زیرفرایند (جمع‌شده)',
        'Sub Process (expanded)': 'زیرفرایند (بازشده)',
        'Data Store Reference': 'مرجع انبار داده',
        'Data Object Reference': 'مرجع شیء داده',
        'Pool': 'استخر',
        'Participant': 'مشارکت‌کننده',
        'Group': 'گروه',
        'Loop': 'حلقه',
        'Parallel Multi Instance': 'چند نمونه موازی',
        'Sequential Multi Instance': 'چند نمونه ترتیبی',
        'no diagram to display': 'نموداری برای نمایش وجود ندارد',
    };

    function bpmnTranslate(template, replacements) {
        replacements = replacements || {};
        let translated = bpmnFaDictionary[template] || template;
        return translated.replace(/{([^}]+)}/g, (_, key) =>
            (replacements[key] !== undefined ? replacements[key] : '{' + key + '}'));
    }

    // افزونه ماژول ترجمه
    const faTranslateModule = {
        translate: ['value', bpmnTranslate],
    };

    // افزونه moddle برای namespace اختصاصی mms (هماهنگ با BpmnXmlParser)
    const mmsModdle = {
        name: 'MMS',
        uri: 'http://mms.local/bpmn',
        prefix: 'mms',
        xml: { tagAlias: 'lowerCase' },
        types: [
            { name: 'Assignee', superClass: ['Element'], properties: [{ name: 'value', isBody: true, type: 'String' }] },
            { name: 'CandidateUsers', superClass: ['Element'], properties: [{ name: 'value', isBody: true, type: 'String' }] },
            { name: 'CandidateGroups', superClass: ['Element'], properties: [{ name: 'value', isBody: true, type: 'String' }] },
            { name: 'DueDate', superClass: ['Element'], properties: [{ name: 'value', isBody: true, type: 'String' }] },
            { name: 'Priority', superClass: ['Element'], properties: [{ name: 'value', isBody: true, type: 'String' }] },
            { name: 'ServiceTaskClass', superClass: ['Element'], properties: [{ name: 'value', isBody: true, type: 'String' }] },
            { name: 'FormSchema', superClass: ['Element'], properties: [{ name: 'value', isBody: true, type: 'String' }] },
        ],
    };

    // نگاشت نام moddle به تگ XML
    const mmsTypeMap = {
        assignee: 'Assignee',
        candidateUsers: 'CandidateUsers',
        candidateGroups: 'CandidateGroups',
        dueDate: 'DueDate',
        priority: 'Priority',
        serviceTaskClass: 'ServiceTaskClass',
        formSchema: 'FormSchema',
    };

    const elementTypeLabels = {
        'bpmn:StartEvent': 'رویداد شروع',
        'bpmn:EndEvent': 'رویداد پایان',
        'bpmn:Task': 'وظیفه',
        'bpmn:UserTask': 'وظیفه کاربری',
        'bpmn:ServiceTask': 'وظیفه سرویس',
        'bpmn:ScriptTask': 'وظیفه اسکریپت',
        'bpmn:ExclusiveGateway': 'دروازه انحصاری',
        'bpmn:ParallelGateway': 'دروازه موازی',
        'bpmn:InclusiveGateway': 'دروازه شمولی',
        'bpmn:SequenceFlow': 'جریان توالی',
        'bpmn:SubProcess': 'زیرفرایند',
        'bpmn:IntermediateCatchEvent': 'رویداد میانی دریافت',
        'bpmn:IntermediateThrowEvent': 'رویداد میانی پرتاب',
        'bpmn:BoundaryEvent': 'رویداد مرزی',
    };

    const elementColors = {
        'bpmn:StartEvent': { fill: '#d1fae5', stroke: '#059669' },
        'bpmn:EndEvent': { fill: '#fee2e2', stroke: '#dc2626' },
        'bpmn:UserTask': { fill: '#dbeafe', stroke: '#2563eb' },
        'bpmn:ServiceTask': { fill: '#ede9fe', stroke: '#7c3aed' },
        'bpmn:ScriptTask': { fill: '#fef3c7', stroke: '#d97706' },
        'bpmn:ExclusiveGateway': { fill: '#ffedd5', stroke: '#ea580c' },
        'bpmn:ParallelGateway': { fill: '#ffedd5', stroke: '#ea580c' },
        'bpmn:InclusiveGateway': { fill: '#ffedd5', stroke: '#ea580c' },
    };

    function bpmnDesigner(serviceTasksJson) {
        return {
            modeler: null,
            serviceTasks: JSON.parse(serviceTasksJson || '[]'),
            selected: null,
            selectedTypeLabel: '',
            isUserTask: false,
            isServiceTask: false,
            form: { name: '', assignee: '', candidateGroups: '', dueDate: '', priority: '', serviceTaskClass: '' },

            initModeler() {
                this.modeler = new BpmnJS({
                    container: '#bpmn-modeler-canvas',
                    keyboard: { bindTo: document },
                    additionalModules: [faTranslateModule],
                    moddleExtensions: { mms: mmsModdle },
                });

                this.modeler.on('selection.changed', (e) => {
                    this.onSelectionChanged(e.newSelection);
                });
                this.modeler.on('element.changed', (e) => {
                    if (this.selected && e.element && e.element.id === this.selected.id) {
                        this.loadSelected(this.selected);
                    }
                });

                this.loadDefault();
            },

            loadDefault() {
                const defaultXml = `<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  xmlns:mms="http://mms.local/bpmn"
                  id="Definitions_1"
                  targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="true">
    <bpmn:startEvent id="StartEvent_1" name="شروع"/>
  </bpmn:process>
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="StartEvent_1_di" bpmnElement="StartEvent_1">
        <dc:Bounds x="180" y="100" width="36" height="36"/>
      </bpmndi:BPMNShape>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>`;
                this.modeler.importXML(defaultXml)
                    .then(() => { this.modeler.get('canvas').zoom('fit-viewport'); this.autoColor(); })
                    .catch(err => console.error('Import error:', err));
            },

            async importFile(event) {
                const file = event.target.files[0];
                if (!file) return;
                const text = await file.text();
                this.modeler.importXML(text)
                    .then(() => { this.modeler.get('canvas').zoom('fit-viewport'); this.autoColor(); })
                    .catch(err => alert('خطا در وارد کردن: ' + err.message));
            },

            async exportXml() {
                const { xml } = await this.modeler.saveXML({ format: true });
                const blob = new Blob([xml], { type: 'application/xml' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'process.bpmn';
                a.click();
                URL.revokeObjectURL(url);
            },

            async saveToForm() {
                try {
                    const { xml } = await this.modeler.saveXML({ format: true });
                    @this.set('data.bpmn_xml', xml);
                    alert('XML در فرم ذخیره شد. حالا «Deploy فرایند» را کلیک کنید.');
                } catch (err) {
                    alert('خطا در ذخیره: ' + err.message);
                }
            },

            // رنگ‌آمیزی خودکار عناصر بر اساس نوع
            autoColor() {
                const reg = this.modeler.get('elementRegistry');
                const modeling = this.modeler.get('modeling');
                reg.forEach((el) => {
                    const c = elementColors[el.type];
                    if (c) {
                        try { modeling.setColor(el, c); } catch (e) { /* noop */ }
                    }
                });
            },

            // افزودن یک ServiceTask از سایدبار
            addServiceTask(task) {
                try {
                    const elementFactory = this.modeler.get('elementFactory');
                    const modeling = this.modeler.get('modeling');
                    const canvas = this.modeler.get('canvas');
                    const root = canvas.getRootElement();

                    const shape = elementFactory.createShape({ type: 'bpmn:ServiceTask' });
                    const created = modeling.createShape(shape, { x: 300, y: 200 }, root);
                    modeling.updateProperties(created, { name: task.description || task.key });
                    this.writeMms(created, 'serviceTaskClass', task.key);
                    modeling.setColor(created, elementColors['bpmn:ServiceTask']);
                    this.modeler.get('selection').select(created);
                } catch (err) {
                    alert('خطا در افزودن وظیفه سرویس: ' + err.message);
                }
            },

            onSelectionChanged(selection) {
                if (!selection || selection.length !== 1) {
                    this.selected = null;
                    return;
                }
                this.selected = selection[0];
                this.loadSelected(this.selected);
            },

            loadSelected(element) {
                const bo = element.businessObject || {};
                this.selectedTypeLabel = elementTypeLabels[element.type] || element.type;
                this.isUserTask = element.type === 'bpmn:UserTask';
                this.isServiceTask = element.type === 'bpmn:ServiceTask';
                this.form = {
                    name: bo.name || '',
                    assignee: this.readMms(element, 'assignee'),
                    candidateGroups: this.readMms(element, 'candidateGroups'),
                    dueDate: this.readMms(element, 'dueDate'),
                    priority: this.readMms(element, 'priority'),
                    serviceTaskClass: this.readMms(element, 'serviceTaskClass'),
                };
            },

            applyName() {
                if (!this.selected) return;
                this.modeler.get('modeling').updateProperties(this.selected, { name: this.form.name });
            },

            applyMms(key, value) {
                if (!this.selected) return;
                this.writeMms(this.selected, key, value);
            },

            // خواندن مقدار یک extension element از نوع mms
            readMms(element, key) {
                const bo = element.businessObject;
                const ext = bo && bo.extensionElements;
                if (!ext || !ext.values) return '';
                const typeName = 'mms:' + mmsTypeMap[key];
                const node = ext.values.find((v) => v.$type === typeName);
                return node ? (node.value || '') : '';
            },

            // نوشتن/حذف یک extension element از نوع mms
            writeMms(element, key, value) {
                const moddle = this.modeler.get('moddle');
                const modeling = this.modeler.get('modeling');
                const bo = element.businessObject;
                const typeName = 'mms:' + mmsTypeMap[key];

                let ext = bo.extensionElements;
                if (!ext) {
                    ext = moddle.create('bpmn:ExtensionElements', { values: [] });
                    ext.$parent = bo;
                }
                const values = (ext.values || []).filter((v) => v.$type !== typeName);
                if (value !== undefined && value !== null && String(value).trim() !== '') {
                    const node = moddle.create(typeName, { value: String(value) });
                    node.$parent = ext;
                    values.push(node);
                }
                ext.values = values;
                modeling.updateProperties(element, { extensionElements: ext });
            },
        };
    }
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@13.0.0/dist/assets/diagram-js.css">
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@13.0.0/dist/assets/bpmn-js.css">
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@13.0.0/dist/assets/bpmn-font/css/bpmn.css">
@endpush

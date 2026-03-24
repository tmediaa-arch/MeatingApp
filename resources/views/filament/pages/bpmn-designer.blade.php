<x-filament-panels::page>
    <div class="space-y-4">
        {{ $this->form }}

        <div class="rounded-lg border border-gray-200 bg-white dark:bg-gray-900 p-2"
             wire:ignore
             x-data="bpmnDesigner()"
             x-init="initModeler()">

            <div class="flex gap-2 mb-2 p-2 bg-gray-50 dark:bg-gray-800 rounded">
                <button type="button" @click="loadDefault()"
                        class="px-3 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600">
                    🔄 شروع جدید
                </button>
                <label class="px-3 py-1 text-xs bg-gray-500 text-white rounded cursor-pointer hover:bg-gray-600">
                    📁 وارد کردن XML
                    <input type="file" accept=".bpmn,.xml" @change="importFile($event)" class="hidden">
                </label>
                <button type="button" @click="exportXml()"
                        class="px-3 py-1 text-xs bg-purple-500 text-white rounded hover:bg-purple-600">
                    📤 export XML
                </button>
                <button type="button" @click="saveToForm()"
                        class="px-3 py-1 text-xs bg-green-500 text-white rounded hover:bg-green-600">
                    💾 ذخیره در فرم
                </button>
            </div>

            <div id="bpmn-modeler-canvas" style="width: 100%; height: 650px; background: white;"></div>

            <div class="text-xs text-gray-500 mt-2 p-2 bg-gray-50 dark:bg-gray-800 rounded">
                💡 برای deploy واقعی، پس از طراحی روی «💾 ذخیره در فرم» کلیک کنید، سپس فرم را submit کنید.
            </div>
        </div>

        <div class="flex gap-2">
            <x-filament::button wire:click="save" color="success" size="lg">
                Deploy فرایند
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>

@push('scripts')
<script src="https://unpkg.com/bpmn-js@13.0.0/dist/bpmn-modeler.production.min.js"></script>
<script>
    function bpmnDesigner() {
        return {
            modeler: null,

            initModeler() {
                this.modeler = new BpmnJS({
                    container: '#bpmn-modeler-canvas',
                    keyboard: { bindTo: document },
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

                this.modeler.importXML(defaultXml).then(() => {
                    this.modeler.get('canvas').zoom('fit-viewport');
                }).catch(err => console.error('Import error:', err));
            },

            async importFile(event) {
                const file = event.target.files[0];
                if (!file) return;
                const text = await file.text();
                this.modeler.importXML(text).then(() => {
                    this.modeler.get('canvas').zoom('fit-viewport');
                }).catch(err => alert('خطا در import: ' + err.message));
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
                    // به Livewire تزریق کن
                    @this.set('data.bpmn_xml', xml);
                    alert('XML در فرم ذخیره شد. حالا «Deploy فرایند» را کلیک کنید.');
                } catch (err) {
                    alert('خطا در ذخیره: ' + err.message);
                }
            }
        }
    }
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@13.0.0/dist/assets/diagram-js.css">
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@13.0.0/dist/assets/bpmn-js.css">
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@13.0.0/dist/assets/bpmn-font/css/bpmn.css">
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@13.0.0/dist/assets/bpmn-js-properties-panel.css">
@endpush

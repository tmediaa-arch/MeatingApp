{{-- نمایش‌گر BPMN از طریق bpmn-js (نسخه CDN) --}}
<div
    x-data="bpmnViewer()"
    x-init="initViewer()"
    wire:ignore
    class="bpmn-viewer-container"
>
    <div
        id="bpmn-viewer-canvas-{{ $getRecord()->id }}"
        style="width: 100%; height: 500px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb;"
    ></div>
    <p class="text-xs text-gray-500 mt-2">
        نمای گرافیکی BPMN. می‌توانید با scroll زوم کنید و با drag حرکت دهید.
    </p>
</div>

@push('scripts')
<script src="https://unpkg.com/bpmn-js@13.0.0/dist/bpmn-navigated-viewer.production.min.js"></script>
<script>
    function bpmnViewer() {
        return {
            viewer: null,
            initViewer() {
                const xml = @json($getRecord()->bpmn_xml);
                const containerId = 'bpmn-viewer-canvas-{{ $getRecord()->id }}';

                this.viewer = new BpmnJS({ container: '#' + containerId });

                this.viewer.importXML(xml).then(() => {
                    const canvas = this.viewer.get('canvas');
                    canvas.zoom('fit-viewport');
                }).catch(err => {
                    console.error('BPMN import error:', err);
                    document.getElementById(containerId).innerHTML =
                        '<div class="p-4 text-red-600">خطا در نمایش BPMN: ' + err.message + '</div>';
                });
            }
        }
    }
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@13.0.0/dist/assets/diagram-js.css">
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@13.0.0/dist/assets/bpmn-js.css">
<link rel="stylesheet" href="https://unpkg.com/bpmn-js@13.0.0/dist/assets/bpmn-font/css/bpmn.css">
@endpush

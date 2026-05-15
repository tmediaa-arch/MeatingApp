{{-- نمایش‌گر BPMN از طریق bpmn-js (نسخه CDN) --}}
{{-- اگر BPMN فاقد بخش DI (طرح‌بندی گرافیکی) باشد، با bpmn-auto-layout طرح‌بندی تولید می‌شود. --}}
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

            async initViewer() {
                const xml = @json($getRecord()->bpmn_xml);
                const containerId = 'bpmn-viewer-canvas-{{ $getRecord()->id }}';
                const el = document.getElementById(containerId);

                if (! xml || ! xml.trim()) {
                    el.innerHTML = '<div class="p-4 text-gray-500">BPMN XML برای این فرایند ثبت نشده است.</div>';
                    return;
                }

                this.viewer = new BpmnJS({ container: '#' + containerId });

                try {
                    await this.render(xml);
                } catch (err) {
                    // معمولاً به دلیل نبود بخش DI: «no diagram to display».
                    // طرح‌بندی را به‌صورت خودکار تولید و دوباره تلاش می‌کنیم.
                    try {
                        const { layoutProcess } = await import('https://esm.sh/bpmn-auto-layout@0.4.0');
                        const laidOutXml = await layoutProcess(xml);
                        await this.render(laidOutXml);
                    } catch (layoutErr) {
                        console.error('BPMN import error:', err, layoutErr);
                        el.innerHTML = '<div class="p-4 text-red-600">خطا در نمایش BPMN: '
                            + (layoutErr && layoutErr.message ? layoutErr.message : err.message) + '</div>';
                    }
                }
            },

            async render(xml) {
                await this.viewer.importXML(xml);
                this.viewer.get('canvas').zoom('fit-viewport');
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

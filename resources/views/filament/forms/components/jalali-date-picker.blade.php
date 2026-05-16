@php
    $statePath         = $getStatePath();
    $config            = $getJsConfig();
    $isRange           = $isRange();
    $useFilamentAssets = $getUseFilamentAssets();
    $isDisabled        = $isDisabled();
    $placeholderText   = $getPlaceholder() ?? __('تاریخ را انتخاب کنید');
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    {{-- Load assets once per page (sentinel via @once) --}}
    @once
       
        <link rel="stylesheet" href="{{ asset('/assets/front/css/jalalidatepicker.min.css') }}">
        <script defer src="{{ asset('/assets/front/js/jalalidatepicker.min.js') }}"></script>
        <script defer src="{{ asset('/assets/front/js/jalali-script.js') }}"></script>
    @endonce

    <div
        wire:ignore
        x-data="jalaliDatePickerComponent({
            statePath: @js($statePath),
            isRange: @js($isRange),
            config: @js($config),
        })"
        x-init="init()"
        class="jdp-wrapper"
        dir="rtl"
    >
        @if ($isRange)
            <div class="jdp-range-group">
                <input
                    type="text"
                    x-ref="start"
                    x-model="startValue"
                    placeholder="{{ __('از تاریخ ...') }}"
                    data-jdp
                    autocomplete="off"
                    readonly
                    @disabled($isDisabled)
                    class="fi-input block w-full rounded-lg border-gray-300 bg-white shadow-sm
                           transition duration-75 focus:border-primary-500 focus:ring-1
                           focus:ring-inset focus:ring-primary-500 dark:border-white/10
                           dark:bg-white/5 dark:text-white"
                />
                <span class="jdp-range-sep">—</span>
                <input
                    type="text"
                    x-ref="end"
                    x-model="endValue"
                    placeholder="{{ __('تا تاریخ ...') }}"
                    data-jdp
                    autocomplete="off"
                    readonly
                    @disabled($isDisabled)
                    class="fi-input block w-full rounded-lg border-gray-300 bg-white shadow-sm
                           transition duration-75 focus:border-primary-500 focus:ring-1
                           focus:ring-inset focus:ring-primary-500 dark:border-white/10
                           dark:bg-white/5 dark:text-white"
                />
            </div>
        @else
            <input
                type="text"
                x-ref="single"
                x-model="singleValue"
                placeholder="{{ $placeholderText }}"
                data-jdp
                autocomplete="off"
                readonly
                @disabled($isDisabled)
                class="fi-input block w-full rounded-lg border-gray-300 bg-white shadow-sm
                       transition duration-75 focus:border-primary-500 focus:ring-1
                       focus:ring-inset focus:ring-primary-500 dark:border-white/10
                       dark:bg-white/5 dark:text-white"
            />
        @endif
    </div>
</x-dynamic-component>

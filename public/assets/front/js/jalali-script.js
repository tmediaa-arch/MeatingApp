/* ============================================================================
 * Jalali Date Picker - Filament wrapper for majidh1/JalaliDatePicker
 * ----------------------------------------------------------------------------
 * Registers a single Alpine component that:
 *   1. Initialises the picker on mount
 *   2. Two-way binds with Livewire state via $wire
 *   3. For range mode, chains min/max between the two inputs
 *   4. Tolerates wire:navigate, Livewire morphs, and Filament repeaters
 *
 * Fix: defensive Alpine registration that works whether Alpine is already
 * initialised (event missed), about to fire alpine:init, or not yet loaded.
 * ==========================================================================*/
(function () {
    'use strict';

    function ensureLibLoaded(cb) {
        if (window.jalaliDatepicker) return cb();
        const start = Date.now();
        const t = setInterval(() => {
            if (window.jalaliDatepicker) {
                clearInterval(t);
                cb();
            } else if (Date.now() - start > 8000) {
                clearInterval(t);
                console.error('[JDP] jalaliDatepicker library failed to load within 8s');
            }
        }, 50);
    }

    function buildDayRendering(cfg) {
        const disabled = new Set((cfg.disabledDates || []).map(String));
        const holidays = new Set((cfg.holidays || []).map(String));

        return function (dayOptions) {
            const key =
                dayOptions.year + '/' +
                String(dayOptions.month).padStart(2, '0') + '/' +
                String(dayOptions.day).padStart(2, '0');

            const out = {};
            if (disabled.has(key)) out.isValid = false;
            if (holidays.has(key)) out.isHollyDay = true;
            return out;
        };
    }

    function libOptions(cfg) {
        return {
            date: cfg.date,
            time: cfg.time,
            hasSecond: cfg.hasSecond,
            persianDigit: cfg.persianDigits,
            autoHide: cfg.autoHide,
            hideAfterChange: cfg.hideAfterChange,
            topSpace: cfg.topSpace,
            // minDate and maxDate removed - set via data-jdp-* attributes on input
            useDropDownYears: true,
            dayRendering: buildDayRendering(cfg),
        };
    }

    function registerComponent(Alpine) {
        // Guard against double-registration on wire:navigate
        if (Alpine.__jdpRegistered) return;
        Alpine.__jdpRegistered = true;

        Alpine.data('jalaliDatePickerComponent', ({ statePath, isRange, config }) => ({
            statePath: statePath,
            config: config,
            isRange: isRange,

            singleValue: '',
            startValue: '',
            endValue: '',

            init() {
                // Defer first hydration so Livewire entangle is fully wired
                queueMicrotask(() => this.hydrateFromState(this.entangledState()));
                this.$watch(() => this.entangledState(), (v) => this.hydrateFromState(v));

                ensureLibLoaded(() => this.bindPickers());

                document.addEventListener('livewire:navigated', () => {
                    ensureLibLoaded(() => this.bindPickers());
                });
                window.addEventListener('jdp:rebind', () => {
                    ensureLibLoaded(() => this.bindPickers());
                });
            },

            entangledState() {
                try {
                    return this.$wire.get(this.statePath);
                } catch (e) {
                    return this.isRange ? { start: null, end: null } : null;
                }
            },

            hydrateFromState(v) {
                if (this.isRange) {
                    v = v || {};
                    this.startValue = v.start || '';
                    this.endValue = v.end || '';
                } else {
                    this.singleValue = v || '';
                }
            },

            pushState() {
                if (this.isRange) {
                    this.$wire.set(
                        this.statePath,
                        { start: this.startValue || null, end: this.endValue || null },
                        false
                    );
                } else {
                    this.$wire.set(this.statePath, this.singleValue || null, false);
                }
            },

            bindPickers() {
                if (this.isRange) this.bindRange();
                else this.bindSingle();
            },

            bindSingle() {
                const input = this.$refs.single;
                if (!input) return;

                if (this.config.minDate) input.setAttribute('data-jdp-min-date', this.config.minDate);
                if (this.config.maxDate) input.setAttribute('data-jdp-max-date', this.config.maxDate);
                if (this.config.mode === 'time') input.setAttribute('data-jdp-only-time', '');
                if (this.config.mode === 'date') input.setAttribute('data-jdp-only-date', '');

                window.jalaliDatepicker.startWatch(libOptions(this.config));

                input.addEventListener('change', () => {
                    this.singleValue = input.value;
                    this.pushState();
                });

            },

            bindRange() {
                const s = this.$refs.start;
                const e = this.$refs.end;
                if (!s || !e) return;

                if (this.config.minDate) s.setAttribute('data-jdp-min-date', this.config.minDate);
                if (this.config.maxDate) e.setAttribute('data-jdp-max-date', this.config.maxDate);

                window.jalaliDatepicker.startWatch(libOptions(this.config));

                const update = () => window.jalaliDatepicker.updateOptions(libOptions(this.config));

                s.addEventListener('change', () => {
                    this.startValue = s.value;
                    if (s.value) {
                        e.setAttribute('data-jdp-min-date', s.value);
                        if (this.endValue && this.endValue < s.value) {
                            this.endValue = '';
                            e.value = '';
                        }
                    } else {
                        e.removeAttribute('data-jdp-min-date');
                    }
                    update();
                    this.pushState();
                });

                e.addEventListener('change', () => {
                    this.endValue = e.value;
                    if (e.value) {
                        s.setAttribute('data-jdp-max-date', e.value);
                    } else {
                        s.removeAttribute('data-jdp-max-date');
                    }
                    update();
                    this.pushState();
                });
            },
        }));
    }

    // ── Defensive registration ────────────────────────────────────────────
    // Handles 3 cases:
    //   1. Alpine not loaded yet  → wait for 'alpine:init'
    //   2. Alpine just loaded     → 'alpine:init' will fire shortly
    //   3. Alpine already running → register immediately (event already passed)
    function tryRegister() {
        if (window.Alpine && typeof window.Alpine.data === 'function') {
            registerComponent(window.Alpine);
            return true;
        }
        return false;
    }

    if (!tryRegister()) {
        document.addEventListener('alpine:init', tryRegister);

        // Belt-and-suspenders: poll briefly in case alpine:init already fired
        // between the library loading and our script executing.
        let attempts = 0;
        const poll = setInterval(() => {
            if (tryRegister() || ++attempts > 40) {
                clearInterval(poll);
            }
        }, 50);
    }
})();

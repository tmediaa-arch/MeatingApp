<?php

namespace App\Filament\Forms\Components;

use Closure;
use Carbon\Carbon;
use Filament\Forms\Components\Field;
use Morilog\Jalali\Jalalian;
use Morilog\Jalali\CalendarUtils;

/**
 * JalaliDatePicker - Custom Filament form field wrapping majidh1/JalaliDatePicker.
 *
 * Supports: single date, range, date+time, time-only, month/year picker,
 * min/max constraints, disabled dates, holidays, Persian digits, and
 * Jalali <-> Gregorian conversion on the server side via morilog/jalali.
 *
 * Default storage: Gregorian (Y-m-d) in DB, Jalali (Y/m/d) in the UI.
 *
 * Fixes applied:
 *   - placeholder() defined locally (Filament v4 base Field no longer ships it)
 *   - formatStateUsing / dehydrateStateUsing wrapped via Closure::fromCallable
 *   - view path matches resources/views/filament/forms/components/
 */
class JalaliDatePicker extends Field
{
    /** Set to 4 if you are on Filament v4 (only affects future JS-call entrypoints). */
    public const FILAMENT_VERSION = 4;

    protected string $view = 'filament.forms.components.jalali-date-picker';

    // --- Mode ---------------------------------------------------------------
    protected string $mode = 'date';            // date | datetime | time | monthPicker | yearPicker
    protected bool   $isRange = false;

    // --- Constraints --------------------------------------------------------
    protected mixed $minDate = null;            // Carbon|string|Closure|null
    protected mixed $maxDate = null;
    protected array $disabledDates = [];
    protected array $holidays = [];
    protected bool  $disableBeforeToday = false;
    protected bool  $disableAfterToday  = false;

    // --- Display ------------------------------------------------------------
    protected ?string $placeholderText = null;   // local placeholder (HasPlaceholder no longer in v4 base)
    protected bool    $persianDigits   = false;
    protected string  $format          = 'Y-m-d';   // storage format (Gregorian default)
    protected string  $displayFormat   = 'Y/m/d';   // Jalali UI format
    protected bool    $autoClose       = true;
    protected int     $topSpace        = 0;

    // --- Time picker --------------------------------------------------------
    protected bool $showHour     = true;
    protected bool $showMinute   = true;
    protected bool $showSecond   = false;
    protected bool $showMeridian = false;
    protected int  $hourStep     = 1;
    protected int  $minuteStep   = 1;
    protected int  $secondStep   = 1;

    // --- Storage ------------------------------------------------------------
    protected bool $storeAsGregorian = true;

    // --- Asset strategy -----------------------------------------------------
    protected bool $useFilamentAssets = false;

    // =======================================================================
    // Boot - wire state hydration / dehydration via morilog/jalali
    // =======================================================================
    protected function setUp(): void
    {
        parent::setUp();

        $this->formatStateUsing(\Closure::fromCallable([$this, 'hydrateStateForDisplay']));
        $this->dehydrateStateUsing(\Closure::fromCallable([$this, 'normalizeStateForStorage']));
    }

    /**
     * Jalali-aware "after" validation. Pass 'today' or a Carbon/string Gregorian date.
     */
    public function jalaliAfter(mixed $date = 'today'): static
    {
        $this->rules([
            function () use ($date) {
                return function (string $attribute, $value, \Closure $fail) use ($date) {
                    if (! $value) return;

                    $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                    $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
                    $value = str_replace([...$fa, ...$ar], range(0, 9), (string) $value);

                    $datePart = explode(' ', trim($value))[0];

                    try {
                        $carbon = \Morilog\Jalali\CalendarUtils::createCarbonFromFormat('Y/m/d', $datePart);
                        if (! $carbon) {
                            $fail(__('فرمت تاریخ نامعتبر است.'));
                            return;
                        }

                        $boundary = $date === 'today'
                            ? now()->startOfDay()
                            : \Carbon\Carbon::parse($date);

                        if (! $carbon->isAfter($boundary)) {
                            $fail(__(':attribute باید بعد از :date باشد.', [
                                'attribute' => $this->getLabel(),
                                'date'      => \Morilog\Jalali\Jalalian::fromCarbon($boundary)->format('Y/m/d'),
                            ]));
                        }
                    } catch (\Throwable) {
                        $fail(__('فرمت تاریخ نامعتبر است.'));
                    }
                };
            },
        ]);

        return $this;
    }

    public function getValidationAttribute(): string
    {
        return $this->getLabel() ?? parent::getValidationAttribute();
    }

    /**
     * Convert Jalali state to Gregorian before validation rules see it.
     */
    protected function getStateForValidation(): mixed
    {
        $state = $this->getState();
        return $this->normalizeStateForStorage($state);
    }
    /**
     * Convert the database-stored value into what the JS picker should display.
     * Filament calls this when the form loads and on every re-hydration.
     */
    protected function hydrateStateForDisplay($state)
    {
        if ($state === null || $state === '' || $state === []) {
            return $this->isRange ? ['start' => null, 'end' => null] : null;
        }

        if ($this->isRange) {
            $state = is_string($state) ? (json_decode($state, true) ?: []) : (array) $state;
            return [
                'start' => $this->toJalaliDisplay($state['start'] ?? null),
                'end'   => $this->toJalaliDisplay($state['end']   ?? null),
            ];
        }

        return $this->toJalaliDisplay($state);
    }

    /**
     * Convert the UI-entered Jalali value back into what should be stored in the DB.
     * Filament calls this right before saving the record.
     */
    protected function normalizeStateForStorage($state)
    {
        if ($state === null || $state === '' || $state === []) {
            return null;
        }

        if ($this->isRange) {
            $state = (array) $state;
            return [
                'start' => $this->fromJalaliInput($state['start'] ?? null),
                'end'   => $this->fromJalaliInput($state['end']   ?? null),
            ];
        }

        return $this->fromJalaliInput($state);
    }

    // =======================================================================
    // Placeholder (local impl - v4 base Field no longer ships HasPlaceholder)
    // =======================================================================
    public function placeholder(?string $text): static
    {
        $this->placeholderText = $text;
        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholderText;
    }

    // =======================================================================
    // Fluent setters - mode
    // =======================================================================
    public function dateTime(): static
    {
        $this->mode = 'datetime';
        $this->format = 'Y-m-d H:i:s';
        $this->displayFormat = 'Y/m/d H:i:s';
        return $this;
    }

    public function time(): static
    {
        $this->mode = 'time';
        $this->format = 'H:i:s';
        $this->displayFormat = 'H:i:s';
        return $this;
    }


    public function monthPicker(): static
    {
        $this->mode = 'monthPicker';
        return $this;
    }
    public function yearPicker(): static
    {
        $this->mode = 'yearPicker';
        return $this;
    }
    public function range(bool $on = true): static
    {
        $this->isRange = $on;
        return $this;
    }

    // =======================================================================
    // Fluent setters - constraints
    // =======================================================================
    public function minDate(mixed $d): static
    {
        $this->minDate = $d;
        return $this;
    }
    public function maxDate(mixed $d): static
    {
        $this->maxDate = $d;
        return $this;
    }
    public function disabledDates(array $d): static
    {
        $this->disabledDates = $d;
        return $this;
    }
    public function holidays(array $d): static
    {
        $this->holidays = $d;
        return $this;
    }
    public function disableBeforeToday(bool $on = true): static
    {
        $this->disableBeforeToday = $on;
        return $this;
    }
    public function disableAfterToday(bool $on = true): static
    {
        $this->disableAfterToday  = $on;
        return $this;
    }

    // =======================================================================
    // Fluent setters - display
    // =======================================================================
    public function persianDigits(bool $on = true): static
    {
        $this->persianDigits = $on;
        return $this;
    }
    public function format(string $f): static
    {
        $this->format = $f;
        return $this;
    }
    public function displayFormat(string $f): static
    {
        $this->displayFormat = $f;
        return $this;
    }
    public function autoClose(bool $on = true): static
    {
        $this->autoClose = $on;
        return $this;
    }
    public function topSpace(int $px): static
    {
        $this->topSpace  = $px;
        return $this;
    }

    // =======================================================================
    // Fluent setters - time picker
    // =======================================================================
    public function showHour(bool $on = true): static
    {
        $this->showHour     = $on;
        return $this;
    }
    public function showMinute(bool $on = true): static
    {
        $this->showMinute   = $on;
        return $this;
    }
    public function showSecond(bool $on = true): static
    {
        $this->showSecond   = $on;
        return $this;
    }
    public function showMeridian(bool $on = true): static
    {
        $this->showMeridian = $on;
        return $this;
    }
    public function hourStep(int $s): static
    {
        $this->hourStep   = $s;
        return $this;
    }
    public function minuteStep(int $s): static
    {
        $this->minuteStep = $s;
        return $this;
    }
    public function secondStep(int $s): static
    {
        $this->secondStep = $s;
        return $this;
    }

    // =======================================================================
    // Fluent setters - storage / assets
    // =======================================================================
    public function storeAsGregorian(bool $on = true): static
    {
        $this->storeAsGregorian = $on;
        return $this;
    }
    public function storeAsJalali(): static
    {
        $this->storeAsGregorian = false;
        return $this;
    }
    public function useFilamentAssets(bool $on = true): static
    {
        $this->useFilamentAssets = $on;
        return $this;
    }

    // =======================================================================
    // Getters used by the Blade view ($getXxx())
    // =======================================================================
    public function getMode(): string
    {
        return $this->mode;
    }
    public function isRange(): bool
    {
        return $this->isRange;
    }
    public function getDisplayFormat(): string
    {
        return $this->displayFormat;
    }
    public function getStoreAsGregorian(): bool
    {
        return $this->storeAsGregorian;
    }
    public function getUseFilamentAssets(): bool
    {
        return $this->useFilamentAssets;
    }
    public function getFilamentVersion(): int
    {
        return static::FILAMENT_VERSION;
    }

    /**
     * Build the JSON config passed to the Alpine component.
     */
    public function getJsConfig(): array
    {
        $min = $this->resolveBoundary($this->minDate);
        $max = $this->resolveBoundary($this->maxDate);

        if ($this->disableBeforeToday) {
            $min = $min ?: Jalalian::now()->format('Y/m/d');
        }
        if ($this->disableAfterToday) {
            $max = $max ?: Jalalian::now()->format('Y/m/d');
        }

        return [
            'mode'            => $this->mode,
            'isRange'         => $this->isRange,
            'persianDigits'   => $this->persianDigits,
            'autoHide'        => $this->autoClose,   // library handles outside-click close
            'hideAfterChange' => $this->autoClose && $this->mode === 'date',  // only auto-close on day pick for date-only mode
            'topSpace'        => $this->topSpace,
            'minDate'         => $min,
            'maxDate'         => $max,
            'disabledDates'   => array_values(array_filter(array_map(fn($d) => $this->normaliseToJalali($d), $this->disabledDates))),
            'holidays'        => array_values(array_filter(array_map(fn($d) => $this->normaliseToJalali($d), $this->holidays))),
            'time'            => in_array($this->mode, ['datetime', 'time'], true),
            'date'            => $this->mode !== 'time',
            'hasSecond'       => $this->showSecond,
            'showHour'        => $this->showHour,
            'showMinute'      => $this->showMinute,
            'showMeridian'    => $this->showMeridian,
            'hourStep'        => $this->hourStep,
            'minuteStep'      => $this->minuteStep,
            'secondStep'      => $this->secondStep,
            'displayFormat'   => $this->displayFormat,
        ];
    }

    // =======================================================================
    // Conversion helpers (morilog/jalali)
    // =======================================================================
    protected function toJalaliDisplay(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        try {
            if (! $this->storeAsGregorian) {
                return (string) $value;
            }

            $tz = config('app.timezone', 'UTC');

            $carbon = $value instanceof Carbon
                ? $value->copy()
                : Carbon::parse((string) $value);

            // Always shift to app timezone (works whether source is UTC, +03:30, or naive)
            $carbon->setTimezone($tz);

            return Jalalian::fromCarbon($carbon)->format($this->displayFormat);
        } catch (\Throwable) {
            return null;
        }
    }
    protected function fromJalaliInput(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $value = $this->stripPersianDigits((string) $value);

        try {
            if (! $this->storeAsGregorian) {
                return $value;
            }

            $hasTime  = str_contains($value, ' ');
            $datePart = $hasTime ? explode(' ', $value)[0] : $value;
            $timePart = $hasTime ? explode(' ', $value, 2)[1] : '00:00:00';

            $carbon = CalendarUtils::createCarbonFromFormat('Y/m/d', $datePart);
            if (! $carbon) return null;

            [$h, $i, $s] = array_pad(explode(':', $timePart), 3, '0');

            // Build datetime in app timezone with the exact wall-clock the user picked.
            return Carbon::create(
                $carbon->year,
                $carbon->month,
                $carbon->day,
                (int) $h,
                (int) $i,
                (int) $s,
                config('app.timezone', 'UTC')
            )->format($this->format);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveBoundary(mixed $value): ?string
    {
        $value = $this->evaluate($value);
        if ($value === null) return null;
        return $this->normaliseToJalali($value);
    }

    protected function normaliseToJalali(mixed $value): ?string
    {
        if ($value === null) return null;
        if (is_string($value) && preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $this->stripPersianDigits($value))) {
            return $this->stripPersianDigits($value);
        }

        try {
            $carbon = $value instanceof Carbon ? $value : Carbon::parse((string) $value);
            return Jalalian::fromCarbon($carbon)->format('Y/m/d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function stripPersianDigits(string $s): string
    {
        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        return str_replace([...$fa, ...$ar], range(0, 9), $s);
    }
}

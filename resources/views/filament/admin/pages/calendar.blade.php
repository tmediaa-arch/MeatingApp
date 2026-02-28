<x-filament-panels::page>
    <div class="space-y-4">
        <div class="fi-section bg-white dark:bg-gray-900 rounded-xl shadow ring-1 ring-gray-950/5 dark:ring-white/10 p-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">
                    تقویم — {{ \Morilog\Jalali\Jalalian::fromCarbon(now())->format('F Y') }}
                </h2>
                <div class="flex gap-2 text-xs">
                    @foreach([
                        'پیش‌نویس' => '#9ca3af',
                        'برنامه‌ریزی' => '#3b82f6',
                        'دعوت‌ها' => '#06b6d4',
                        'در حال برگزاری' => '#10b981',
                        'متوقف' => '#f59e0b',
                        'برگزار شد' => '#6b7280',
                        'لغو شد' => '#ef4444',
                    ] as $label => $color)
                        <div class="flex items-center gap-1">
                            <span class="w-3 h-3 rounded" style="background: {{ $color }}"></span>
                            <span>{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div id="meetings-calendar" wire:ignore></div>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
        <style>
            #meetings-calendar {
                direction: rtl;
                font-family: inherit;
            }
            #meetings-calendar .fc-header-toolbar {
                margin-bottom: 1rem;
            }
            #meetings-calendar .fc-button-primary {
                background-color: #4f46e5;
                border-color: #4f46e5;
            }
            #meetings-calendar .fc-event {
                cursor: pointer;
                padding: 2px 4px;
                font-size: 0.875rem;
            }
            .fc-event-popup {
                position: absolute;
                background: white;
                padding: 1rem;
                border-radius: 0.5rem;
                box-shadow: 0 10px 25px rgba(0,0,0,0.15);
                z-index: 100;
                min-width: 280px;
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
        <script>
            document.addEventListener('livewire:initialized', () => {
                const calendarEl = document.getElementById('meetings-calendar');

                // تبدیل اعداد به فارسی
                const toFarsiDigits = (str) => {
                    const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                    return String(str).replace(/[0-9]/g, d => fa[d]);
                };

                // نام ماه‌های شمسی
                const jalaliMonths = [
                    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
                ];

                // تبدیل میلادی به شمسی ساده (Gregorian → Jalali)
                // پیاده‌سازی fast بدون نیاز به package خارجی
                const gregorianToJalali = (gy, gm, gd) => {
                    const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
                    const gy2 = (gm > 2) ? (gy + 1) : gy;
                    let days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4)
                        - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400)
                        + gd + g_d_m[gm - 1];
                    let jy = -1595 + (33 * Math.floor(days / 12053));
                    days %= 12053;
                    jy += 4 * Math.floor(days / 1461);
                    days %= 1461;
                    if (days > 365) {
                        jy += Math.floor((days - 1) / 365);
                        days = (days - 1) % 365;
                    }
                    let jm, jd;
                    if (days < 186) {
                        jm = 1 + Math.floor(days / 31);
                        jd = 1 + (days % 31);
                    } else {
                        jm = 7 + Math.floor((days - 186) / 30);
                        jd = 1 + ((days - 186) % 30);
                    }
                    return [jy, jm, jd];
                };

                const calendar = new FullCalendar.Calendar(calendarEl, {
                    direction: 'rtl',
                    locale: 'fa',
                    firstDay: 6, // شنبه ابتدای هفته
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        right: 'prev,next today',
                        center: 'title',
                        left: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    buttonText: {
                        today: 'امروز',
                        month: 'ماه',
                        week: 'هفته',
                        day: 'روز',
                        list: 'فهرست'
                    },
                    allDayText: 'تمام روز',
                    moreLinkText: 'مورد دیگر',
                    noEventsText: 'رویدادی موجود نیست',
                    height: 'auto',
                    nowIndicator: true,

                    // تبدیل تاریخ‌های نمایشی به شمسی
                    titleFormat: (info) => {
                        const date = new Date(info.date.marker);
                        const [jy, jm] = gregorianToJalali(
                            date.getFullYear(),
                            date.getMonth() + 1,
                            date.getDate()
                        );
                        return jalaliMonths[jm - 1] + ' ' + toFarsiDigits(jy);
                    },

                    dayHeaderFormat: { weekday: 'long' },

                    // ستون‌ها به شمسی
                    dayCellContent: (info) => {
                        const date = info.date;
                        const [, , jd] = gregorianToJalali(
                            date.getFullYear(),
                            date.getMonth() + 1,
                            date.getDate()
                        );
                        return { html: '<div class="fc-daygrid-day-number">' + toFarsiDigits(jd) + '</div>' };
                    },

                    // بارگذاری رویدادها از سرور
                    events: async (info, successCallback, failureCallback) => {
                        try {
                            const response = await @this.call(
                                'getEvents',
                                info.startStr,
                                info.endStr
                            );
                            successCallback(response);
                        } catch (e) {
                            failureCallback(e);
                        }
                    },

                    // کلیک روی رویداد → باز کردن صفحه view جلسه
                    eventClick: (info) => {
                        info.jsEvent.preventDefault();
                        if (info.event.extendedProps.url) {
                            window.location.href = info.event.extendedProps.url;
                        }
                    },

                    // drag&drop رویداد برای تغییر زمان
                    editable: true,
                    eventDrop: async (info) => {
                        const result = await @this.call(
                            'rescheduleMeeting',
                            info.event.id,
                            info.event.start.toISOString(),
                            info.event.end ? info.event.end.toISOString() : info.event.start.toISOString()
                        );

                        if (!result.success) {
                            alert(result.message);
                            info.revert();
                        }
                    },
                    eventResize: async (info) => {
                        const result = await @this.call(
                            'rescheduleMeeting',
                            info.event.id,
                            info.event.start.toISOString(),
                            info.event.end.toISOString()
                        );

                        if (!result.success) {
                            alert(result.message);
                            info.revert();
                        }
                    },
                });

                calendar.render();
            });
        </script>
    @endpush
</x-filament-panels::page>

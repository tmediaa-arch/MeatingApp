<?php

declare(strict_types=1);

namespace App\Domains\Rooms\Services;

use App\Domains\Calendar\ValueObjects\TimeRange;
use App\Domains\Rooms\Enums\ReservationStatus;
use App\Domains\Rooms\Models\Room;
use App\Domains\Rooms\Models\RoomReservation;
use Illuminate\Support\Collection;

/**
 * تشخیص تداخل رزرو سالن.
 *
 * این Service core logic رزرو سالن است:
 * - بررسی تداخل با رزروهای موجود (با احتساب buffer)
 * - بررسی ساعت کاری سالن
 * - بررسی محدودیت‌های زمانی (min/max booking, advance)
 *
 * مهم: این Service ATOMIC نیست — برای جلوگیری از race condition،
 * Action صدا زننده باید از DB::transaction + DB::lockForUpdate استفاده کند.
 */
class RoomConflictDetectionService
{
    /**
     * نتیجه validation — اگر valid باشد errors خالی است
     *
     * @return array{valid: bool, errors: array<string>, conflicts: Collection<RoomReservation>}
     */
    public function validateReservation(
        Room $room,
        TimeRange $requested,
        ?RoomReservation $excludeReservation = null,
    ): array {
        $errors = [];
        $conflicts = collect();

        // 1. بررسی وضعیت سالن
        if (!$room->status->isBookable()) {
            $errors[] = "سالن '{$room->name}' در حال حاضر قابل رزرو نیست (وضعیت: {$room->status->label()}).";
            return ['valid' => false, 'errors' => $errors, 'conflicts' => $conflicts];
        }

        // 2. بازه باید در آینده باشد
        if ($requested->isInPast()) {
            $errors[] = 'رزرو در گذشته مجاز نیست.';
        }

        // 3. حداقل و حداکثر مدت
        $duration = $requested->durationInMinutes();
        if ($duration < $room->min_booking_minutes) {
            $errors[] = "حداقل مدت رزرو این سالن {$room->min_booking_minutes} دقیقه است.";
        }
        if ($duration > $room->max_booking_minutes) {
            $errors[] = "حداکثر مدت رزرو این سالن {$room->max_booking_minutes} دقیقه است.";
        }

        // 4. حداکثر روز پیش‌رزرو
        $daysAhead = now()->diffInDays($requested->start, false);
        if ($daysAhead > $room->advance_booking_days) {
            $errors[] = "حداکثر {$room->advance_booking_days} روز قبل می‌توان رزرو کرد.";
        }

        // 5. ساعت کاری
        if (!$room->isInWorkingHours($requested->start, $requested->end)) {
            $errors[] = 'این زمان خارج از ساعت کاری سالن است.';
        }

        // 6. تداخل با رزروهای موجود (با احتساب buffer)
        $expanded = $requested->expand(
            $room->buffer_before_minutes,
            $room->buffer_after_minutes,
        );

        $conflicts = $this->findConflictingReservations($room, $expanded, $excludeReservation);

        if ($conflicts->isNotEmpty()) {
            foreach ($conflicts as $conflict) {
                $errors[] = sprintf(
                    'تداخل با رزرو موجود: از %s تا %s (وضعیت: %s)',
                    $conflict->reserved_from->format('Y/m/d H:i'),
                    $conflict->reserved_until->format('Y/m/d H:i'),
                    $conflict->status->label(),
                );
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * پیدا کردن رزروهای تداخلی
     */
    public function findConflictingReservations(
        Room $room,
        TimeRange $range,
        ?RoomReservation $exclude = null,
    ): Collection {
        $query = RoomReservation::query()
            ->where('room_id', $room->id)
            ->whereIn('status', [
                ReservationStatus::Pending,
                ReservationStatus::Approved,
            ])
            ->overlapping($range->start, $range->end);

        if ($exclude) {
            $query->where('id', '!=', $exclude->id);
        }

        return $query->get();
    }

    /**
     * بازه‌های آزاد سالن در یک روز
     * مفید برای نمایش suggestion به کاربر
     *
     * @return array<TimeRange>
     */
    public function findAvailableSlots(
        Room $room,
        \DateTimeInterface $forDay,
        int $minSlotMinutes = 30,
    ): array {
        $dayStart = (clone $forDay instanceof \DateTimeImmutable ? $forDay : new \DateTimeImmutable($forDay->format('c')))
            ->setTime(0, 0);
        $dayEnd = $dayStart->setTime(23, 59, 59);

        $reservations = RoomReservation::query()
            ->where('room_id', $room->id)
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved])
            ->where('reserved_from', '<=', $dayEnd)
            ->where('reserved_until', '>=', $dayStart)
            ->orderBy('reserved_from')
            ->get();

        // ابتدا از ساعت کاری روز شروع کنیم
        $workingHours = $room->working_hours ?? [];
        $dayKey = strtolower($dayStart->format('D'));
        $dayHours = $workingHours[$dayKey] ?? null;

        if (!$dayHours) {
            // اگر working_hours تعریف نشده، فرض می‌کنیم همه ساعات روز
            $availableStart = $dayStart;
            $availableEnd = $dayEnd;
        } else {
            $startTime = $dayHours['start'] ?? '00:00';
            $endTime = $dayHours['end'] ?? '23:59';
            $availableStart = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i',
                $dayStart->format('Y-m-d') . ' ' . $startTime,
            );
            $availableEnd = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i',
                $dayStart->format('Y-m-d') . ' ' . $endTime,
            );
        }

        // الگوریتم: شروع از availableStart، رزروها را به ترتیب چک کن
        $slots = [];
        $cursor = $availableStart;

        foreach ($reservations as $res) {
            $resStart = \DateTimeImmutable::createFromMutable(
                $res->reserved_from instanceof \DateTimeImmutable
                    ? new \DateTime($res->reserved_from->format('c'))
                    : $res->reserved_from
            );

            $resEnd = \DateTimeImmutable::createFromMutable(
                $res->reserved_until instanceof \DateTimeImmutable
                    ? new \DateTime($res->reserved_until->format('c'))
                    : $res->reserved_until
            );

            // اگر بین cursor و resStart فاصله کافی است، slot اضافه کن
            $gapMinutes = ($resStart->getTimestamp() - $cursor->getTimestamp()) / 60;
            if ($gapMinutes >= $minSlotMinutes) {
                $slots[] = TimeRange::from($cursor, $resStart);
            }
            // cursor را به پایان این رزرو ببر
            if ($resEnd > $cursor) {
                $cursor = $resEnd;
            }
        }

        // slot نهایی تا availableEnd
        $finalGap = ($availableEnd->getTimestamp() - $cursor->getTimestamp()) / 60;
        if ($finalGap >= $minSlotMinutes) {
            $slots[] = TimeRange::from($cursor, $availableEnd);
        }

        return $slots;
    }

    /**
     * پیشنهاد سالن‌های جایگزین در یک بازه
     *
     * @param int $minCapacity حداقل ظرفیت موردنیاز
     * @param array $requiredEquipment لیست تجهیزات الزامی (مثل ['projector', 'video_conference'])
     * @return Collection<Room>
     */
    public function suggestAlternativeRooms(
        int $organizationId,
        TimeRange $when,
        int $minCapacity = 1,
        array $requiredEquipment = [],
    ): Collection {
        $query = Room::query()
            ->where('organization_id', $organizationId)
            ->bookable()
            ->withCapacityAtLeast($minCapacity);

        foreach ($requiredEquipment as $eq) {
            $query->withEquipment($eq);
        }

        $candidates = $query->get();

        // فیلتر سالن‌های بدون تداخل
        return $candidates->filter(function (Room $room) use ($when) {
            $expanded = $when->expand(
                $room->buffer_before_minutes,
                $room->buffer_after_minutes,
            );
            return $this->findConflictingReservations($room, $expanded)->isEmpty()
                && $room->isInWorkingHours($when->start, $when->end);
        })->values();
    }
}

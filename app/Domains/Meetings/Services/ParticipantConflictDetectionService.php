<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Services;

use App\Domains\Calendar\ValueObjects\TimeRange;
use App\Domains\Meetings\Enums\MeetingStatus;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingParticipant;
use App\Domains\Organization\Models\Employee;
use Illuminate\Support\Collection;

/**
 * تشخیص تداخل برای شرکت‌کنندگان کلیدی (رئیس، دبیر، اعضای اجباری).
 *
 * منطق:
 * - برای رئیس و دبیر، هر تداخلی warning است
 * - برای اعضای اجباری، اگر بیشتر از حد آستانه conflict داشته باشد، warning
 * - این Service warning ها را تولید می‌کند ولی جلوی ایجاد جلسه را نمی‌گیرد
 *   (تصمیم نهایی با کاربر است)
 */
class ParticipantConflictDetectionService
{
    /**
     * بررسی تداخل برای یک Employee در یک بازه
     *
     * @return Collection<Meeting>
     */
    public function findConflictingMeetingsForEmployee(
        Employee $employee,
        TimeRange $range,
        ?Meeting $excludeMeeting = null,
    ): Collection {
        $query = Meeting::query()
            ->whereIn('status', [
                MeetingStatus::Scheduled,
                MeetingStatus::InvitationsSent,
                MeetingStatus::InProgress,
                MeetingStatus::Paused,
            ])
            ->where(function ($q) use ($range) {
                $q->where('scheduled_start_at', '<', $range->end)
                  ->where('scheduled_end_at', '>', $range->start);
            })
            ->where(function ($q) use ($employee) {
                $q->where('chairperson_employee_id', $employee->id)
                  ->orWhere('secretary_employee_id', $employee->id)
                  ->orWhereHas('participants', function ($p) use ($employee) {
                      $p->where('employee_id', $employee->id)
                        ->whereIn('invitation_status', ['invited', 'accepted', 'tentative']);
                  });
            });

        if ($excludeMeeting) {
            $query->where('id', '!=', $excludeMeeting->id);
        }

        return $query->get();
    }

    /**
     * بررسی تداخل برای لیست employee
     *
     * @param array<int> $employeeIds
     * @return array<int, Collection<Meeting>>  key = employee_id
     */
    public function findConflictsForEmployees(
        array $employeeIds,
        TimeRange $range,
        ?Meeting $excludeMeeting = null,
    ): array {
        $result = [];
        foreach ($employeeIds as $id) {
            $employee = Employee::find($id);
            if (!$employee) continue;

            $conflicts = $this->findConflictingMeetingsForEmployee($employee, $range, $excludeMeeting);
            if ($conflicts->isNotEmpty()) {
                $result[$id] = $conflicts;
            }
        }
        return $result;
    }

    /**
     * گزارش تداخل برای جلسه — برای نمایش به سازنده هنگام ایجاد یا ویرایش
     *
     * @return array{key_conflicts: array, mandatory_conflicts: array, summary: string}
     */
    public function analyzeMeetingConflicts(Meeting $meeting): array
    {
        $range = TimeRange::from($meeting->scheduled_start_at, $meeting->scheduled_end_at);
        $keyEmployeeIds = [];

        if ($meeting->chairperson_employee_id) {
            $keyEmployeeIds[] = $meeting->chairperson_employee_id;
        }
        if ($meeting->secretary_employee_id) {
            $keyEmployeeIds[] = $meeting->secretary_employee_id;
        }

        $keyConflicts = $this->findConflictsForEmployees($keyEmployeeIds, $range, $meeting);

        // شرکت‌کنندگان اجباری
        $mandatoryParticipantIds = $meeting->participants()
            ->where('is_mandatory', true)
            ->whereNotNull('employee_id')
            ->pluck('employee_id')
            ->toArray();

        $mandatoryConflicts = $this->findConflictsForEmployees(
            array_diff($mandatoryParticipantIds, $keyEmployeeIds),
            $range,
            $meeting,
        );

        $summary = $this->buildSummary($keyConflicts, $mandatoryConflicts);

        return [
            'key_conflicts' => $keyConflicts,
            'mandatory_conflicts' => $mandatoryConflicts,
            'summary' => $summary,
        ];
    }

    private function buildSummary(array $keyConflicts, array $mandatoryConflicts): string
    {
        $parts = [];
        if (!empty($keyConflicts)) {
            $parts[] = 'تداخل ' . count($keyConflicts) . ' عضو کلیدی';
        }
        if (!empty($mandatoryConflicts)) {
            $parts[] = 'تداخل ' . count($mandatoryConflicts) . ' عضو الزامی';
        }
        return empty($parts) ? 'بدون تداخل' : implode('، ', $parts);
    }
}

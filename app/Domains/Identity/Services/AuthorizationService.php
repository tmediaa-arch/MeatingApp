<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserDelegation;
use App\Domains\Shared\Enums\ConfidentialityLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class AuthorizationService
 *
 * هسته تصمیم‌گیری دسترسی. ترکیبی از RBAC + ABAC + Delegation.
 *
 * منطق تصمیم‌گیری به ترتیب اولویت:
 * 1. کاربر نمی‌تواند login کند → DENY
 * 2. کاربر super-admin است → ALLOW (با ثبت در audit)
 * 3. policy های DENY مطابقت دارد → DENY (deny غلبه می‌کند)
 * 4. کاربر permission مستقیم دارد → ALLOW
 * 5. کاربر از طریق نقش permission دارد → ALLOW
 * 6. کاربر از طریق تفویض permission دارد → ALLOW (با ثبت)
 * 7. policy های ALLOW مطابقت دارد → ALLOW
 * 8. پیش‌فرض → DENY
 *
 * این Service توسط Filament Policies استفاده می‌شود.
 */
class AuthorizationService
{
    /**
     * بررسی اینکه آیا کاربر می‌تواند action مشخصی را روی subject انجام دهد.
     */
    public function can(User $user, string $action, Model|string|null $subject = null): bool
    {
        // 1. کاربر باید بتواند login کند
        if (!$user->canLogin()) {
            return false;
        }

        // 2. super-admin همه چیز را می‌تواند
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // permission name استاندارد
        $subjectType = $this->resolveSubjectType($subject);
        $permissionName = $subjectType ? "{$subjectType}.{$action}" : $action;

        // 3. بررسی permission مستقیم (با cache)
        if ($user->hasPermissionTo($permissionName)) {
            // 4. سپس policy های deny را بررسی کن (deny بر allow اولویت دارد)
            if ($subject instanceof Model && $this->hasDenyPolicy($user, $action, $subject)) {
                return false;
            }
            return true;
        }

        // 5. بررسی از طریق تفویض
        if ($this->canViaDelegation($user, $permissionName, $subject)) {
            return true;
        }

        // 6. بررسی policy های ALLOW
        if ($subject instanceof Model && $this->hasAllowPolicy($user, $action, $subject)) {
            // باز هم deny را بررسی کن
            return !$this->hasDenyPolicy($user, $action, $subject);
        }

        return false;
    }

    /**
     * آیا کاربر می‌تواند subject را بر اساس سطح محرمانگی ببیند؟
     */
    public function canAccessByClearance(User $user, ConfidentialityLevel $required): bool
    {
        return $user->clearanceLevel()->canAccess($required);
    }

    /**
     * آیا کاربر می‌تواند به نمایندگی از کاربر دیگر اقدام کند؟
     */
    public function canActOnBehalfOf(User $delegate, User $delegator, string $scope = 'all'): ?UserDelegation
    {
        $delegation = UserDelegation::query()
            ->where('delegate_user_id', $delegate->id)
            ->where('delegator_user_id', $delegator->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->whereIn('scope', ['all', $scope])
            ->first();

        return $delegation;
    }

    // ------------------------- Internal Logic ------------------------- //

    private function resolveSubjectType(Model|string|null $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        if (is_string($subject)) {
            return strtolower(class_basename($subject));
        }

        return strtolower(class_basename($subject));
    }

    private function canViaDelegation(User $user, string $permissionName, Model|string|null $subject): bool
    {
        $activeDelegations = UserDelegation::query()
            ->where('delegate_user_id', $user->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->with('delegator')
            ->get();

        foreach ($activeDelegations as $delegation) {
            // آیا scope مناسب است؟
            $scopeMatches = $this->delegationScopeMatchesPermission($delegation->scope, $permissionName);
            if (!$scopeMatches) {
                continue;
            }

            // آیا restricted_to محدودیتی دارد؟
            if ($subject instanceof Model && !empty($delegation->restricted_to)) {
                $entityType = strtolower(class_basename($subject)) . 's';
                $allowedIds = $delegation->restricted_to[$entityType] ?? null;
                if (is_array($allowedIds) && !in_array($subject->getKey(), $allowedIds, true)) {
                    continue;
                }
            }

            // آیا delegator خودش permission دارد؟
            if ($delegation->delegator && $delegation->delegator->hasPermissionTo($permissionName)) {
                return true;
            }
        }

        return false;
    }

    private function delegationScopeMatchesPermission(string $scope, string $permission): bool
    {
        if ($scope === 'all') {
            return true;
        }

        $mapping = [
            'meetings' => ['meeting.', 'invitation.', 'attendance.'],
            'signatures' => ['minutes.sign', 'resolution.sign'],
            'approvals' => ['.approve', '.reject', '.confirm'],
            'tasks' => ['task.', 'resolution.'],
            'inbox' => ['inbox.', 'workitem.'],
        ];

        $patterns = $mapping[$scope] ?? [];
        foreach ($patterns as $pattern) {
            if (str_contains($permission, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * بررسی وجود policy از نوع deny که با شرایط مطابقت دارد
     */
    private function hasDenyPolicy(User $user, string $action, Model $subject): bool
    {
        return $this->evaluatePolicies($user, $action, $subject, 'deny');
    }

    private function hasAllowPolicy(User $user, string $action, Model $subject): bool
    {
        return $this->evaluatePolicies($user, $action, $subject, 'allow');
    }

    /**
     * ارزیابی access_policies — ABAC ساده
     *
     * Policy.conditions ساختار JSON دارد:
     * {
     *   "all": [
     *     {"fact": "user.is_external", "op": "eq", "value": false},
     *     {"fact": "subject.confidentiality_level", "op": "lte", "value": "user.clearance_level"}
     *   ]
     * }
     */
    private function evaluatePolicies(User $user, string $action, Model $subject, string $effect): bool
    {
        $subjectType = get_class($subject);

        $policies = DB::table('access_policies')
            ->where('subject_type', $subjectType)
            ->where('action', $action)
            ->where('effect', $effect)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        foreach ($policies as $policy) {
            $conditions = is_string($policy->conditions)
                ? json_decode($policy->conditions, true)
                : (array) $policy->conditions;

            if ($this->evaluateConditions($conditions, $user, $subject)) {
                return true;
            }
        }

        return false;
    }

    private function evaluateConditions(array $conditions, User $user, Model $subject): bool
    {
        if (isset($conditions['all'])) {
            foreach ($conditions['all'] as $cond) {
                if (!$this->evaluateSingleCondition($cond, $user, $subject)) {
                    return false;
                }
            }
            return true;
        }

        if (isset($conditions['any'])) {
            foreach ($conditions['any'] as $cond) {
                if ($this->evaluateSingleCondition($cond, $user, $subject)) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    private function evaluateSingleCondition(array $cond, User $user, Model $subject): bool
    {
        $factValue = $this->resolveFact($cond['fact'] ?? '', $user, $subject);
        $expected = $cond['value'] ?? null;

        // اگر expected خودش به فرم "user.X" یا "subject.X" است، resolve کن
        if (is_string($expected) && (str_starts_with($expected, 'user.') || str_starts_with($expected, 'subject.'))) {
            $expected = $this->resolveFact($expected, $user, $subject);
        }

        return match ($cond['op'] ?? 'eq') {
            'eq' => $factValue == $expected,
            'neq' => $factValue != $expected,
            'gt' => $factValue > $expected,
            'gte' => $factValue >= $expected,
            'lt' => $factValue < $expected,
            'lte' => $factValue <= $expected,
            'in' => is_array($expected) && in_array($factValue, $expected, true),
            'not_in' => is_array($expected) && !in_array($factValue, $expected, true),
            'contains' => is_string($factValue) && is_string($expected) && str_contains($factValue, $expected),
            default => false,
        };
    }

    private function resolveFact(string $path, User $user, Model $subject): mixed
    {
        $parts = explode('.', $path);
        $root = array_shift($parts);

        $object = match ($root) {
            'user' => $user,
            'subject' => $subject,
            default => null,
        };

        if (!$object) {
            return null;
        }

        foreach ($parts as $key) {
            // برای enum از value استفاده می‌کنیم
            $value = data_get($object, $key);
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
            $object = $value;

            if ($object === null) {
                return null;
            }
        }

        // برای clearance level به عدد تبدیل کن
        if (in_array($path, ['user.clearance_level', 'subject.confidentiality_level'], true)) {
            if (is_string($object)) {
                $enum = ConfidentialityLevel::tryFrom($object);
                return $enum?->level();
            }
        }

        return $object;
    }
}

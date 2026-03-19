<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\Engine;

use App\Domains\Workflow\Exceptions\WorkflowException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * ارزیاب expression با sandbox.
 *
 * این کلاس از symfony/expression-language استفاده می‌کند که
 * بسیار محدودتر از eval() است:
 *  - نمی‌تواند فایل بخواند/بنویسد
 *  - نمی‌تواند کلاس instantiate کند مگر در توابع whitelisted
 *  - فقط syntax محدودی پشتیبانی می‌کند
 *
 * مثال expressions:
 *   - `vars.priority == 'high'`
 *   - `vars.budget > 10000 and vars.approved`
 *   - `len(vars.assignees) > 0`
 *   - `now() > vars.due_date`
 */
class ExpressionEvaluator
{
    private ExpressionLanguage $lang;

    public function __construct()
    {
        $this->lang = new ExpressionLanguage();
        $this->registerFunctions();
    }

    /**
     * ارزیابی یک expression با مجموعه متغیرها.
     *
     * @param string $expression
     * @param array $variables متغیرها (key-value)
     * @return mixed
     */
    public function evaluate(string $expression, array $variables = []): mixed
    {
        if (trim($expression) === '') {
            throw WorkflowException::expressionEvaluationFailed('', 'expression خالی است');
        }

        try {
            // متغیرها در namespace `vars` در دسترس هستند
            return $this->lang->evaluate($expression, [
                'vars' => $variables,
            ]);
        } catch (\Throwable $e) {
            throw WorkflowException::expressionEvaluationFailed($expression, $e->getMessage());
        }
    }

    /**
     * ارزیابی به‌صورت boolean — هر مقدار truthy → true.
     */
    public function evaluateBoolean(string $expression, array $variables = []): bool
    {
        return (bool) $this->evaluate($expression, $variables);
    }

    /**
     * resolve یک expression که ممکن است literal باشد یا با ${} مشخص شده.
     * مثلاً: "user_123"  → literal
     *        "${vars.creator_id}" → expression
     */
    public function resolve(string $value, array $variables = []): mixed
    {
        if (preg_match('/^\$\{(.+)\}$/', trim($value), $m)) {
            return $this->evaluate($m[1], $variables);
        }
        return $value;
    }

    /**
     * توابع امن قابل استفاده در expressions.
     */
    private function registerFunctions(): void
    {
        // length
        $this->lang->register('len', function ($a) {
            return '\count((array) ($a))';
        }, function ($args, $a) {
            return is_array($a) || $a instanceof \Countable ? count((array) $a) : strlen((string) $a);
        });

        // now timestamp
        $this->lang->register('now', function () {
            return 'time()';
        }, function () {
            return time();
        });

        // boolean checks
        $this->lang->register('empty', function ($a) {
            return 'empty($a)';
        }, function ($args, $a) {
            return empty($a);
        });

        $this->lang->register('contains', function ($haystack, $needle) {
            return 'str_contains((string) $haystack, (string) $needle)';
        }, function ($args, $haystack, $needle) {
            return is_array($haystack)
                ? in_array($needle, $haystack, true)
                : str_contains((string) $haystack, (string) $needle);
        });

        $this->lang->register('starts_with', function ($haystack, $needle) {
            return 'str_starts_with((string) $haystack, (string) $needle)';
        }, function ($args, $haystack, $needle) {
            return str_starts_with((string) $haystack, (string) $needle);
        });

        $this->lang->register('lower', function ($s) {
            return 'mb_strtolower((string) $s)';
        }, function ($args, $s) {
            return mb_strtolower((string) $s);
        });

        $this->lang->register('upper', function ($s) {
            return 'mb_strtoupper((string) $s)';
        }, function ($args, $s) {
            return mb_strtoupper((string) $s);
        });

        // date helpers
        $this->lang->register('days_from_now', function ($n) {
            return '(time() + ((int) $n) * 86400)';
        }, function ($args, $n) {
            return time() + ((int) $n) * 86400;
        });
    }
}

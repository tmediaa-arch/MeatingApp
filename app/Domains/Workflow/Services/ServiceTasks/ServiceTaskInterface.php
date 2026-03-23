<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services\ServiceTasks;

use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\ProcessToken;

/**
 * هر Service Task باید این interface را پیاده‌سازی کند.
 *
 * هیچ Service Task بیرون از whitelist امن قابل اجرا نیست.
 */
interface ServiceTaskInterface
{
    /**
     * نام منحصربه‌فرد service task — برای ارجاع از BPMN XML
     * (مثلاً `mms:serviceTaskClass="send_email"`).
     */
    public static function key(): string;

    /**
     * توضیح کوتاه service task — برای نمایش در BPMN designer.
     */
    public static function description(): string;

    /**
     * schema پراپرتی‌های پیکربندی — برای فرم designer.
     *
     * مثال:
     * [
     *   'to' => ['type' => 'string', 'required' => true, 'label' => 'گیرنده'],
     *   'template_key' => ['type' => 'string', 'required' => true, 'label' => 'قالب'],
     * ]
     */
    public static function configSchema(): array;

    /**
     * اجرای task.
     *
     * @param ProcessInstance $instance instance فعلی
     * @param ProcessToken $token token در حال اجرای این service task
     * @param array $config پراپرتی‌های پیکربندی (resolved expressions)
     * @param array $variables متغیرهای فعلی instance
     * @return array variables جدید/به‌روز شده که در instance ذخیره شوند
     */
    public function execute(
        ProcessInstance $instance,
        ProcessToken $token,
        array $config,
        array $variables,
    ): array;
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workflow\Actions\DeployProcessAction;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder یک فرایند نمونه برای تست — تأیید صورتجلسه.
 *
 * این فرایند سه گام دارد:
 *  1. شروع
 *  2. UserTask: تأیید توسط رئیس
 *  3. ExclusiveGateway: اگر تأیید شد → ServiceTask (Send Notification) → پایان
 *                       اگر رد شد → پایان (Rejected)
 */
class SampleWorkflowProcessSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::first();
        if (!$organization) {
            $this->command->warn('هیچ سازمانی یافت نشد. ابتدا OrganizationSeeder را اجرا کنید.');
            return;
        }

        $admin = User::first();
        if (!$admin) {
            $this->command->warn('هیچ کاربری یافت نشد.');
            return;
        }

        $bpmnXml = $this->sampleMinuteApprovalBpmn();

        try {
            $definition = app(DeployProcessAction::class)->execute([
                'organization_id' => $organization->id,
                'process_key' => 'minute_approval_sample',
                'name' => 'فرایند نمونه: تأیید صورتجلسه',
                'description' => 'یک فرایند نمونه برای تست موتور BPMN با Gateway و ServiceTask',
                'category' => 'approval',
                'bpmn_xml' => $bpmnXml,
                'creator_user_id' => $admin->id,
                'publish_immediately' => true,
            ]);

            $this->command->info("✅ فرایند نمونه '{$definition->process_key}' v{$definition->version} منتشر شد");
        } catch (\Throwable $e) {
            $this->command->error('خطا در ایجاد فرایند نمونه: ' . $e->getMessage());
        }
    }

    private function sampleMinuteApprovalBpmn(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions
    xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
    xmlns:mms="http://mms.local/bpmn"
    id="Definitions_Sample"
    targetNamespace="http://mms.local/bpmn">

  <bpmn:process id="MinuteApprovalSample" isExecutable="true">
    <bpmn:documentation>فرایند نمونه برای تأیید یک صورتجلسه</bpmn:documentation>

    <!-- شروع -->
    <bpmn:startEvent id="Start" name="درخواست تأیید">
      <bpmn:outgoing>Flow_Start_ToReview</bpmn:outgoing>
    </bpmn:startEvent>

    <!-- وظیفه کاربر: بررسی توسط رئیس -->
    <bpmn:userTask id="Task_Review" name="بررسی صورتجلسه توسط رئیس">
      <bpmn:incoming>Flow_Start_ToReview</bpmn:incoming>
      <bpmn:outgoing>Flow_Review_ToGateway</bpmn:outgoing>
      <mms:assignee>${vars.chairperson_user_id}</mms:assignee>
      <mms:dueDate>${days_from_now(3)}</mms:dueDate>
      <mms:priority>high</mms:priority>
    </bpmn:userTask>

    <!-- Gateway: تأیید یا رد؟ -->
    <bpmn:exclusiveGateway id="Gateway_Approved" name="آیا تأیید شد؟" default="Flow_Rejected">
      <bpmn:incoming>Flow_Review_ToGateway</bpmn:incoming>
      <bpmn:outgoing>Flow_Approved</bpmn:outgoing>
      <bpmn:outgoing>Flow_Rejected</bpmn:outgoing>
    </bpmn:exclusiveGateway>

    <!-- ServiceTask: ارسال اعلان تأیید -->
    <bpmn:serviceTask id="Task_NotifyApproval" name="ارسال اعلان تأیید">
      <bpmn:incoming>Flow_Approved</bpmn:incoming>
      <bpmn:outgoing>Flow_ToEndApproved</bpmn:outgoing>
      <mms:serviceTaskClass>log</mms:serviceTaskClass>
      <mms:serviceTaskConfig>
        <mms:entry key="message">صورتجلسه ${vars.minute_number} تأیید شد</mms:entry>
        <mms:entry key="level">info</mms:entry>
      </mms:serviceTaskConfig>
    </bpmn:serviceTask>

    <!-- پایان: تأیید -->
    <bpmn:endEvent id="End_Approved" name="تأیید شد">
      <bpmn:incoming>Flow_ToEndApproved</bpmn:incoming>
    </bpmn:endEvent>

    <!-- پایان: رد -->
    <bpmn:endEvent id="End_Rejected" name="رد شد">
      <bpmn:incoming>Flow_Rejected</bpmn:incoming>
    </bpmn:endEvent>

    <!-- Flows -->
    <bpmn:sequenceFlow id="Flow_Start_ToReview" sourceRef="Start" targetRef="Task_Review"/>
    <bpmn:sequenceFlow id="Flow_Review_ToGateway" sourceRef="Task_Review" targetRef="Gateway_Approved"/>
    <bpmn:sequenceFlow id="Flow_Approved" sourceRef="Gateway_Approved" targetRef="Task_NotifyApproval">
      <bpmn:conditionExpression>vars._last_user_task_outcome == 'approve'</bpmn:conditionExpression>
    </bpmn:sequenceFlow>
    <bpmn:sequenceFlow id="Flow_Rejected" sourceRef="Gateway_Approved" targetRef="End_Rejected"/>
    <bpmn:sequenceFlow id="Flow_ToEndApproved" sourceRef="Task_NotifyApproval" targetRef="End_Approved"/>
  </bpmn:process>
</bpmn:definitions>
XML;
    }
}

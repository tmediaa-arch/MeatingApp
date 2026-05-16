<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Audit\Observers\AuditableModelObserver;
use App\Domains\Calendar\Services\JalaliCalendarService;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserDelegation;
use App\Domains\Identity\Policies\UserPolicy;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Identity\Services\DelegationContextService;
use App\Domains\Meetings\Events\MeetingStatusChanged;
use App\Domains\Meetings\Listeners\MeetingStatusListener;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use App\Domains\Meetings\Models\MeetingAttachment;
use App\Domains\Meetings\Models\MeetingParticipant;
use App\Domains\Meetings\Observers\MeetingObserver;
use App\Domains\Meetings\Policies\MeetingPolicy;
use App\Domains\Meetings\Services\ParticipantConflictDetectionService;
use App\Domains\Organization\Models\Employee;
use App\Domains\Organization\Models\JobTitle;
use App\Domains\Organization\Models\OrgUnit;
use App\Domains\Organization\Models\Organization;
use App\Domains\Organization\Models\Position;
use App\Domains\Organization\Observers\OrgUnitObserver;
use App\Domains\Organization\Policies\EmployeePolicy;
use App\Domains\Organization\Policies\OrgUnitPolicy;
use App\Domains\Rooms\Models\Room;
use App\Domains\Rooms\Models\RoomReservation;
use App\Domains\Rooms\Policies\RoomPolicy;
use App\Domains\Rooms\Policies\RoomReservationPolicy;
use App\Domains\Rooms\Services\RoomConflictDetectionService;
// Phase 3 — Post-meeting
use App\Domains\Minutes\Models\Minute;
use App\Domains\Minutes\Observers\MinuteObserver;
use App\Domains\Minutes\Policies\MinutePolicy;
use App\Domains\Minutes\Services\MinutePdfGenerator;
use App\Domains\Resolutions\Models\Resolution;
use App\Domains\Resolutions\Observers\ResolutionObserver;
use App\Domains\Resolutions\Policies\ResolutionPolicy;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Observers\TaskObserver;
use App\Domains\Tasks\Policies\TaskPolicy;
use App\Domains\Tasks\Services\TaskEscalationService;
use App\Domains\Notifications\Services\NotificationDispatcher;
use App\Domains\Files\Models\File;
use App\Domains\Files\Observers\FileObserver;
use App\Domains\Files\Policies\FilePolicy;
// Phase 4 — Workflow
use App\Domains\Workflow\Models\ProcessDefinition;
use App\Domains\Workflow\Models\ProcessInstance;
use App\Domains\Workflow\Models\UserTask as WorkflowUserTask;
use App\Domains\Workflow\Observers\ProcessDefinitionObserver;
use App\Domains\Workflow\Observers\ProcessInstanceObserver;
use App\Domains\Workflow\Policies\ProcessDefinitionPolicy;
use App\Domains\Workflow\Policies\ProcessInstancePolicy;
use App\Domains\Workflow\Policies\UserTaskPolicy as WorkflowUserTaskPolicy;
use App\Domains\Workflow\Services\Engine\ExpressionEvaluator;
use App\Domains\Workflow\Services\Engine\FlowResolver;
use App\Domains\Workflow\Services\Engine\Handlers\ElementHandlerRegistry;
use App\Domains\Workflow\Services\Engine\VariablesService;
use App\Domains\Workflow\Services\Parser\BpmnXmlParser;
use App\Domains\Workflow\Services\Runtime\WorkflowEngine;
use App\Domains\Workflow\Services\ServiceTasks\CreateTaskServiceTask;
use App\Domains\Workflow\Services\ServiceTasks\LogServiceTask;
use App\Domains\Workflow\Services\ServiceTasks\SendNotificationServiceTask;
use App\Domains\Workflow\Services\ServiceTasks\ServiceTaskRegistry;
use App\Domains\Workflow\Services\ServiceTasks\SetVariableServiceTask;
// Phase 5 — VideoConference & ServiceRequests
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use App\Domains\VideoConference\Models\VideoConferenceRoom;
use App\Domains\VideoConference\Observers\VideoConferenceRoomObserver;
use App\Domains\VideoConference\Policies\VideoConferenceProviderPolicy;
use App\Domains\VideoConference\Policies\VideoConferenceRoomPolicy;
use App\Domains\VideoConference\Services\VideoConferenceProviderManager;
use App\Domains\VideoConference\Services\VideoConferenceService;
use App\Domains\ServiceRequests\Models\ServiceRequest;
use App\Domains\ServiceRequests\Observers\ServiceRequestObserver;
use App\Domains\ServiceRequests\Policies\ServiceRequestPolicy;
use App\Domains\Workflow\Services\ServiceTasks\CreateVideoConferenceServiceTask;
// Phase 6 — Reports & Dashboards & Integrations & Exports
use App\Domains\Reports\Models\Report;
use App\Domains\Reports\Models\ReportRun;
use App\Domains\Reports\Models\ReportSchedule;
use App\Domains\Reports\Policies\ReportPolicy;
use App\Domains\Reports\Services\ReportRegistryService;
use App\Domains\Reports\Services\ReportRenderingService;
use App\Domains\Reports\Services\ReportRunnerService;
use App\Domains\Reports\Reports\Audit\AuditActivityReport;
use App\Domains\Reports\Reports\Kpi\ExecutiveKpiReport;
use App\Domains\Reports\Reports\Meetings\MeetingsAttendanceRateReport;
use App\Domains\Reports\Reports\Meetings\MeetingsSummaryReport;
use App\Domains\Reports\Reports\Minutes\MinutesPublishedReport;
use App\Domains\Reports\Reports\Resolutions\ResolutionsExecutionRateReport;
use App\Domains\Reports\Reports\Tasks\TasksCompletionRateReport;
use App\Domains\Reports\Reports\Tasks\TasksOverdueReport;
use App\Domains\Reports\Reports\VideoConference\VideoConferenceUsageReport;
use App\Domains\Reports\Reports\Workflow\WorkflowInstancesReport;
use App\Domains\Reports\Enums\ReportCategory;
use App\Domains\Dashboards\Models\Dashboard;
use App\Domains\Dashboards\Policies\DashboardPolicy;
use App\Domains\Dashboards\Services\DashboardRegistryService;
use App\Domains\Dashboards\Services\DashboardService;
use App\Domains\Dashboards\Widgets\AttendanceTrendChartWidget;
use App\Domains\Dashboards\Widgets\MyMeetingsCountWidget;
use App\Domains\Dashboards\Widgets\MyPendingApprovalsWidget;
use App\Domains\Dashboards\Widgets\OverdueTasksStatWidget;
use App\Domains\Dashboards\Widgets\RecentMinutesListWidget;
use App\Domains\Dashboards\Widgets\ResolutionsStatusChartWidget;
use App\Domains\Dashboards\Widgets\RoomUtilizationWidget;
use App\Domains\Dashboards\Widgets\TasksCompletionGaugeWidget;
use App\Domains\Integrations\Models\ApiWebhook;
use App\Domains\Integrations\Models\IntegrationProvider;
use App\Domains\Integrations\Policies\IntegrationProviderPolicy;
use App\Domains\Integrations\Services\IntegrationProviderManager;
use App\Domains\Integrations\Services\IntegrationSyncService;
use App\Domains\Integrations\Services\SsoAuthService;
use App\Domains\Integrations\Services\WebhookDispatchService;
use App\Domains\Exports\Models\ExportJob;
use App\Domains\Exports\Services\ExportCleanupService;
use App\Domains\Exports\Services\ExportService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Class DomainServiceProvider
 *
 * مرکز ثبت همه domain-related bindings:
 * - Observers
 * - Policies
 * - Singleton Services
 * - Event listeners
 */
class DomainServiceProvider extends ServiceProvider
{
    protected array $policies = [
        // Phase 1
        User::class => UserPolicy::class,
        OrgUnit::class => OrgUnitPolicy::class,
        Employee::class => EmployeePolicy::class,
        // Phase 2
        Meeting::class => MeetingPolicy::class,
        Room::class => RoomPolicy::class,
        RoomReservation::class => RoomReservationPolicy::class,
        // Phase 3
        Minute::class => MinutePolicy::class,
        Resolution::class => ResolutionPolicy::class,
        Task::class => TaskPolicy::class,
        File::class => FilePolicy::class,
        // Phase 4
        ProcessDefinition::class => ProcessDefinitionPolicy::class,
        ProcessInstance::class => ProcessInstancePolicy::class,
        WorkflowUserTask::class => WorkflowUserTaskPolicy::class,
        // Phase 5
        VideoConferenceProvider::class => VideoConferenceProviderPolicy::class,
        VideoConferenceRoom::class => VideoConferenceRoomPolicy::class,
        ServiceRequest::class => ServiceRequestPolicy::class,
        // Phase 6
        Report::class => ReportPolicy::class,
        Dashboard::class => DashboardPolicy::class,
        IntegrationProvider::class => IntegrationProviderPolicy::class,
    ];

    protected array $auditableModels = [
        // Phase 1
        User::class,
        UserDelegation::class,
        Organization::class,
        OrgUnit::class,
        Employee::class,
        Position::class,
        JobTitle::class,
        // Phase 2
        Meeting::class,
        MeetingParticipant::class,
        MeetingAgendaItem::class,
        MeetingAttachment::class,
        Room::class,
        RoomReservation::class,
        // Phase 3
        Minute::class,
        Resolution::class,
        Task::class,
        File::class,
        // Phase 4
        ProcessDefinition::class,
        ProcessInstance::class,
        // Phase 5
        VideoConferenceProvider::class,
        VideoConferenceRoom::class,
        ServiceRequest::class,
        // Phase 6
        Report::class,
        ReportSchedule::class,
        Dashboard::class,
        IntegrationProvider::class,
        ApiWebhook::class,
        ExportJob::class,
    ];

    protected array $eventListeners = [
        MeetingStatusChanged::class => [MeetingStatusListener::class],
    ];

    public function register(): void
    {
        // Phase 1 services
        $this->app->singleton(\App\Domains\Audit\Services\AuditService::class);
        $this->app->singleton(AuthorizationService::class);
        $this->app->singleton(DelegationContextService::class);

        // Phase 2 services
        $this->app->singleton(JalaliCalendarService::class);
        $this->app->singleton(RoomConflictDetectionService::class);
        $this->app->singleton(ParticipantConflictDetectionService::class);

        // Phase 3 services
        $this->app->singleton(NotificationDispatcher::class);
        $this->app->singleton(MinutePdfGenerator::class);
        $this->app->singleton(TaskEscalationService::class);

        // Phase 4 — Workflow services (همه singleton هستند چون stateless اند)
        $this->app->singleton(BpmnXmlParser::class);
        $this->app->singleton(ExpressionEvaluator::class);
        $this->app->singleton(VariablesService::class);
        $this->app->singleton(FlowResolver::class);
        $this->app->singleton(ServiceTaskRegistry::class);
        $this->app->singleton(ElementHandlerRegistry::class);
        $this->app->singleton(WorkflowEngine::class);

        // Phase 5 — VideoConference services
        $this->app->singleton(VideoConferenceProviderManager::class);
        $this->app->singleton(VideoConferenceService::class);

        // Phase 6 — Reports services
        $this->app->singleton(ReportRunnerService::class);
        $this->app->singleton(ReportRegistryService::class);
        $this->app->singleton(ReportRenderingService::class);

        // Phase 6 — Dashboards services
        $this->app->singleton(DashboardService::class);
        $this->app->singleton(DashboardRegistryService::class);

        // Phase 6 — Integrations services
        $this->app->singleton(IntegrationProviderManager::class);
        $this->app->singleton(IntegrationSyncService::class);
        $this->app->singleton(SsoAuthService::class);
        $this->app->singleton(WebhookDispatchService::class);

        // Phase 6 — Exports services
        $this->app->singleton(ExportService::class);
        $this->app->singleton(ExportCleanupService::class);
    }

    public function boot(): void
    {
        $this->registerObservers();
        $this->registerPolicies();
        $this->registerEventListeners();
        $this->registerTableDefaults();
        Event::subscribe(\App\Domains\Audit\Listeners\AuthEventSubscriber::class);
        $this->registerWorkflowHandlers();
        $this->registerWorkflowServiceTasks();
        $this->registerReports();
        $this->registerDashboards();
    }

    /**
     * Phase 6 — ثبت همه گزارش‌های built-in در ReportRegistryService.
     *
     * این متد فقط روی in-memory registry کار می‌کند؛ sync به DB
     * توسط command `reports:sync-registry` انجام می‌شود (یا در زمان seed اولیه).
     */
    private function registerReports(): void
    {
        /** @var ReportRegistryService $registry */
        $registry = $this->app->make(ReportRegistryService::class);

        $registry->register(
            handlerClass: MeetingsSummaryReport::class,
            key: 'meetings.summary',
            displayName: 'خلاصه جلسات',
            category: ReportCategory::Meetings,
            cacheTtlMinutes: 30,
        );
        $registry->register(
            handlerClass: MeetingsAttendanceRateReport::class,
            key: 'meetings.attendance_rate',
            displayName: 'نرخ حضور در جلسات',
            category: ReportCategory::Attendance,
            cacheTtlMinutes: 60,
        );
        $registry->register(
            handlerClass: MinutesPublishedReport::class,
            key: 'minutes.published',
            displayName: 'صورتجلسات منتشر شده',
            category: ReportCategory::Minutes,
        );
        $registry->register(
            handlerClass: ResolutionsExecutionRateReport::class,
            key: 'resolutions.execution_rate',
            displayName: 'نرخ اجرای مصوبات',
            category: ReportCategory::Resolutions,
        );
        $registry->register(
            handlerClass: TasksOverdueReport::class,
            key: 'tasks.overdue',
            displayName: 'وظایف معوقه',
            category: ReportCategory::Tasks,
            cacheTtlMinutes: 15,
        );
        $registry->register(
            handlerClass: TasksCompletionRateReport::class,
            key: 'tasks.completion_rate',
            displayName: 'نرخ تکمیل وظایف',
            category: ReportCategory::Tasks,
        );
        $registry->register(
            handlerClass: AuditActivityReport::class,
            key: 'audit.activity',
            displayName: 'گزارش فعالیت ممیزی',
            category: ReportCategory::Audit,
            cacheable: false,
        );
        $registry->register(
            handlerClass: WorkflowInstancesReport::class,
            key: 'workflow.instances',
            displayName: 'instance های گردش کار',
            category: ReportCategory::Workflow,
        );
        $registry->register(
            handlerClass: VideoConferenceUsageReport::class,
            key: 'video_conference.usage',
            displayName: 'استفاده از ویدئوکنفرانس',
            category: ReportCategory::VideoConference,
        );
        $registry->register(
            handlerClass: ExecutiveKpiReport::class,
            key: 'kpi.executive',
            displayName: 'شاخص‌های کلیدی مدیریتی',
            category: ReportCategory::Kpi,
            cacheTtlMinutes: 30,
        );
    }

    /**
     * Phase 6 — تعریف داشبوردهای built-in.
     *
     * هر داشبورد مجموعه‌ای از widget هاست که در گرید ۱۲ ستونی چینش می‌شوند.
     */
    private function registerDashboards(): void
    {
        /** @var DashboardRegistryService $registry */
        $registry = $this->app->make(DashboardRegistryService::class);

        // 1. داشبورد مدیر ارشد
        $registry->defineDashboard(
            key: 'executive',
            displayName: 'داشبورد مدیر ارشد',
            allowedRoles: ['super-admin', 'organization-admin'],
            icon: 'heroicon-o-trophy',
            color: 'primary',
            description: 'نمای کلی KPI های کلیدی سازمان',
        )
            ->widget(OverdueTasksStatWidget::class, 'overdue_tasks_org', 'وظایف معوقه سازمان', 'stat', 0, 0, 3, 1, config: ['scope' => 'org'])
            ->widget(TasksCompletionGaugeWidget::class, 'completion_rate_org', 'نرخ تکمیل (۳۰ روز)', 'stat', 0, 3, 3, 1, config: ['days' => 30])
            ->widget(MyMeetingsCountWidget::class, 'my_meetings_count', 'جلسات این هفته', 'stat', 0, 6, 3, 1)
            ->widget(MyPendingApprovalsWidget::class, 'pending_approvals', 'در انتظار تأیید', 'stat', 0, 9, 3, 1)
            ->widget(AttendanceTrendChartWidget::class, 'attendance_trend', 'روند جلسات', 'chart', 1, 0, 8, 2, 'line', ['weeks' => 12])
            ->widget(ResolutionsStatusChartWidget::class, 'resolutions_status', 'وضعیت مصوبات', 'chart', 1, 8, 4, 2, 'doughnut', ['days' => 90])
            ->widget(RoomUtilizationWidget::class, 'room_util', 'استفاده از سالن‌ها', 'chart', 3, 0, 6, 2, 'bar')
            ->widget(RecentMinutesListWidget::class, 'recent_minutes', 'آخرین صورتجلسات', 'list', 3, 6, 6, 2, config: ['limit' => 8])
            ->register();

        // 2. داشبورد دبیر جلسه
        $registry->defineDashboard(
            key: 'secretary',
            displayName: 'داشبورد دبیر جلسه',
            allowedRoles: ['meeting-secretary'],
            icon: 'heroicon-o-document-text',
            color: 'info',
        )
            ->widget(MyMeetingsCountWidget::class, 'my_meetings', 'جلسات این هفته', 'stat', 0, 0, 3, 1)
            ->widget(MyPendingApprovalsWidget::class, 'my_approvals', 'در انتظار تأیید', 'stat', 0, 3, 3, 1)
            ->widget(OverdueTasksStatWidget::class, 'my_overdue', 'وظایف معوقه من', 'stat', 0, 6, 3, 1, config: ['scope' => 'mine'])
            ->widget(RecentMinutesListWidget::class, 'recent_min', 'آخرین صورتجلسات', 'list', 1, 0, 12, 2)
            ->register();

        // 3. داشبورد Auditor
        $registry->defineDashboard(
            key: 'auditor',
            displayName: 'داشبورد ممیز',
            allowedRoles: ['auditor'],
            icon: 'heroicon-o-shield-check',
            color: 'warning',
        )
            ->widget(OverdueTasksStatWidget::class, 'overdue_audit', 'وظایف معوقه کل', 'stat', 0, 0, 4, 1, config: ['scope' => 'all'])
            ->widget(MyPendingApprovalsWidget::class, 'pending_audit', 'در انتظار', 'stat', 0, 4, 4, 1)
            ->widget(TasksCompletionGaugeWidget::class, 'completion_audit', 'نرخ تکمیل کل', 'stat', 0, 8, 4, 1, config: ['days' => 90])
            ->widget(AttendanceTrendChartWidget::class, 'trend_audit', 'روند جلسات', 'chart', 1, 0, 12, 2, 'line')
            ->register();
    }

    private function registerObservers(): void
    {
        OrgUnit::observe(OrgUnitObserver::class);
        Meeting::observe(MeetingObserver::class);

        // Phase 3 state-machine observers
        Minute::observe(MinuteObserver::class);
        Resolution::observe(ResolutionObserver::class);
        Task::observe(TaskObserver::class);
        File::observe(FileObserver::class);

        // Phase 4 state-machine observers
        ProcessDefinition::observe(ProcessDefinitionObserver::class);
        ProcessInstance::observe(ProcessInstanceObserver::class);

        // Phase 5 state-machine observers
        VideoConferenceRoom::observe(VideoConferenceRoomObserver::class);
        ServiceRequest::observe(ServiceRequestObserver::class);

        foreach ($this->auditableModels as $modelClass) {
            $modelClass::observe(AuditableModelObserver::class);
        }
    }

    private function registerPolicies(): void
    {
        foreach ($this->policies as $modelClass => $policyClass) {
            Gate::policy($modelClass, $policyClass);
        }

        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
                return true;
            }
            return null;
        });
    }

    private function registerEventListeners(): void
    {
        foreach ($this->eventListeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /**
     * پیش‌فرض مشترک برای همه جدول‌های Filament — پیام «خالی بودن» فارسی.
     *
     * این صرفاً پیش‌فرض است؛ هر جدول می‌تواند در متد table() خود آن را
     * بازنویسی کند و دکمه «ایجاد» همچنان به‌صورت خودکار توسط Filament در
     * empty state نمایش داده می‌شود.
     */
    private function registerTableDefaults(): void
    {
        \Filament\Tables\Table::configureUsing(function (\Filament\Tables\Table $table): void {
            $table
                ->emptyStateHeading('موردی برای نمایش وجود ندارد')
                ->emptyStateDescription('هنوز رکوردی در این فهرست ثبت نشده است؛ برای افزودن مورد جدید از دکمه‌های موجود استفاده کنید.')
                ->emptyStateIcon(\Filament\Support\Icons\Heroicon::OutlinedInbox);
        });
    }

    /**
     * Phase 4 — ثبت handlerهای موتور Workflow.
     *
     * هر نوع element BPMN باید handler مربوطه را داشته باشد.
     */
    private function registerWorkflowHandlers(): void
    {
        /** @var ElementHandlerRegistry $registry */
        $registry = $this->app->make(ElementHandlerRegistry::class);
        $registry->registerDefaults();
    }

    /**
     * Phase 4 — ثبت Service Taskهای built-in.
     *
     * هر Service Task جدید باید اینجا ثبت شود تا در whitelist امن قرار گیرد.
     */
    private function registerWorkflowServiceTasks(): void
    {
        /** @var ServiceTaskRegistry $registry */
        $registry = $this->app->make(ServiceTaskRegistry::class);

        $registry->register(SendNotificationServiceTask::class);
        $registry->register(CreateTaskServiceTask::class);
        $registry->register(SetVariableServiceTask::class);
        $registry->register(LogServiceTask::class);
        // Phase 5 bridge
        $registry->register(CreateVideoConferenceServiceTask::class);
    }
}

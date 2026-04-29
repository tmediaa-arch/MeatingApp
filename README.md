# سامانه جامع مدیریت جلسات سازمانی (MMS)

سامانه‌ای جامع برای مدیریت کامل چرخه عمر جلسات سازمانی — از برنامه‌ریزی تا تأیید نهایی اجرای مصوبات.

## ویژگی‌های اصلی

- 🗓️ **مدیریت کامل جلسات** — حضوری، آنلاین، ترکیبی (Hybrid)
- 📋 **صورتجلسه و مصوبات** — با ادیتور غنی، نسخه‌بندی، امضای دیجیتال
- ✅ **پیگیری وظایف** — تبدیل مصوبه به وظیفه با گردش کار کامل
- 🎬 **ویدئوکنفرانس** — معماری Adapter قابل تعویض (Alocom/Jitsi/BBB)
- 📊 **فرایندساز BPMN** — طراحی گرافیکی فرایند با bpmn.js + Runtime اختصاصی Laravel
- 📅 **تقویم شمسی** — FullCalendar Shamsi با ICS feed برای تقویم خارجی
- 🔐 **امنیت سازمانی** — RBAC + ABAC، MFA، Audit Log کامل، تفویض اختیار
- 🏢 **ساختار سازمانی** — درختی، تاریخچه‌دار، با پشتیبانی LDAP
- 📈 **گزارش‌ها و داشبورد** — ۱۰+ گزارش از پیش تعریف، داشبورد نقش‌محور، KPI ها
- 🔌 **یکپارچه‌سازی** — LDAP/AD، SAML SSO، HRS sync، Webhook outbound
- 📤 **Export و API** — XLSX/PDF/CSV/ICS + REST API V1 با Sanctum
- 🚀 **درخواست‌های جانبی** — کترینگ، حمل‌ونقل، تجهیزات، پشتیبانی فنی

## معماری

**Modular Monolith** — هر دامنه شامل Models، Actions، Services، DTOs، Events، Listeners، Jobs، Policies، Observers، Filament Resources و Tests.

```
app/
  Domains/
    Identity/         # کاربران، نقش‌ها، دسترسی‌ها، تفویض
    Organization/     # سازمان، واحد، پست، کارمند
    Audit/            # لاگ ممیزی، لاگ ورود، رویدادهای امنیتی
    Settings/         # تنظیمات سامانه
    Meetings/         # جلسات (فاز ۲)
    Calendar/         # تقویم (فاز ۲)
    Rooms/            # سالن‌ها (فاز ۲)
    Invitations/      # دعوت‌نامه‌ها (فاز ۲)
    Notifications/    # اعلان‌ها (فاز ۳)
    Minutes/          # صورتجلسه (فاز ۳)
    Resolutions/      # مصوبات (فاز ۳)
    Tasks/            # وظایف (فاز ۳)
    Files/            # فایل‌ها و پیوست‌ها (فاز ۳)
    Workflow/         # BPMN و Workflow Runtime (فاز ۴)
    VideoConference/  # ویدئوکنفرانس (فاز ۵)
    ServiceRequests/  # درخواست‌های جانبی (فاز ۵)
    Reports/          # گزارش‌ها (فاز ۶)
    Dashboards/       # داشبوردهای نقش‌محور (فاز ۶)
    Integrations/     # LDAP، SSO، HRS، Webhooks (فاز ۶)
    Exports/          # Export به فرمت‌های مختلف (فاز ۶)
    Shared/           # کد مشترک بین دامنه‌ها
```

## استک فنی

- **PHP 8.3+** با Laravel 11
- **Filament 3** برای پنل ادمین
- **Livewire 3** + Blade برای UI تعاملی
- **PostgreSQL** (یا MySQL 8) برای دیتابیس اصلی
- **Redis** برای cache، queue، session
- **FullCalendar Shamsi** برای تقویم
- **bpmn.js** برای طراحی گرافیکی فرایندها
- **Spatie packages** — permission، activitylog، data، settings، medialibrary
- **morilog/jalali** برای تاریخ شمسی
- **Pest** برای تست
- **Pint** + **PHPStan** برای کیفیت کد

## نقشه راه ۶ فازی

| فاز | عنوان | وضعیت |
|-----|-------|--------|
| ۱ | **زیرساخت و هویت** — Identity, Organization, Audit, Settings | ✅ تکمیل شده |
| ۲ | **هسته جلسات و تقویم** — Meetings, Calendar (Shamsi), Rooms, Invitations | ✅ تکمیل شده |
| ۳ | **صورتجلسه و مصوبات** — Minutes, Resolutions, Tasks + Notifications + Files | ✅ تکمیل شده |
| ۴ | **موتور فرایند BPMN** — Workflow Engine + bpmn.js Designer | ✅ تکمیل شده |
| ۵ | **ویدئوکنفرانس و درخواست‌های جانبی** — VideoConference Adapter + Service Requests | ⏳ بعدی |
| ۶ | **گزارش‌ها و یکپارچه‌سازی** — Reports, Dashboards, LDAP, HRS Integration | ⏳ |

## نصب سریع با Docker

```bash
# ۱. کلون کردن
git clone <repo> mms && cd mms

# ۲. کپی env
cp .env.example .env

# ۳. اجرای کانتینرها
docker compose up -d

# ۴. نصب dependencies
docker compose exec app composer install

# ۵. تولید کلید برنامه
docker compose exec app php artisan key:generate

# ۶. اجرای migration و seeder
docker compose exec app php artisan migrate --seed

# ۷. ساخت storage link
docker compose exec app php artisan storage:link

# ۸. ورود به پنل
# http://localhost:8080/admin
# username: admin
# password: ChangeMe@123456 (حتماً تغییر دهید)
```

## نصب دستی

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

نیازمندی‌ها: PHP 8.3+، Composer 2، PostgreSQL 14+ یا MySQL 8+، Redis 6+، Node 20+ (برای assets).

## تست

```bash
# اجرای تمام تست‌ها
php artisan test

# یا با Pest مستقیماً
./vendor/bin/pest

# با coverage
./vendor/bin/pest --coverage
```

## فاز ۱ — جزئیات

این فاز شامل ۴ دامنه اصلی است:

### Identity (هویت)
- مدیریت کاربران با وضعیت‌های Active/Suspended/Locked/Expired/Pending
- نقش‌ها و دسترسی‌ها بر اساس **spatie/permission** گسترش یافته
- **RBAC + ABAC** ترکیبی با Policy های قابل تنظیم
- **تفویض اختیار** با محدوده زمانی و scope (all/meetings/signatures/approvals/tasks/inbox)
- پشتیبانی از LDAP و SSO (placeholder در این فاز)
- **MFA** برای نقش‌های حساس

### Organization (ساختار سازمانی)
- ساختار سازمانی درختی با **materialized path**
- مدل پست (Position) با وضعیت vacant/occupied/frozen/abolished
- کارمندان با تاریخچه انتساب پست (append-only)
- ۹ نوع واحد سازمانی (معاونت، اداره، دفتر و...)

### Audit (ممیزی)
- لاگ ممیزی **append-only** — هیچ‌گاه قابل ویرایش یا حذف نیست
- لاگ ورود با brute-force protection
- رویدادهای امنیتی با گردش کار بررسی
- **Correlation ID** برای ردیابی هر درخواست end-to-end

### Settings (تنظیمات)
- تنظیمات با type، group، subgroup، validation
- پشتیبانی از encryption برای مقادیر حساس
- ترجیحات اعلان به تفکیک کاربر و نوع

## فاز ۲ — جزئیات (✅ تکمیل شد)

این فاز هسته مدیریت جلسات و تقویم را پیاده‌سازی می‌کند:

### Meetings (جلسات)
- **State Machine** با ۹ وضعیت: Draft → Scheduled → InvitationsSent → InProgress (↔ Paused) → Completed
- وضعیت‌های ترمینال: Completed، Cancelled
- جدول `meeting_status_transitions` به‌صورت **append-only** برای حفظ تاریخچه
- تولید خودکار `meeting_number` به فرمت `${org_code}-${year}-####`
- شرکت‌کنندگان داخلی (Employee) و خارجی (مهمان) در یک جدول
- مدیریت دستور جلسه (`MeetingAgendaItem`) با ترتیب قابل تغییر
- پیوست‌ها با سطح visibility (all/voting/chair-sec/specific/private)

### Rooms (سالن‌ها)
- ظرفیت، چیدمان، تجهیزات (پروژکتور، VC، Whiteboard...)
- سیاست رزرو: free / approval / restricted
- **Buffer time** قبل و بعد از رزرو برای جلوگیری از تداخل
- ساعات کاری به تفکیک روز هفته (شنبه تا چهارشنبه پیش‌فرض)
- `RoomConflictDetectionService` با اعتبارسنجی **atomic** (`lockForUpdate`)

### Calendar (تقویم)
- `JalaliCalendarService` — تنها نقطه تبدیل تقویم در سامانه
- `TimeRange` Value Object برای کار با بازه‌های زمانی
- **FullCalendar Shamsi** با Drag & Drop برای تغییر زمان
- پشتیبانی کامل از سال‌های کبیسه شمسی

### Invitations (دعوت‌نامه‌ها)
- ایجاد invitation در صف برای کانال‌های email/in_app/sms
- پاسخ مدعو: accepted / declined / tentative
- `invitation_responses` به‌صورت **append-only** — تاریخچه کامل پاسخ‌ها حفظ می‌شود
- پشتیبانی از معرفی جایگزین (proposed_substitute) و تفویض اختیار

### پوشش تست
**66 تست Pest** در ۱۰ فایل تست برای پوشش تمام مسیرهای بحرانی فاز ۲ از جمله:
- state machine کامل و idempotency
- conflict detection با buffer
- append-only enforcement
- round-trip تبدیل تقویم شمسی

## فاز ۳ — جزئیات (✅ تکمیل شد)

این فاز چرخه پس از جلسه را پیاده‌سازی می‌کند:

### Minutes (صورت‌جلسات)
- **State Machine**: Draft → Review → Signed → Published (با Revoked/Archived ترمینال)
- شماره خودکار: `${org_code}-MIN-${year}-####`
- جدول `minute_versions` به‌صورت **append-only** — هر ویرایش snapshot کامل می‌سازد
- جدول `minute_signatures` به‌صورت **append-only** با `content_hash` (SHA-256) ذخیره‌شده در لحظه امضا
- **تشخیص دستکاری**: `isValidForCurrentContent()` در هر امضا hash فعلی را با ذخیره‌شده مقایسه می‌کند
- امضای دومرحله‌ای (دبیر + رئیس) قبل از انتشار
- تولید PDF با blade template و درج content_hash در فوتر

### Resolutions (مصوبات)
- شماره خودکار: `${org_code}-RES-${year}-####`
- پشتیبانی از رأی‌گیری با **حد نصاب** و **آستانه اکثریت** قابل تنظیم
- جدول `resolution_votes` به‌صورت **append-only**
- پشتیبانی از **رأی تفویضی** (proxy vote) با ثبت `delegation_id`
- assignees با نقش‌های Executor/Supervisor/Approver/Informant
- تبدیل خودکار مصوبه به وظایف از طریق `CreateTasksFromResolutionAction`

### Tasks (وظایف)
- **State Machine** کامل: Open → Assigned → InProgress → Submitted → UnderReview → Completed (با NeedsRevision → InProgress برگشت)
- شماره خودکار: `${org_code}-TSK-${year}-####`
- منشأ polymorphic: Resolution / Meeting / parent Task
- جدول `task_updates` به‌صورت **append-only** برای timeline
- **Escalation سطح‌بندی شده**:
  - L1: ۱+ روز تأخیر → Supervisor
  - L2: ۳+ روز تأخیر → Supervisor + Approver
  - L3: ۷+ روز تأخیر → Supervisor + Approver + Creator
- `TaskEscalationService::runDaily()` idempotent با check روی `last_escalated_at`
- تمدید مهلت با گردش درخواست → تأیید/رد

### Notifications (اعلان‌ها)
- قالب‌های کلید-بنیاد با template variables (`{{ var }}`)
- `notification_template_channels` — متن قالب به ازای هر کانال
- **Outbox unified با Inbox**: همان جدول `notifications_outbox` برای صف ارسال و کارتابل
- پشتیبانی از ۵ کانال: email / sms / in_app / push / webhook
- ترجیحات کاربر: channel matrix + **quiet hours**
- Retry با **exponential backoff**: [1, 5, 30, 120, 360] دقیقه
- ۱۷ قالب پیش‌فرض seed شده برای فلوهای رایج

### Files (فایل‌ها)
- متادیتای کامل با hash SHA-256 و virus scan status
- **ACL پلی‌مورفیک**: owner / public / explicit permissions
- جدول `file_access_logs` به‌صورت **append-only**
- پشتیبانی از versioning (previous_version_file_id)
- expiry و watermarking (آماده برای فاز ۴)

### پوشش تست
**۱۲+ فایل تست Pest** در ۵ پوشه تست برای فاز ۳:
- state machine validation برای Minute/Resolution/Task
- append-only enforcement (minute_versions، minute_signatures، resolution_votes، task_updates، file_access_logs)
- content_hash و tampering detection
- voting (quorum، majority، duplicate prevention)
- task escalation با idempotency
- task lifecycle کامل
- notification dispatcher (rendering، preferences، quiet hours)
- file access control

## فاز ۴ — جزئیات (✅ تکمیل شد)

این فاز موتور BPMN 2.0 اختصاصی روی Laravel را پیاده‌سازی می‌کند که سنگین‌ترین فاز پروژه است.

### Workflow — BPMN Engine
- **State Machine** ProcessDefinitionStatus: Draft → Published → Deprecated → Archived
- **State Machine** ProcessInstanceStatus: Pending → Running ⇄ Suspended → Completed/Cancelled/Failed
- **Token-based execution** مشابه Camunda/Activiti
- جدول `process_history` به‌صورت **append-only**
- پشتیبانی از versioning per `process_key` با خودکار `is_latest`
- شناسایی content با hash SHA-256 از BPMN XML

### BPMN 2.0 Element Coverage
عناصر پشتیبانی‌شده در BPMN 2.0:
- **Events**: StartEvent، EndEvent، IntermediateCatchEvent، IntermediateThrowEvent، BoundaryEvent
- **Tasks**: UserTask، ServiceTask، ManualTask، ReceiveTask
- **Gateways**: ExclusiveGateway (XOR)، ParallelGateway (AND split/join)، InclusiveGateway (OR)
- **Flow**: SequenceFlow با conditionExpression و default

### BPMN Designer (Filament Page)
- **bpmn.js Modeler** برای طراحی گرافیکی
- پشتیبانی از import/export XML
- ذخیره مستقیم از طریق `DeployProcessAction`
- نمای runtime با hilight کردن tokenهای فعال (آبی) و waiting (نارنجی)

### Workflow Engine
- `WorkflowEngine::runToCompletion()` — اجرای تا اولین wait/end
- `WorkflowEngine::stepToken()` — اجرای یک گام
- `WorkflowEngine::wakeUpToken()` — بیدار کردن token waiting
- **MAX_STEPS_PER_RUN=100** برای جلوگیری از infinite loops
- **Token split/join** برای parallel/inclusive gateways

### Service Tasks (Whitelist)
هیچ کد PHP بیرون از whitelist قابل اجرا نیست. Service Tasks built-in:
- `send_notification` — پل به Phase 3 NotificationDispatcher
- `create_task` — پل به Phase 3 CreateTaskAction
- `set_variable` — تنظیم متغیر در instance
- `log` — لاگ پیام در ProcessHistory و Laravel Log

### Expression Language (Sandboxed)
- استفاده از `symfony/expression-language` (نه eval)
- متغیرها در namespace `vars`
- توابع امن: `len()`, `now()`, `empty()`, `contains()`, `starts_with()`, `lower()`, `upper()`, `days_from_now()`

### Custom BPMN Extensions (mms: namespace)
- `<mms:assignee>` — کاربر یا expression
- `<mms:candidateUsers>` / `<mms:candidateGroups>` — کاربران/نقش‌های واجد
- `<mms:dueDate>` — مهلت با expression
- `<mms:formSchema>` — JSON schema فرم
- `<mms:serviceTaskClass>` — کلید service task
- `<mms:serviceTaskConfig>` — پیکربندی به‌صورت key-value

### Incident Handling
- خطاهای runtime در `process_incidents` ذخیره می‌شوند
- token مرتبط در `waiting` قرار می‌گیرد
- ادمین می‌تواند incident را resolve کند با گزینه `retry`

### Background Jobs
- `WorkflowTimerJob` — هر دقیقه (`workflow:timer-tick`) — wake کردن timerهای سررسیده
- `WorkflowSlaCheckerJob` — هر ساعت (`workflow:sla-check`) — تشخیص SLA breach

### Filament UI Components
- 3 Resource: ProcessDefinitionResource، ProcessInstanceResource، UserTaskResource
- 7 RelationManager: Elements، Instances، Tokens، UserTasks، Variables، History، Incidents
- 3 Page: BpmnDesignerPage، MyWorkflowTasksPage، WorkflowMonitorPage
- 3 Widget: ActiveInstancesWidget، OpenIncidentsWidget، MyPendingWorkflowTasksWidget

### پوشش تست
**۹ فایل تست Pest** برای فاز ۴:
- `BpmnXmlParserTest` — پارس، اعتبارسنجی، extension elements
- `ExpressionEvaluatorTest` — sandbox safety، توابع، throw on invalid
- `ServiceTaskRegistryTest` — whitelist، duplicate prevention
- `ProcessHistoryAppendOnlyTest` — ممنوعیت update/delete
- `ProcessDefinitionStateMachineTest` — 3-layer defence
- `WorkflowEngineTest` — linear، userTask، gateway، incident، set_variable
- `DeployProcessActionTest` — versioning، is_latest، hash
- `UserTaskAuthorizationTest` — claim by assignee/candidate/role
- `VariablesServiceTest` — type inference، scope precedence

## مستندات بیشتر

- [docs/INSTALLATION.md](docs/INSTALLATION.md) — نصب کامل
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — معماری سامانه
- [docs/SECURITY.md](docs/SECURITY.md) — مدل امنیتی
- [docs/PHASE1.md](docs/PHASE1.md) — جزئیات فاز ۱
- [docs/PHASE2.md](docs/PHASE2.md) — جزئیات فاز ۲ (Meetings & Calendar)
- [docs/PHASE3.md](docs/PHASE3.md) — جزئیات فاز ۳ (Post-Meeting: Minutes, Resolutions, Tasks)
- [docs/PHASE4.md](docs/PHASE4.md) — جزئیات فاز ۴ (BPMN Workflow Engine)
- [docs/ROADMAP.md](docs/ROADMAP.md) — نقشه راه ۶ فازی

## مجوز

این پروژه برای استفاده داخلی سازمانی توسعه داده شده است.

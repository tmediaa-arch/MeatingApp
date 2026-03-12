<?php
declare(strict_types=1);
namespace App\Filament\Resources\TaskResource\Pages;

use App\Domains\Tasks\Actions\AssignTaskAction;
use App\Domains\Tasks\Actions\CompleteTaskAction;
use App\Domains\Tasks\Actions\RequestExtensionAction;
use App\Domains\Tasks\Actions\SubmitTaskAction;
use App\Domains\Tasks\Actions\TransitionTaskStatusAction;
use App\Domains\Tasks\Actions\UpdateTaskProgressAction;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Organization\Models\Employee;
use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('assign')
                ->label('ارجاع')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->visible(fn () => auth()->user()->can('assign', $this->record)
                    && $this->record->status === TaskStatus::Open)
                ->form([
                    Forms\Components\Select::make('assignee_employee_id')
                        ->label('مجری')
                        ->relationship('assignee', 'first_name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('supervisor_employee_id')
                        ->label('ناظر')
                        ->relationship('supervisor', 'first_name')
                        ->searchable()
                        ->preload(),
                ])
                ->action(function (array $data) {
                    $assignee = Employee::findOrFail($data['assignee_employee_id']);
                    $supervisor = !empty($data['supervisor_employee_id'])
                        ? Employee::find($data['supervisor_employee_id'])
                        : null;
                    app(AssignTaskAction::class)->execute($this->record, $assignee, $supervisor);
                    Notification::make()->success()->title('ارجاع شد')->send();
                }),

            Actions\Action::make('updateProgress')
                ->label('به‌روزرسانی پیشرفت')
                ->icon('heroicon-o-chart-bar')
                ->color('primary')
                ->visible(fn () => $this->record->canBeUpdatedBy(auth()->user()))
                ->form([
                    Forms\Components\TextInput::make('progress_percent')
                        ->label('درصد پیشرفت')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->required(),
                    Forms\Components\Textarea::make('comment')->label('توضیح')->rows(2),
                ])
                ->action(function (array $data) {
                    app(UpdateTaskProgressAction::class)->execute(
                        $this->record,
                        (int) $data['progress_percent'],
                        $data['comment'] ?? null,
                    );
                    Notification::make()->success()->title('به‌روز شد')->send();
                }),

            Actions\Action::make('submit')
                ->label('ارسال')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => auth()->user()->can('submit', $this->record)
                    && in_array($this->record->status, [TaskStatus::InProgress, TaskStatus::NeedsRevision]))
                ->form([
                    Forms\Components\Textarea::make('result_summary')
                        ->label('خلاصه نتایج')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data) {
                    app(SubmitTaskAction::class)->execute(
                        $this->record,
                        auth()->user(),
                        $data['result_summary'],
                    );
                    Notification::make()->success()->title('ارسال شد')->send();
                }),

            Actions\Action::make('approve')
                ->label('تأیید')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => auth()->user()->can('approve', $this->record)
                    && in_array($this->record->status, [TaskStatus::Submitted, TaskStatus::UnderReview]))
                ->form([
                    Forms\Components\Select::make('quality')
                        ->label('کیفیت')
                        ->options([
                            'excellent' => 'عالی',
                            'good' => 'خوب',
                            'acceptable' => 'قابل قبول',
                            'poor' => 'ضعیف',
                        ])
                        ->required(),
                    Forms\Components\Textarea::make('comment')->label('نظر تأییدکننده')->rows(2),
                ])
                ->action(function (array $data) {
                    app(CompleteTaskAction::class)->execute(
                        $this->record,
                        auth()->user(),
                        $data['quality'],
                        $data['comment'] ?? null,
                    );
                    Notification::make()->success()->title('تأیید شد')->send();
                }),

            Actions\Action::make('needsRevision')
                ->label('نیاز به اصلاح')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => auth()->user()->can('approve', $this->record)
                    && in_array($this->record->status, [TaskStatus::Submitted, TaskStatus::UnderReview]))
                ->form([
                    Forms\Components\Textarea::make('reason')->label('علت برگشت')->rows(3)->required(),
                ])
                ->action(function (array $data) {
                    app(TransitionTaskStatusAction::class)->execute(
                        $this->record,
                        TaskStatus::NeedsRevision,
                        $data['reason'],
                    );
                    Notification::make()->warning()->title('برگشت داده شد')->send();
                }),

            Actions\Action::make('requestExtension')
                ->label('درخواست تمدید')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->visible(fn () => auth()->user()->can('requestExtension', $this->record))
                ->form([
                    Forms\Components\DatePicker::make('new_due_date')
                        ->label('مهلت جدید')
                        ->required()
                        ->after('today'),
                    Forms\Components\Textarea::make('reason')->label('دلیل')->rows(3)->required(),
                ])
                ->action(function (array $data) {
                    app(RequestExtensionAction::class)->execute(
                        $this->record,
                        auth()->user(),
                        new \DateTimeImmutable($data['new_due_date']),
                        $data['reason'],
                    );
                    Notification::make()->success()->title('درخواست ثبت شد')->send();
                }),
        ];
    }
}

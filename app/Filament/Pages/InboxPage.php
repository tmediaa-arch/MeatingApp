<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\Notifications\Models\NotificationOutbox;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InboxPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;
    protected static ?string $navigationLabel = 'کارتابل';
    protected static ?string $title = 'کارتابل من';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.inbox';

    public ?string $activeTab = 'unread';

    public function mount(): void
    {
        $this->activeTab = 'unread';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\IconColumn::make('priority')
                    ->label('')
                    ->width('10px')
                    ->state(fn ($r) => match ($r->priority) {
                        'critical', 'high' => Heroicon::MiniExclamationCircle,
                        default => Heroicon::OutlinedBell,
                    })
                    ->color(fn ($r) => match ($r->priority) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('subject')
                    ->label('عنوان')
                    ->weight(fn ($record) => $record->read_in_inbox ? 'normal' : 'bold')
                    ->limit(50),

                Tables\Columns\TextColumn::make('body')
                    ->label('متن')
                    ->limit(80)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('زمان')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notifiable_type')
                    ->label('نوع')
                    ->formatStateUsing(fn ($state) => match (class_basename($state)) {
                        'Meeting' => 'جلسه',
                        'Minute' => 'صورتجلسه',
                        'Resolution' => 'مصوبه',
                        'Task' => 'وظیفه',
                        default => '—',
                    })
                    ->badge(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label('مشاهده')
                        ->icon(Heroicon::OutlinedEye)
                        ->action(function (NotificationOutbox $record) {
                            $record->markAsRead();

                            if ($record->notifiable) {
                                return redirect($this->getNotifiableUrl($record));
                            }
                        }),

                    Action::make('markAsRead')
                        ->label('خوانده شده')
                        ->icon(Heroicon::OutlinedCheck)
                        ->visible(fn (NotificationOutbox $record) => !$record->read_in_inbox)
                        ->action(fn (NotificationOutbox $record) => $record->markAsRead()),

                    Action::make('archive')
                        ->label('بایگانی')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('gray')
                        ->action(fn (NotificationOutbox $record) => $record->archive()),
                ]),
            ])
            ->groupedBulkActions([
                BulkAction::make('markAllRead')
                    ->label('علامت‌گذاری به‌عنوان خوانده')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->action(function ($records) {
                        $records->each(fn ($r) => $r->markAsRead());
                        Notification::make()->success()->title('انجام شد')->send();
                    }),
                BulkAction::make('archiveAll')
                    ->label('بایگانی همه')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->action(function ($records) {
                        $records->each(fn ($r) => $r->archive());
                        Notification::make()->success()->title('بایگانی شدند')->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([20, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        $query = NotificationOutbox::query()->forInbox($user);

        return match ($this->activeTab) {
            'unread' => $query->unread(),
            'archived' => NotificationOutbox::query()
                ->where('recipient_user_id', $user->id)
                ->where('archived_in_inbox', true),
            default => $query,
        };
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    private function getNotifiableUrl(NotificationOutbox $notification): ?string
    {
        if (!$notification->notifiable_type || !$notification->notifiable_id) return null;

        $resourceMap = [
            'App\Domains\Meetings\Models\Meeting' => 'meetings',
            'App\Domains\Minutes\Models\Minute' => 'minutes',
            'App\Domains\Resolutions\Models\Resolution' => 'resolutions',
            'App\Domains\Tasks\Models\Task' => 'tasks',
        ];

        $slug = $resourceMap[$notification->notifiable_type] ?? null;
        if (!$slug) return null;

        return route("filament.admin.resources.{$slug}.view", $notification->notifiable_id);
    }

    public function getUnreadCount(): int
    {
        return NotificationOutbox::query()
            ->forInbox(auth()->user())
            ->unread()
            ->count();
    }
}

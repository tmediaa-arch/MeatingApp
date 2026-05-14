<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\VideoConference\Actions\CheckProviderHealthAction;
use App\Domains\VideoConference\Enums\HealthStatus;
use App\Domains\VideoConference\Enums\VideoConferenceRoomStatus;
use App\Domains\VideoConference\Models\VideoConferenceProvider;
use App\Domains\VideoConference\Models\VideoConferenceRoom;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class VideoConferenceMonitorPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;
    protected string $view = 'filament.pages.vc-monitor';
    protected static string|\UnitEnum|null $navigationGroup = 'ویدئوکنفرانس';
    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'مانیتورینگ';
    }

    public function getTitle(): string
    {
        return 'مانیتورینگ ویدئوکنفرانس';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('vc_provider.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VideoConferenceRoom::query()
                    ->active()
                    ->with(['provider', 'meeting'])
                    ->latest('scheduled_start_at'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('subject')->label('موضوع')->limit(40),
                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Provider')
                    ->badge(),
                Tables\Columns\TextColumn::make('driver')
                    ->label('Driver')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
                Tables\Columns\TextColumn::make('meeting.subject')->label('جلسه')->placeholder('—'),
                Tables\Columns\TextColumn::make('attendances_count')
                    ->label('شرکت‌کنندگان')
                    ->counts('attendances'),
                Tables\Columns\TextColumn::make('scheduled_start_at')
                    ->label('شروع برنامه')
                    ->dateTime('Y/m/d H:i'),
                Tables\Columns\TextColumn::make('actual_start_at')
                    ->label('شروع واقعی')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('sync')
                        ->label('Sync وضعیت')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('info')
                        ->action(function (VideoConferenceRoom $room) {
                            app(\App\Domains\VideoConference\Services\VideoConferenceService::class)
                                ->syncStatus($room);
                            Notification::make()->title('وضعیت به‌روز شد')->success()->send();
                        }),
                    Action::make('end')
                        ->label('پایان دادن')
                        ->icon(Heroicon::OutlinedStopCircle)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (VideoConferenceRoom $r) => $r->status === VideoConferenceRoomStatus::InProgress)
                        ->action(function (VideoConferenceRoom $room) {
                            app(\App\Domains\VideoConference\Services\VideoConferenceService::class)
                                ->endRoom($room);
                            Notification::make()->title('اتاق پایان یافت')->warning()->send();
                        }),
                ]),
            ]);
    }

    public function getProvidersHealth(): array
    {
        return VideoConferenceProvider::query()
            ->active()
            ->get()
            ->map(fn ($p) => [
                'name' => $p->name,
                'driver' => $p->driver->value,
                'status' => $p->health_status->label(),
                'color' => $p->health_status->color(),
                'last_check' => $p->last_health_check_at?->diffForHumans() ?? '—',
                'active_rooms' => $p->activeRooms()->count(),
            ])
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('refresh_all_health')
                ->label('بررسی سلامت همه Providerها')
                ->icon(Heroicon::OutlinedHeart)
                ->color('info')
                ->action(function () {
                    $count = 0;
                    foreach (VideoConferenceProvider::active()->get() as $p) {
                        app(CheckProviderHealthAction::class)->execute($p);
                        $count++;
                    }
                    Notification::make()
                        ->title("{$count} provider بررسی شد")
                        ->success()
                        ->send();
                }),
        ];
    }
}

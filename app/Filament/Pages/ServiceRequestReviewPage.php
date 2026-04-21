<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\ServiceRequests\Actions\ApproveServiceRequestAction;
use App\Domains\ServiceRequests\Actions\RejectServiceRequestAction;
use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Enums\ServiceRequestType;
use App\Domains\ServiceRequests\Models\ServiceRequest;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ServiceRequestReviewPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';
    protected static string $view = 'filament.pages.service-request-review';
    protected static ?string $navigationGroup = 'درخواست‌های جانبی';
    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return 'صف بررسی';
    }

    public function getTitle(): string
    {
        return 'صف درخواست‌های در انتظار بررسی';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('service_request.review') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ServiceRequest::query()
                    ->pendingReview()
                    ->orderBy('priority', 'desc')
                    ->orderBy('required_at', 'asc'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('request_number')->label('شماره'),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->color(fn (ServiceRequestType $t) => $t->color())
                    ->formatStateUsing(fn (ServiceRequestType $t) => $t->label()),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->limit(50),
                Tables\Columns\TextColumn::make('priority')
                    ->label('اولویت')
                    ->badge()
                    ->color(fn ($s) => match ($s) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'normal' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('required_at')
                    ->label('نیاز')
                    ->dateTime('Y/m/d H:i')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('requester.name')->label('درخواست‌کننده'),
                Tables\Columns\TextColumn::make('estimated_cost')->label('هزینه')->money('IRR', divideBy: 1),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('تأیید')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->form([Forms\Components\Textarea::make('comment')->label('یادداشت')])
                    ->action(function (ServiceRequest $r, array $data) {
                        app(ApproveServiceRequestAction::class)->execute($r, auth()->user(), $data['comment'] ?? null);
                        Notification::make()->title('تأیید شد')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('رد')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->form([Forms\Components\Textarea::make('reason')->label('دلیل')->required()])
                    ->action(function (ServiceRequest $r, array $data) {
                        app(RejectServiceRequestAction::class)->execute($r, auth()->user(), $data['reason']);
                        Notification::make()->title('رد شد')->warning()->send();
                    }),

                Tables\Actions\Action::make('view')
                    ->label('مشاهده')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ServiceRequest $r) => route(
                        'filament.admin.resources.service-requests.view',
                        ['record' => $r],
                    )),
            ]);
    }
}

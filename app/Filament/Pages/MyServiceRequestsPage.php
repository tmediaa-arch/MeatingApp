<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domains\ServiceRequests\Enums\ServiceRequestStatus;
use App\Domains\ServiceRequests\Enums\ServiceRequestType;
use App\Domains\ServiceRequests\Models\ServiceRequest;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyServiceRequestsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string $view = 'filament.pages.my-service-requests';
    protected static ?string $navigationGroup = 'درخواست‌های جانبی';
    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'درخواست‌های من';
    }

    public function getTitle(): string
    {
        return 'درخواست‌های جانبی من';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ServiceRequest::query()
                    ->where('requester_user_id', auth()->id())
                    ->latest('required_at'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('request_number')->label('شماره')->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->color(fn (ServiceRequestType $t) => $t->color())
                    ->formatStateUsing(fn (ServiceRequestType $t) => $t->label()),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (ServiceRequestStatus $s) => $s->color())
                    ->formatStateUsing(fn (ServiceRequestStatus $s) => $s->label()),
                Tables\Columns\TextColumn::make('required_at')
                    ->label('زمان نیاز')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewer.name')->label('بررسی‌کننده')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\Filter::make('open')
                    ->label('فقط باز')
                    ->query(fn (Builder $q) => $q->open())
                    ->default(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(ServiceRequestStatus::options()),
            ])
            ->actions([
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

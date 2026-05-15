<?php
declare(strict_types=1);
namespace App\Filament\Widgets;

use App\Domains\Minutes\Enums\MinuteStatus;
use App\Domains\Minutes\Models\Minute;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingSignaturesWidget extends BaseWidget
{
    protected static ?string $heading = 'صورتجلسات منتظر امضای من';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $userEmployeeId = auth()->user()->employee_id;
        if (!$userEmployeeId) {
            return $table->query(Minute::query()->whereRaw('1=0'));
        }

        return $table
            ->query(
                Minute::query()
                    ->whereIn('status', [MinuteStatus::Review->value, MinuteStatus::Signed->value])
                    ->where(function ($q) use ($userEmployeeId) {
                        $q->where('secretary_employee_id', $userEmployeeId)
                          ->whereNull('secretary_signed_at')
                          ->orWhere(function ($q2) use ($userEmployeeId) {
                              $q2->where('chairperson_employee_id', $userEmployeeId)
                                 ->whereNull('chairperson_signed_at');
                          });
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('minute_number')->label('شماره')->searchable(),
                Tables\Columns\TextColumn::make('title')->label('عنوان')->limit(40),
                Tables\Columns\TextColumn::make('meeting.meeting_number')->label('جلسه'),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (MinuteStatus $s) => $s->color())
                    ->formatStateUsing(fn (MinuteStatus $s) => $s->label()),
                Tables\Columns\TextColumn::make('signing_role')
                    ->label('نقش من')
                    ->state(function ($r) {
                        $eid = auth()->user()->employee_id;
                        if ($eid === $r->secretary_employee_id) return 'دبیر';
                        if ($eid === $r->chairperson_employee_id) return 'رئیس';
                        return '—';
                    })
                    ->badge(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('مشاهده')
                    ->url(fn ($record) => route('filament.admin.resources.minutes.view', $record)),
            ]);
    }
}

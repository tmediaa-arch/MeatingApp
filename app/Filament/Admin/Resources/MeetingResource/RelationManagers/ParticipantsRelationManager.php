<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MeetingResource\RelationManagers;

use App\Domains\Meetings\Enums\AttendanceStatus;
use App\Domains\Meetings\Enums\InvitationStatus;
use App\Domains\Meetings\Enums\ParticipantRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';
    protected static ?string $title = 'شرکت‌کنندگان';
    protected static ?string $recordTitleAttribute = 'display_name';

    public function form(Form $form): Form
    {
        return $form->schema([
            Toggle::make('is_external')
                ->label('خارج از سازمان')
                ->live(),
            Select::make('employee_id')
                ->label('کارمند')
                ->relationship('employee', 'first_name')
                ->getOptionLabelFromRecordUsing(fn ($r) => $r->full_name)
                ->searchable(['first_name', 'last_name', 'personnel_code'])
                ->preload()
                ->visible(fn ($get) => !$get('is_external')),
            TextInput::make('external_full_name')
                ->label('نام کامل')
                ->visible(fn ($get) => $get('is_external')),
            TextInput::make('external_email')
                ->label('ایمیل')
                ->email()
                ->visible(fn ($get) => $get('is_external')),
            TextInput::make('external_mobile')
                ->label('موبایل')
                ->visible(fn ($get) => $get('is_external')),
            TextInput::make('external_organization')
                ->label('سازمان')
                ->visible(fn ($get) => $get('is_external')),
            Select::make('role')
                ->label('نقش')
                ->options(ParticipantRole::options())
                ->default(ParticipantRole::VotingMember->value)
                ->required(),
            Toggle::make('is_mandatory')
                ->label('حضور الزامی')
                ->default(true),
            TextInput::make('order_index')
                ->label('ترتیب نمایش')
                ->numeric()
                ->default(0),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('نام'),
                Tables\Columns\IconColumn::make('is_external')
                    ->label('خارجی')
                    ->boolean(),
                Tables\Columns\TextColumn::make('role')
                    ->label('نقش')
                    ->badge()
                    ->formatStateUsing(fn (ParticipantRole $state) => $state->label()),
                Tables\Columns\IconColumn::make('is_mandatory')
                    ->label('الزامی')
                    ->boolean(),
                Tables\Columns\TextColumn::make('invitation_status')
                    ->label('وضعیت دعوت')
                    ->badge()
                    ->color(fn (InvitationStatus $state) => $state->color())
                    ->formatStateUsing(fn (InvitationStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('attendance_status')
                    ->label('حضور')
                    ->badge()
                    ->color(fn (AttendanceStatus $state) => $state->color())
                    ->formatStateUsing(fn (AttendanceStatus $state) => $state->label())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('ایمیل')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('mobile')
                    ->label('موبایل')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order_index')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('افزودن شرکت‌کننده'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('record_attendance')
                    ->label('ثبت حضور')
                    ->icon('heroicon-o-check-circle')
                    ->form([
                        Select::make('attendance_status')
                            ->label('وضعیت حضور')
                            ->options(AttendanceStatus::options())
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        app(\App\Domains\Meetings\Actions\RecordAttendanceAction::class)
                            ->recordPresence(
                                $record,
                                AttendanceStatus::from($data['attendance_status']),
                            );

                        \Filament\Notifications\Notification::make()
                            ->title('حضور ثبت شد')
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => $this->ownerRecord->status->allowsAttendance()),
                Tables\Actions\DeleteAction::make()
                    ->action(function ($record) {
                        app(\App\Domains\Meetings\Actions\RemoveParticipantAction::class)
                            ->execute($record);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

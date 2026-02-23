<?php
declare(strict_types=1);
namespace App\Filament\Admin\Resources\RoomResource\Pages;
use App\Filament\Admin\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord {
    protected static string $resource = RoomResource::class;
    protected function getHeaderActions(): array {
        return [Actions\DeleteAction::make()];
    }
    public function getTitle(): string { return sprintf('ویرایش سالن: %s', $this->record->name); }
}

<?php
declare(strict_types=1);
namespace App\Filament\Admin\Resources\RoomResource\Pages;
use App\Filament\Admin\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRooms extends ListRecords {
    protected static string $resource = RoomResource::class;
    protected function getHeaderActions(): array {
        return [Actions\CreateAction::make()->label('ایجاد سالن')];
    }
}

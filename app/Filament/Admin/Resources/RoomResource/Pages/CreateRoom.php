<?php
declare(strict_types=1);
namespace App\Filament\Admin\Resources\RoomResource\Pages;
use App\Filament\Admin\Resources\RoomResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoom extends CreateRecord {
    protected static string $resource = RoomResource::class;
    public function getTitle(): string { return 'ایجاد سالن جدید'; }
}

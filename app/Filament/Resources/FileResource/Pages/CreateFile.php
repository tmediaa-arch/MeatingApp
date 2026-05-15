<?php
declare(strict_types=1);
namespace App\Filament\Resources\FileResource\Pages;

use App\Filament\Resources\FileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFile extends CreateRecord
{
    protected static string $resource = FileResource::class;

    protected function mutateDataBeforeCreate(array $data): array
    {
        // محاسبه hash و metadata
        if (!empty($data['storage_path']) && file_exists(storage_path('app/' . $data['storage_path']))) {
            $path = storage_path('app/' . $data['storage_path']);
            $data['file_name'] = basename($data['storage_path']);
            $data['original_file_name'] = $data['title'] ?? basename($data['storage_path']);
            $data['mime_type'] = mime_content_type($path);
            $data['file_size_bytes'] = filesize($path);
            $data['file_hash'] = hash_file('sha256', $path);
            $data['hash_algorithm'] = 'sha256';
            $data['extension'] = pathinfo($path, PATHINFO_EXTENSION);
        }
        $data['uploaded_by_user_id'] = auth()->id();
        $data['uploaded_at'] = now();
        $data['version'] = 1;
        return $data;
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\WebhookResource\Pages;

use App\Filament\Resources\WebhookResource;
use Filament\Resources\Pages\EditRecord;

class EditWebhook extends EditRecord
{
    protected static string $resource = WebhookResource::class;
}

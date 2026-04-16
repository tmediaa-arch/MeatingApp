<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DelegationResource\Pages;

use App\Filament\Admin\Resources\DelegationResource;
use Filament\Resources\Pages\EditRecord;

class EditDelegation extends EditRecord
{
    protected static string $resource = DelegationResource::class;
}

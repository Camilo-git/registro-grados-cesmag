<?php

namespace App\Filament\Resources\Ceremonies\Pages;

use App\Filament\Resources\Ceremonies\CeremonyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCeremony extends EditRecord
{
    protected static string $resource = CeremonyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

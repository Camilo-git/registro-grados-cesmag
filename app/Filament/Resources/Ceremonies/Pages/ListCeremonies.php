<?php

namespace App\Filament\Resources\Ceremonies\Pages;

use App\Filament\Resources\Ceremonies\CeremonyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCeremonies extends ListRecords
{
    protected static string $resource = CeremonyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

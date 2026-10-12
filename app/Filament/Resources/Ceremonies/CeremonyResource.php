<?php

namespace App\Filament\Resources\Ceremonies;

use App\Filament\Resources\Ceremonies\Pages\CreateCeremony;
use App\Filament\Resources\Ceremonies\Pages\EditCeremony;
use App\Filament\Resources\Ceremonies\Pages\ListCeremonies;
use App\Filament\Resources\Ceremonies\Schemas\CeremonyForm;
use App\Filament\Resources\Ceremonies\Tables\CeremoniesTable;
use App\Models\Ceremony;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CeremonyResource extends Resource
{
    protected static ?string $model = Ceremony::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return CeremonyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CeremoniesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCeremonies::route('/'),
            'create' => CreateCeremony::route('/create'),
            'edit' => EditCeremony::route('/{record}/edit'),
        ];
    }
}

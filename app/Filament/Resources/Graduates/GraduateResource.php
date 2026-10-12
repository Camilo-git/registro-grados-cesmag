<?php

namespace App\Filament\Resources\Graduates;

use App\Filament\Resources\Graduates\Pages\CreateGraduate;
use App\Filament\Resources\Graduates\Pages\EditGraduate;
use App\Filament\Resources\Graduates\Pages\ListGraduates;
use App\Filament\Resources\Graduates\Schemas\GraduateForm;
use App\Filament\Resources\Graduates\Tables\GraduatesTable;
use App\Models\Graduate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GraduateResource extends Resource
{
    protected static ?string $model = Graduate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'full_name_list';

    public static function form(Schema $schema): Schema
    {
        return GraduateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GraduatesTable::configure($table);
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
            'index' => ListGraduates::route('/'),
            'create' => CreateGraduate::route('/create'),
            'edit' => EditGraduate::route('/{record}/edit'),
        ];
    }
}

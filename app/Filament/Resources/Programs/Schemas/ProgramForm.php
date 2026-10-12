<?php

namespace App\Filament\Resources\Programs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProgramForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre del programa')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $state, callable $set) {
                        $set('key', Str::slug(Str::ascii($state), '_'));
                    }),
                TextInput::make('key')
                    ->label('Identificador interno')
                    ->disabled()
                    ->dehydrated()
                    ->helperText('Se genera solo a partir del nombre, para que coincida siempre con el importador.'),
            ]);
    }
}
<?php

namespace App\Filament\Resources\Ceremonies\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CeremonyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Nombre/hora de la ceremonia')
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
                DateTimePicker::make('starts_at')
                    ->label('Fecha y hora real del evento')
                    ->helperText('Se usa para validar la mayoría de edad de los invitados a esta fecha.'),
                TextInput::make('capacity')
                    ->label('Cupo de graduandos')
                    ->numeric()
                    ->default(null),
                Select::make('max_guests')
                    ->label('Cupo de invitados por graduando')
                    ->options([2 => '2 invitados', 3 => '3 invitados'])
                    ->required()
                    ->default(2),
                DateTimePicker::make('registration_opens_at')
                    ->label('Apertura de inscripción'),
                DateTimePicker::make('registration_closes_at')
                    ->label('Cierre de inscripción'),
            ]);
    }
}
<?php

namespace App\Filament\Resources\Guests\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GuestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('graduate_id')
                    ->label('Graduado')
                    ->relationship('graduate', 'full_name_list')
                    ->searchable()
                    ->required(),
                Select::make('slot')
                    ->label('Puesto de invitado')
                    ->options([1 => 'Invitado 1', 2 => 'Invitado 2', 3 => 'Invitado 3'])
                    ->required(),
                TextInput::make('first_names')
                    ->label('Nombres')
                    ->required(),
                TextInput::make('last_names')
                    ->label('Apellidos')
                    ->required(),
                TextInput::make('cedula')
                    ->label('Cédula')
                    ->required(),
                DatePicker::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->required(),
                TextInput::make('phone')
                    ->label('Celular')
                    ->tel()
                    ->required(),
                TextInput::make('address')
                    ->label('Dirección')
                    ->default(null),
                Toggle::make('has_disability')
                    ->label('¿Tiene discapacidad?')
                    ->required(),
                TextInput::make('disability_description')
                    ->label('¿Cuál discapacidad?')
                    ->default(null),
                TextInput::make('door_note')
                    ->label('Nota')
                    ->default(null),
            ]);
    }
}
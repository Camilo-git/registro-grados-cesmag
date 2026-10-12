<?php

namespace App\Filament\Resources\Graduates\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GraduateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Correo institucional')
                    ->email()
                    ->required(),
                TextInput::make('full_name_list')
                    ->label('Nombre (del listado oficial)')
                    ->required(),
                TextInput::make('first_names')
                    ->label('Nombres')
                    ->default(null),
                TextInput::make('last_names')
                    ->label('Apellidos')
                    ->default(null),
                TextInput::make('cedula')
                    ->label('Cédula')
                    ->default(null),
                DatePicker::make('birth_date')
                    ->label('Fecha de nacimiento'),
                TextInput::make('phone')
                    ->label('Celular')
                    ->tel()
                    ->default(null),
                TextInput::make('address')
                    ->label('Dirección')
                    ->default(null),
                Select::make('program_id')
                    ->label('Programa')
                    ->relationship('program', 'name')
                    ->required(),
                Select::make('ceremony_id')
                    ->label('Ceremonia')
                    ->relationship('ceremony', 'label')
                    ->default(null),
                TextInput::make('guest_quota_override')
                    ->label('Excepción de cupo de invitados')
                    ->helperText('Déjalo vacío para usar el cupo de la ceremonia (2 o 3). Solo llénalo para dar una excepción a este graduado en particular.')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
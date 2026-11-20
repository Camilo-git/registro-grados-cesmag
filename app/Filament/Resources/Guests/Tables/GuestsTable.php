<?php

namespace App\Filament\Resources\Guests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class GuestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('graduate.full_name_list')
                    ->label('Graduado')
                    ->searchable(),
                TextColumn::make('slot')
                    ->label('Puesto')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('first_names')
                    ->label('Nombres')
                    ->searchable(),
                TextColumn::make('last_names')
                    ->label('Apellidos')
                    ->searchable(),
                TextColumn::make('cedula')
                    ->label('Cédula')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Celular')
                    ->searchable(),

                // Mismo patrón que en GraduatesTable: una sola columna de
                // estado en vez de dos íconos sueltos (presente, agregado
                // en puerta). "Discapacidad" se deja aparte porque no es un
                // estado de progreso, es un dato informativo distinto.
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if ($record->attended_at !== null) {
                            return 'Presente';
                        }
                        if ($record->added_at_door) {
                            return 'Agregado en puerta';
                        }

                        return 'Registrado';
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Presente' => 'success',
                        'Agregado en puerta' => 'warning',
                        'Registrado' => 'gray',
                    }),

                TextColumn::make('has_disability')
                    ->label('Discapacidad')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->has_disability ? 'Sí' : 'No')
                    ->color(fn (string $state) => $state === 'Sí' ? 'info' : 'gray'),

                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('door_note')
                    ->label('Nota')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->groups([
                Group::make('graduate.full_name_list')
                    ->label('Graduado')
                    ->collapsible(),
            ])
            ->defaultGroup('graduate.full_name_list')
            ->defaultSort('slot')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
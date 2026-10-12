<?php

namespace App\Filament\Resources\Graduates\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GraduatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name_list')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('cedula')
                    ->label('Cédula')
                    ->searchable(),
                TextColumn::make('program.name')
                    ->label('Programa')
                    ->searchable(),
                TextColumn::make('ceremony.label')
                    ->label('Ceremonia')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Celular')
                    ->searchable(),
                IconColumn::make('profile_confirmed_at')
                    ->label('Perfil confirmado')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->profile_confirmed_at !== null),
                IconColumn::make('attended_at')
                    ->label('Asistió')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->attended_at !== null),
                TextColumn::make('guests_count')
                    ->label('Invitados')
                    ->counts('guests')
                    ->sortable(),
                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('removed_from_list_at')
                    ->label('Retirado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // Botón simple de asistencia SOLO para el graduado (sin tocar
                // invitados). Es el panel "solo graduados" que pediste desde
                // el inicio del proyecto, aparte del panel completo de la
                // página Attendance que cruza graduado + invitados.
                Action::make('toggleAttendance')
                    ->label(fn ($record) => $record->attended_at !== null ? 'Desmarcar asistencia' : 'Marcar asistencia')
                    ->icon(fn ($record) => $record->attended_at !== null ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->attended_at !== null ? 'danger' : 'success')
                    ->action(fn ($record) => $record->update([
                        'attended_at' => $record->attended_at !== null ? null : now(),
                        'checked_by' => $record->attended_at !== null ? null : auth()->id(),
                    ])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
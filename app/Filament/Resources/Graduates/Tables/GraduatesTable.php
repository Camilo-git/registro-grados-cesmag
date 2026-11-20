<?php

namespace App\Filament\Resources\Graduates\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

                // Columna "Estado": resume en una sola etiqueta de color lo
                // que antes eran dos íconos separados (perfil confirmado,
                // asistió). badge() la dibuja como una píldora de color,
                // más fácil de leer de un vistazo que varios íconos sueltos.
                // getStateUsing() calcula el texto; color() decide el color
                // según ESE MISMO texto (no se repite la lógica dos veces).
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if ($record->removed_from_list_at !== null) {
                            return 'Retirado';
                        }
                        if ($record->attended_at !== null) {
                            return 'Asistió';
                        }
                        if ($record->profile_confirmed_at !== null) {
                            return 'Inscrito';
                        }

                        return 'Pendiente';
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Asistió' => 'success',
                        'Inscrito' => 'info',
                        'Pendiente' => 'warning',
                        'Retirado' => 'gray',
                    }),

                // alignEnd(): alinea el número a la derecha, convención
                // estándar para que las cantidades sean fáciles de comparar
                // entre filas (en vez de quedar pegadas al texto de al lado).
                TextColumn::make('guests_count')
                    ->label('Invitados')
                    ->counts('guests')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Dirección')
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
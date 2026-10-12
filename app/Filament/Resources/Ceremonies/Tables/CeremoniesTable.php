<?php

namespace App\Filament\Resources\Ceremonies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CeremoniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // modifyQueryUsing: en vez de usar ->counts() (que solo sirve para
            // conteos simples), aquí armamos manualmente varios withCount() en
            // una sola consulta, incluidos conteos CONDICIONALES (solo los que
            // tienen attended_at). Esto evita traer cada graduado/invitado
            // completo a PHP solo para contarlos.
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'graduates',
                'graduates as graduates_attended_count' => fn ($q) => $q->whereNotNull('attended_at'),
                'guests',
                'guests as guests_attended_count' => fn ($q) => $q->whereNotNull('guests.attended_at'),
            ]))
            ->columns([
                TextColumn::make('label')
                    ->label('Ceremonia')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->label('Fecha real')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('max_guests')
                    ->label('Cupo invitados')
                    ->numeric(),

                // --- Bloque de graduandos de esta ceremonia ---
                TextColumn::make('graduates_count')
                    ->label('Graduandos inscritos')
                    ->sortable(),
                TextColumn::make('graduates_attended_count')
                    ->label('Graduandos que ingresaron')
                    ->sortable()
                    ->color('success'),
                // getStateUsing: no es una columna real de la base de datos,
                // se calcula al vuelo restando los dos conteos de arriba.
                TextColumn::make('graduates_pending')
                    ->label('Graduandos por ingresar')
                    ->getStateUsing(fn ($record) => $record->graduates_count - $record->graduates_attended_count)
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                // --- Bloque de invitados de esta ceremonia ---
                TextColumn::make('guests_count')
                    ->label('Invitados registrados')
                    ->sortable(),
                TextColumn::make('guests_attended_count')
                    ->label('Invitados que ingresaron')
                    ->sortable()
                    ->color('success'),
                TextColumn::make('guests_pending')
                    ->label('Invitados por ingresar')
                    ->getStateUsing(fn ($record) => $record->guests_count - $record->guests_attended_count)
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                TextColumn::make('capacity')
                    ->label('Cupo graduandos')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('key')
                    ->label('Identificador interno')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
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
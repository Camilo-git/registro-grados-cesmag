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

                // badge() con color fijo por valor (2 o 3): un cupo mayor
                // se resalta en amarillo, para que se note de un vistazo
                // qué ceremonias tienen la excepción de 3 invitados.
                TextColumn::make('max_guests')
                    ->label('Cupo invitados')
                    ->badge()
                    ->color(fn (int $state) => $state >= 3 ? 'warning' : 'gray'),

                TextColumn::make('graduates_count')
                    ->label('Graduandos')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('graduates_attended_count')
                    ->label('Ingresaron')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->color('success'),
                TextColumn::make('guests_count')
                    ->label('Invitados')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('guests_attended_count')
                    ->label('Ingresaron')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->color('success'),

                TextColumn::make('capacity')
                    ->label('Cupo graduandos')
                    ->numeric()
                    ->alignEnd()
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
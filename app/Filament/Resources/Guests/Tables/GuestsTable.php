<?php

namespace App\Filament\Resources\Guests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;



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
                IconColumn::make('has_disability')
                    ->label('Discapacidad')
                    ->boolean(),
                IconColumn::make('added_at_door')
                    ->label('Agregado en puerta')
                    ->boolean(),
                IconColumn::make('attended_at')
                    ->label('Asistió')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->attended_at !== null),
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
            ->groups([
            Group::make('graduate.full_name_list')
            ->label('Graduado')
            ->collapsible(),
            ])
            ->defaultGroup('graduate.full_name_list')
            ->defaultSort('slot')
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
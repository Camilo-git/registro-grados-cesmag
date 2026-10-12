<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Graduate;
use App\Models\Guest;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

// HasTable + InteractsWithTable: esto es lo que le permite a una página
// "suelta" (no un Resource) tener una tabla con búsqueda, orden y acciones,
// igual que las que ya viste en Graduate/Guest, pero con columnas y botones
// hechos completamente a mano.
class Attendance extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.attendance';

    protected static ?string $navigationLabel = 'Asistencia';

    public function table(Table $table): Table
    {
        return $table
            // Base: todos los graduados vigentes, con sus invitados ya
            // cargados de una vez (with) para no hacer una consulta nueva
            // por cada fila al armar las columnas de invitado 1/2/3.
            ->query(
                Graduate::query()
                    ->whereNull('removed_from_list_at')
                    ->with(['guests', 'ceremony'])
            )
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100, 'all'])
            ->columns([
                TextColumn::make('full_name_list')
                    ->label('Graduado')
                    // searchable con "query" propia: además de buscar en el
                    // propio nombre del graduado, también busca por nombre
                    // de CUALQUIERA de sus invitados (whereHas). Así, escribir
                    // el nombre de un invitado también encuentra la fila.
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('full_name_list', 'like', "%{$search}%")
                            ->orWhereHas('guests', fn ($q) => $q
                                ->where('first_names', 'like', "%{$search}%")
                                ->orWhere('last_names', 'like', "%{$search}%"));
                    }),

                TextColumn::make('cedula')
                    ->label('Cédula graduado')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('cedula', 'like', "%{$search}%")
                            ->orWhereHas('guests', fn ($q) => $q->where('cedula', 'like', "%{$search}%"));
                    }),

                TextColumn::make('ceremony.label')
                    ->label('Ceremonia'),

                IconColumn::make('graduate_present')
                    ->label('Graduado')
                    ->boolean()
                    ->getStateUsing(fn (Graduate $record) => $record->attended_at !== null),

                // Cada invitado (1, 2, 3) ahora se arma con self::guestAt(),
                // que busca una sola vez y se reutiliza en nombre + ícono.
                TextColumn::make('guest_1_name')
                    ->label('Invitado 1')
                    ->getStateUsing(fn (Graduate $record) => self::guestName(self::guestAt($record, 1))),
                IconColumn::make('guest_1_present')
                    ->label('Presente')
                    ->boolean()
                    ->getStateUsing(fn (Graduate $record) => optional(self::guestAt($record, 1))->attended_at !== null),

                TextColumn::make('guest_2_name')
                    ->label('Invitado 2')
                    ->getStateUsing(fn (Graduate $record) => self::guestName(self::guestAt($record, 2))),
                IconColumn::make('guest_2_present')
                    ->label('Presente')
                    ->boolean()
                    ->getStateUsing(fn (Graduate $record) => optional(self::guestAt($record, 2))->attended_at !== null),

                TextColumn::make('guest_3_name')
                    ->label('Invitado 3')
                    ->getStateUsing(fn (Graduate $record) => self::guestName(self::guestAt($record, 3))),
                IconColumn::make('guest_3_present')
                    ->label('Presente')
                    ->boolean()
                    ->getStateUsing(fn (Graduate $record) => optional(self::guestAt($record, 3))->attended_at !== null),
            ])
            ->filters([
                // toggle(): se muestra como un interruptor simple, no un
                // desplegable. default(true): queda activado desde que se
                // abre la página, sin que el operador tenga que recordarlo.
                Filter::make('pending')
                    ->label('Solo pendientes')
                    ->toggle()
                    ->default(true)
                    // "Pendiente" = el graduado NO asistió, O tiene al menos
                    // un invitado sin marcar.
                    ->query(fn ($query) => $query->where(
                        fn ($q) => $q->whereNull('attended_at')
                            ->orWhereHas('guests', fn ($g) => $g->whereNull('attended_at'))
                    )),
                // SelectFilter con relationship(): arma el desplegable solo,
                // trayendo las ceremonias existentes. Sin selección, muestra
                // todas.
                SelectFilter::make('ceremony_id')
                    ->label('Ceremonia')
                    ->relationship('ceremony', 'label'),
            ])
            ->recordActions([
                $this->toggleAction('graduado', fn (Graduate $r) => $r, 'Graduado'),
                $this->toggleAction('invitado1', fn (Graduate $r) => self::guestAt($r, 1), 'Invitado 1')
                    ->visible(fn (Graduate $r) => self::guestAt($r, 1) !== null),
                $this->toggleAction('invitado2', fn (Graduate $r) => self::guestAt($r, 2), 'Invitado 2')
                    ->visible(fn (Graduate $r) => self::guestAt($r, 2) !== null),
                $this->toggleAction('invitado3', fn (Graduate $r) => self::guestAt($r, 3), 'Invitado 3')
                    ->visible(fn (Graduate $r) => self::guestAt($r, 3) !== null),

                Action::make('markAllPresent')
                    ->label('Marcar todos presentes')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Graduate $record) {
                        // Marca al graduado y a TODOS sus invitados existentes
                        // de una sola vez, sin desmarcar a nadie que ya
                        // estuviera presente.
                        $this->markPresent($record);
                        foreach ($record->guests as $guest) {
                            $this->markPresent($guest);
                        }
                    }),
            ]);
    }

    /**
     * Único lugar que busca "el invitado en el puesto $slot de este
     * graduado". Antes esta misma línea estaba repetida entre 2 y 4 veces
     * por cada invitado (nombre, ícono, acción, visibilidad) — con el mismo
     * riesgo que vimos en el bug de guest_1_name: si alguien cambia algo
     * aquí, ahora solo hay un lugar que corregir, no cuatro.
     */
    private static function guestAt(Graduate $record, int $slot): ?Guest
    {
        return $record->guests->firstWhere('slot', $slot);
    }

    /** Nombre completo del invitado para mostrar en la tabla, o "—" si no existe. */
    private static function guestName(?Guest $guest): string
    {
        return $guest ? "{$guest->first_names} {$guest->last_names}" : '—';
    }

    /**
     * Crea un botón de "toggle" (marcar/desmarcar presente) reutilizable,
     * tanto para el graduado como para cada invitado. $resolve recibe la
     * fila de la tabla (siempre un Graduate) y devuelve el modelo REAL que
     * se va a marcar (el propio graduado, o uno de sus invitados).
     */
    private function toggleAction(string $name, \Closure $resolve, string $label): Action
    {
        return Action::make($name)
            ->label(fn (Graduate $record) => (optional($resolve($record))->attended_at !== null)
                ? "Desmarcar {$label}"
                : "Marcar {$label}")
            ->icon(fn (Graduate $record) => (optional($resolve($record))->attended_at !== null)
                ? 'heroicon-o-x-circle'
                : 'heroicon-o-check-circle')
            ->color(fn (Graduate $record) => (optional($resolve($record))->attended_at !== null) ? 'danger' : 'success')
            ->action(function (Graduate $record) use ($resolve) {
                $target = $resolve($record);
                if ($target === null) {
                    return;
                }
                $target->attended_at === null
                    ? $this->markPresent($target)
                    : $this->markAbsent($target);
            });
    }

    /** Marca presente (Graduate o Guest) y deja constancia en audit_logs. */
    private function markPresent(Graduate|Guest $record): void
    {
        $record->update(['attended_at' => now(), 'checked_by' => Auth::id()]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'graduate_id' => $record instanceof Graduate ? $record->id : $record->graduate_id,
            'event' => 'attendance_marked',
            'auditable_type' => $record::class,
            'auditable_id' => $record->id,
            'new_values' => ['attended_at' => now()->toDateTimeString()],
        ]);
    }

    /** Deshace una marca de asistencia (por si fue un error del operador). */
    private function markAbsent(Graduate|Guest $record): void
    {
        $old = $record->attended_at;
        $record->update(['attended_at' => null, 'checked_by' => null]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'graduate_id' => $record instanceof Graduate ? $record->id : $record->graduate_id,
            'event' => 'attendance_unmarked',
            'auditable_type' => $record::class,
            'auditable_id' => $record->id,
            'old_values' => ['attended_at' => $old?->toDateTimeString()],
        ]);
    }
}
<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Graduate;
use App\Models\Guest;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

// HasTable + InteractsWithTable: permiten mostrar la tabla en una página suelta.
// HasActions + InteractsWithActions: necesarios para que ->action() en las
// columnas ejecute algo al hacer clic (sin esto el clic no hace nada).
class Attendance extends Page implements HasTable, HasActions
{
    use InteractsWithTable;
    use InteractsWithActions;

    protected string $view = 'filament.pages.attendance';

    protected static ?string $navigationLabel = 'Asistencia';

    public function table(Table $table): Table
    {
        return $table
            // Base: graduados vigentes, con invitados y ceremonia ya cargados
            // de una vez (with) para no hacer una consulta nueva por cada fila.
            ->query(
                Graduate::query()
                    ->whereNull('removed_from_list_at')
                    ->with(['guests', 'ceremony'])
            )
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100, 'all'])
            ->columns([
                // Bloque del graduado: cédula arriba, nombre abajo. Un clic
                // ejecuta toggleGroup(): marca/desmarca al graduado Y a todos
                // sus invitados juntos. El fondo se pinta verde mientras el
                // graduado esté presente.
                TextColumn::make('cedula')
                    ->label('Graduado')
                    ->description(fn (Graduate $record) => $record->full_name_list)
                    // La búsqueda encuentra la fila por cédula o nombre del
                    // graduado, o por cédula o nombre de cualquiera de sus invitados.
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('cedula', 'like', "%{$search}%")
                            ->orWhere('full_name_list', 'like', "%{$search}%")
                            ->orWhereHas('guests', fn ($q) => $q
                                ->where('cedula', 'like', "%{$search}%")
                                ->orWhere('first_names', 'like', "%{$search}%")
                                ->orWhere('last_names', 'like', "%{$search}%"));
                    })
                    ->action(fn (Graduate $record) => $this->toggleGroup($record))
                    ->extraAttributes(fn (Graduate $record) => self::cellStyle($record->attended_at !== null)),

                TextColumn::make('ceremony.label')
                    ->label('Ceremonia'),

                // Invitados 1, 2 y 3: mismo patrón visual (cédula + nombre),
                // pero cada uno se marca de forma independiente: el clic no
                // afecta al graduado ni a los demás invitados.
                TextColumn::make('guest_1')
                    ->label('Invitado 1')
                    ->getStateUsing(fn (Graduate $record) => optional(self::guestAt($record, 1))->cedula ?? '')
                    ->description(fn (Graduate $record) => self::guestName(self::guestAt($record, 1)))
                    ->action(fn (Graduate $record) => $this->toggleIndividual(self::guestAt($record, 1)))
                    ->extraAttributes(fn (Graduate $record) => self::cellStyle(optional(self::guestAt($record, 1))->attended_at !== null)),

                TextColumn::make('guest_2')
                    ->label('Invitado 2')
                    ->getStateUsing(fn (Graduate $record) => optional(self::guestAt($record, 2))->cedula ?? '')
                    ->description(fn (Graduate $record) => self::guestName(self::guestAt($record, 2)))
                    ->action(fn (Graduate $record) => $this->toggleIndividual(self::guestAt($record, 2)))
                    ->extraAttributes(fn (Graduate $record) => self::cellStyle(optional(self::guestAt($record, 2))->attended_at !== null)),

                TextColumn::make('guest_3')
                    ->label('Invitado 3')
                    ->getStateUsing(fn (Graduate $record) => optional(self::guestAt($record, 3))->cedula ?? '')
                    ->description(fn (Graduate $record) => self::guestName(self::guestAt($record, 3)))
                    ->action(fn (Graduate $record) => $this->toggleIndividual(self::guestAt($record, 3)))
                    ->extraAttributes(fn (Graduate $record) => self::cellStyle(optional(self::guestAt($record, 3))->attended_at !== null)),

                // "Estado" refleja únicamente si el graduado está marcado.
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->getStateUsing(fn (Graduate $record) => $record->attended_at !== null ? 'Presente' : '')
                    ->color('success'),
            ])
            ->filters([
                // Interruptor activado por defecto: oculta a quien ya tiene
                // todo el grupo marcado (graduado + todos sus invitados).
                Filter::make('pending')
                    ->label('Solo pendientes')
                    ->toggle()
                    ->default(true)
                    ->query(fn ($query) => $query->where(
                        fn ($q) => $q->whereNull('attended_at')
                            ->orWhereHas('guests', fn ($g) => $g->whereNull('attended_at'))
                    )),
                SelectFilter::make('ceremony_id')
                    ->label('Ceremonia')
                    ->relationship('ceremony', 'label'),
            ]);
    }

    /** Encuentra al invitado en el puesto $slot de este graduado (o null si no existe). */
    private static function guestAt(Graduate $record, int $slot): ?Guest
    {
        return $record->guests->firstWhere('slot', $slot);
    }

    /** Nombre completo del invitado para mostrar bajo su cédula, o null si el puesto está vacío. */
    private static function guestName(?Guest $guest): ?string
    {
        return $guest ? "{$guest->first_names} {$guest->last_names}" : null;
    }

    /**
     * Atributos HTML para colorear una celda de asistencia.
     * Usa "style" en línea (no clases de Tailwind) a propósito: el CSS que
     * trae Filament ya viene compilado y no incluye clases inventadas por
     * nosotros, pero un style en línea siempre se aplica, sin recompilar.
     * El verde usa rgba (con transparencia) para verse bien en modo claro y
     * oscuro. display:block + padding hacen que el color rellene el bloque
     * y no solo el texto.
     */
    private static function cellStyle(bool $present): array
    {
        $base = 'display:block;padding:8px 10px;border-radius:6px;cursor:pointer;';

        return ['style' => $present
            ? $base.'background-color:rgba(34,197,94,0.25);'
            : $base];
    }

    /**
     * Clic en el bloque del GRADUADO: decide un solo estado nuevo (presente o
     * ausente, lo contrario de lo que tenía el graduado) y lo aplica a él y a
     * todos sus invitados existentes, para que el grupo quede siempre igual.
     */
    private function toggleGroup(Graduate $record): void
    {
        $markPresent = $record->attended_at === null;

        $markPresent ? $this->markPresent($record) : $this->markAbsent($record);

        foreach ($record->guests as $guest) {
            $markPresent ? $this->markPresent($guest) : $this->markAbsent($guest);
        }
    }

    /** Clic en el bloque de UN invitado: lo marca o desmarca solo a él. */
    private function toggleIndividual(?Guest $guest): void
    {
        if ($guest === null) {
            return; // Puesto vacío (ej. invitado 3 que no existe): nada que marcar.
        }

        $guest->attended_at === null ? $this->markPresent($guest) : $this->markAbsent($guest);
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
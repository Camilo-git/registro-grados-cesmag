<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Graduate;
use App\Models\Guest;
use App\Models\ImportBatch;
use App\Support\Normalize;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importa las respuestas exportadas de Google Forms (una fila = un graduado con sus invitados).
 * Cruza por el correo institucional verificado contra los graduados ya cargados del listado.
 */
class FormsResponsesImporter
{
    private const ADULT_AGE = 18;

    public function run(array $rows, string $filename, ?int $userId = null, bool $dryRun = true): array
    {
        $report = [
            'dry_run' => $dryRun, 'responses_total' => 0, 'responses_used' => 0, 'duplicates_discarded' => 0,
            'graduates_updated' => 0, 'guests_saved' => 0, 'guests_removed' => 0,
            'errors' => [], 'warnings' => [], 'unmatched' => [],
            'not_registered' => [], 'without_guests' => [], 'partial' => [],
        ];

        $err = function (int $line, string $email, string $msg) use (&$report) {
            $report['errors'][] = ['row' => $line, 'email' => $email, 'message' => $msg];
        };
        $warn = function (int $line, string $email, string $msg) use (&$report) {
            $report['warnings'][] = ['row' => $line, 'email' => $email, 'message' => $msg];
        };

        // ---- Encabezados ----
        if (count($rows) < 2) {
            $err(1, '', 'El archivo no tiene respuestas');

            return $report;
        }
        $map = $this->mapHeaders($rows[0]);
        if ($map['email'] === null) {
            $err(1, '', 'No se encontró la columna del correo electrónico');

            return $report;
        }
        $labels = ['first_names' => 'nombres', 'last_names' => 'apellidos', 'cedula' => 'cédula', 'birth_date' => 'fecha de nacimiento', 'phone' => 'celular'];
        foreach ($labels as $field => $label) {
            if (! isset($map['people'][0][$field])) {
                $err(1, '', "No se encontró la columna de {$label} del graduado");
            }
            if (! isset($map['people'][1][$field])) {
                $err(1, '', "No se encontró la columna de {$label} del invitado 1");
            }
        }
        if ($report['errors']) {
            return $report;
        }

        // ---- Respuestas: si un correo aparece varias veces, gana la última fila ----
        $responses = [];
        foreach (array_slice($rows, 1, null, true) as $i => $row) {
            $line = $i + 1; // la fila 1 es el encabezado
            $email = Normalize::email($this->str($row[$map['email']] ?? null));
            if ($email === '') {
                if (array_filter($row, fn ($v) => filled($v))) {
                    $err($line, '', 'Respuesta sin correo electrónico');
                }
                continue;
            }
            $report['responses_total']++;
            if (isset($responses[$email])) {
                $report['duplicates_discarded']++;
                $warn($responses[$email]['line'], $email, "Respuesta reemplazada por otra más reciente (fila {$line})");
            }
            $responses[$email] = ['line' => $line, 'row' => $row];
        }

        $graduates  = Graduate::with(['ceremony', 'guests'])->get()->keyBy('email');
        $gradCedula = Graduate::whereNotNull('cedula')->pluck('email', 'cedula')->all();
        $guestOwner = Guest::query()
            ->join('graduates', 'graduates.id', '=', 'guests.graduate_id')
            ->pluck('graduates.email', 'guests.cedula')->all();
        $failedEmails = [];

        // ---- Aplicar. En simulación se revierte para que los conteos sean idénticos a los reales ----
        DB::beginTransaction();
        try {
            $batch = ImportBatch::create(['filename' => $filename, 'user_id' => $userId]);

            foreach ($responses as $email => $resp) {
                $line = $resp['line'];
                $g = $graduates[$email] ?? null;

                if ($g === null) {
                    $report['unmatched'][] = ['row' => $line, 'email' => $email];
                    continue;
                }
                if ($g->removed_from_list_at !== null) {
                    $warn($line, $email, 'El graduado está marcado como retirado del listado');
                }

                $ref = $g->ceremony?->starts_at ?? now(); // mayoría de edad a la fecha de la ceremonia (o hoy)
                $people = $this->extractPeople($resp['row'], $map);

                // --- Graduado (columna 0): si algo falla, no se importa esta respuesta ---
                $me = $this->cleanPerson($people[0] ?? [], $ref, false);
                foreach ($me['warnings'] as $w) {
                    $warn($line, $email, 'Graduado: ' . $w);
                }
                $myErrors = array_map(fn ($m) => 'Graduado: ' . $m, $me['errors']);
                $myCed = $me['data']['cedula'];
                if ($myCed !== null) {
                    if (isset($gradCedula[$myCed]) && $gradCedula[$myCed] !== $email) {
                        $myErrors[] = "Graduado: la cédula ya pertenece a otro graduado ({$gradCedula[$myCed]})";
                    } elseif (isset($guestOwner[$myCed]) && $guestOwner[$myCed] !== $email) {
                        $warn($line, $email, "Su cédula figura como invitado de {$guestOwner[$myCed]}: revisar");
                    }
                }
                if ($myErrors) {
                    foreach ($myErrors as $m) {
                        $err($line, $email, $m);
                    }
                    $failedEmails[$email] = true;
                    continue;
                }
                if ($myCed !== null) {
                    $gradCedula[$myCed] = $email; // desde aquí, ningún invitado puede usar esta cédula
                }

                // --- Invitados ---
                $existing = $g->guests->keyBy('slot');
                $protected = $existing->filter(fn (Guest $x) => $x->added_at_door || $x->attended_at !== null);
                $quota = $g->guestQuota();
                $room = max(0, $quota - $protected->count());
                $accepted = [];
                $rejectedSlots = [];

                foreach ($people as $slot => $p) {
                    if ($slot === 0 || ! $this->present($p)) {
                        continue;
                    }
                    if ($protected->has($slot)) {
                        $warn($line, $email, "Invitado {$slot}: ese lugar ya tiene un invitado atendido o agregado en puerta; no se modificó");
                        continue;
                    }
                    $c = $this->cleanPerson($p, $ref, true);
                    foreach ($c['warnings'] as $w) {
                        $warn($line, $email, "Invitado {$slot}: " . $w);
                    }
                    $problems = $c['errors'];
                    $ced = $c['data']['cedula'];
                    if ($ced !== null) {
                        if (isset($gradCedula[$ced])) {
                            $problems[] = 'la cédula corresponde a un graduado (requiere revisión de la administración)';
                        } elseif (isset($guestOwner[$ced]) && $guestOwner[$ced] !== $email) {
                            $problems[] = "la cédula ya está inscrita como invitado de {$guestOwner[$ced]}";
                        } elseif (collect($accepted)->contains(fn ($a) => $a['data']['cedula'] === $ced)) {
                            $problems[] = 'cédula repetida entre sus invitados';
                        }
                    }
                    if (! $problems && count($accepted) >= $room) {
                        $problems[] = "excede el cupo de invitados ({$quota})";
                    }
                    if ($problems) {
                        foreach ($problems as $m) {
                            $err($line, $email, "Invitado {$slot}: {$m}");
                        }
                        $rejectedSlots[] = $slot; // se conserva lo que ya hubiera en ese lugar
                        continue;
                    }
                    $accepted[$slot] = $c;
                }

                // --- Guardar (con punto de restauración por graduado) ---
                try {
                    $result = DB::transaction(function () use ($g, $me, $accepted, $rejectedSlots, $existing, $protected, $userId) {
                        foreach (['phone', 'cedula'] as $f) {
                            $old = $g->getOriginal($f);
                            if ($old !== null && $old !== $me['data'][$f]) {
                                AuditLog::create([
                                    'user_id' => $userId,
                                    'graduate_id' => $g->id,
                                    'event' => $f . '_changed',
                                    'auditable_type' => Graduate::class,
                                    'auditable_id' => $g->id,
                                    'old_values' => [$f => $old],
                                    'new_values' => [$f => $me['data'][$f]],
                                ]);
                            }
                        }

                        $g->fill(Arr::only($me['data'], ['first_names', 'last_names', 'cedula', 'birth_date', 'phone']));
                        $g->address = $me['data']['address'] ?? $g->address;
                        $g->profile_confirmed_at ??= now();
                        $g->save();

                        $removed = 0;
                        foreach ($existing as $slot => $x) {
                            if ($protected->has($slot) || in_array($slot, $rejectedSlots, true)) {
                                continue;
                            }
                            if (! isset($accepted[$slot])) {
                                $removed++;
                            }
                            $x->delete();
                        }
                        foreach ($accepted as $slot => $c) {
                            Guest::create($c['data'] + ['graduate_id' => $g->id, 'slot' => $slot]);
                        }

                        return ['saved' => count($accepted), 'removed' => $removed];
                    });
                } catch (QueryException $ex) {
                    $err($line, $email, 'No se pudo guardar por un conflicto de datos únicos (cédula repetida). Revisar manualmente.');
                    $failedEmails[$email] = true;
                    continue;
                }

                foreach ($existing as $slot => $x) {
                    if (! $protected->has($slot) && ! in_array($slot, $rejectedSlots, true)) {
                        unset($guestOwner[$x->cedula]);
                    }
                }
                foreach ($accepted as $c) {
                    $guestOwner[$c['data']['cedula']] = $email;
                }

                $report['responses_used']++;
                $report['graduates_updated']++;
                $report['guests_saved'] += $result['saved'];
                $report['guests_removed'] += $result['removed'];
            }

            // ---- Listas de seguimiento según el estado de la base de datos tras la importación ----
            $all = Graduate::with(['ceremony', 'program'])->withCount('guests')
                ->whereNull('removed_from_list_at')
                ->orderBy('ceremony_id')->orderBy('full_name_list')->get();
            foreach ($all as $x) {
                $row = [
                    'email' => $x->email,
                    'name' => $x->full_name_list,
                    'ceremony' => $x->ceremony?->label,
                    'program' => $x->program?->name,
                    'phone' => $x->phone,
                    'guests' => $x->guests_count,
                    'quota' => $x->guestQuota(),
                    'note' => isset($failedEmails[$x->email]) ? 'Respondió, pero con errores' : '',
                ];
                if ($x->profile_confirmed_at === null) {
                    $report['not_registered'][] = $row;
                } elseif ($x->guests_count === 0) {
                    $report['without_guests'][] = $row;
                } elseif ($x->guests_count < $x->guestQuota()) {
                    $report['partial'][] = $row;
                }
            }

            $batch->update([
                'rows_total' => $report['responses_total'],
                'created_count' => $report['guests_saved'],
                'updated_count' => $report['graduates_updated'],
                'unchanged_count' => 0,
                'error_count' => count($report['errors']),
                'report' => $report,
            ]);

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $report;
    }

    // ------------------------------------------------------------------
    // Encabezados: detecta campo y persona (0 = graduado, 1..3 = invitados)
    // ------------------------------------------------------------------

    private function mapHeaders(array $header): array
    {
        $map = ['email' => null, 'ceremony' => null, 'program' => null, 'people' => []];
        $plainOcc = [];
        $guestOcc = [];

        foreach ($header as $idx => $h) {
            $key = Normalize::key($this->str($h));
            if ($key === '') {
                continue;
            }
            $field = $this->classify($key);
            if ($field === null || $field === 'timestamp') {
                continue;
            }
            if (in_array($field, ['email', 'ceremony', 'program'], true)) {
                $map[$field] ??= $idx; // primera columna que coincida
                continue;
            }

            $slot = $this->slotOf($key);
            if ($slot === null) {
                $guestOnlyField = in_array($field, ['has_disability', 'disability_desc'], true);
                if ($guestOnlyField || preg_match('/\b(invitad|acompan)/', $key)) {
                    $guestOcc[$field] = ($guestOcc[$field] ?? 0) + 1;
                    $slot = $guestOcc[$field];          // "del invitado" sin número: por orden de aparición
                } else {
                    $plainOcc[$field] = ($plainOcc[$field] ?? 0) + 1;
                    $slot = $plainOcc[$field] - 1;      // 1.ª vez = graduado, siguientes = invitados
                }
            }
            if ($slot < 0 || $slot > 3) {
                continue;
            }
            $map['people'][$slot][$field] ??= $idx;
        }
        ksort($map['people']);

        return $map;
    }

    private function classify(string $key): ?string
    {
        return match (true) {
            (bool) preg_match('/^(marca temporal|timestamp)/', $key) => 'timestamp',
            str_contains($key, 'correo') || str_contains($key, 'email') || str_contains($key, 'e mail') => 'email',
            str_contains($key, 'apellido') => 'last_names',
            str_contains($key, 'nombre') => 'first_names',
            (bool) preg_match('/cedula|documento|identificacion|\bcc\b/', $key) => 'cedula',
            str_contains($key, 'nacimiento') => 'birth_date',
            (bool) preg_match('/celular|telefono|whatsapp/', $key) => 'phone',
            (bool) preg_match('/direccion|residencia|domicilio/', $key) => 'address',
            str_contains($key, 'discapacidad') => (preg_match('/\bcual\b|describ|especific/', $key) ? 'disability_desc' : 'has_disability'),
            (bool) preg_match('/ceremonia|horario/', $key) => 'ceremony',
            str_contains($key, 'programa') => 'program',
            (bool) preg_match('/\bcual\b/', $key) => 'disability_desc',
            default => null,
        };
    }

    /** "Invitado 1 - Nombres", "Nombres del invitado 2", "Segundo invitado: cédula"... ("Primer apellido" NO cuenta). */
    private function slotOf(string $key): ?int
    {
        $words = ['uno' => 1, 'dos' => 2, 'tres' => 3, 'primer' => 1, 'primero' => 1, 'segundo' => 2, 'tercer' => 3, 'tercero' => 3];
        $pre = '\b(?:invitad[oa]|acompanante)s?\s*(?:(?:n|no|nro|num|numero)\s*)?';

        if (preg_match('/' . $pre . '([1-3])\b/', $key, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/' . $pre . '(uno|dos|tres)\b/', $key, $m)) {
            return $words[$m[1]];
        }
        if (preg_match('/\b(primer|primero|segundo|tercer|tercero)\s+(?:invitad|acompan)/', $key, $m)) {
            return $words[$m[1]];
        }

        return null;
    }

    private function extractPeople(array $row, array $map): array
    {
        $people = [];
        foreach ($map['people'] as $slot => $cols) {
            $p = [];
            foreach ($cols as $field => $idx) {
                $p[$field] = $row[$idx] ?? null;
            }
            $people[$slot] = $p;
        }

        return $people;
    }

    /** Un invitado "existe" si contestó alguno de sus datos principales. */
    private function present(array $p): bool
    {
        foreach (['first_names', 'last_names', 'cedula', 'birth_date', 'phone', 'address'] as $f) {
            if ($this->str($p[$f] ?? null) !== '') {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Limpieza y validación de una persona (graduado o invitado)
    // ------------------------------------------------------------------

    private function cleanPerson(array $p, Carbon $ref, bool $isGuest): array
    {
        $e = [];
        $w = [];

        $first = preg_replace('/\s+/', ' ', $this->str($p['first_names'] ?? null));
        $last = preg_replace('/\s+/', ' ', $this->str($p['last_names'] ?? null));
        if ($first === '') {
            $e[] = 'faltan los nombres';
        }
        if ($last === '') {
            $e[] = 'faltan los apellidos';
        }

        $cedRaw = $this->str($p['cedula'] ?? null);
        $ced = Normalize::digits($cedRaw);
        if ($ced === '') {
            $e[] = 'falta la cédula';
            $ced = null;
        } elseif (strlen($ced) < 6 || strlen($ced) > 10) {
            $e[] = "cédula inválida ({$cedRaw})";
            $ced = null;
        }

        $birth = $this->date($p['birth_date'] ?? null);
        if ($birth === null) {
            $e[] = 'fecha de nacimiento vacía o inválida (se espera día/mes/año)';
        } elseif (! $birth->copy()->addYears(self::ADULT_AGE)->lte($ref)) {
            $msg = 'menor de edad a la fecha de la ceremonia (' . $birth->format('d/m/Y') . ')';
            if ($isGuest) {
                $e[] = $msg;
            } else {
                $w[] = $msg;
            }
        }

        $phoneRaw = $this->str($p['phone'] ?? null);
        $phone = Normalize::phone($phoneRaw);
        if ($phone === null || ! preg_match('/^3\d{9}$/', $phone)) {
            $e[] = "celular inválido o vacío ({$phoneRaw})";
            $phone = null;
        }

        $address = $this->str($p['address'] ?? null);
        $address = $address === '' ? null : $address;

        $desc = $this->str($p['disability_desc'] ?? null);
        if ($desc === '' || in_array(Normalize::key($desc), ['ninguna', 'ninguno', 'no', 'no aplica', 'n a', 'na', 'nada'], true)) {
            $desc = null;
        }
        $hasDis = $this->bool($p['has_disability'] ?? null) || $desc !== null;
        if ($hasDis && $desc === null) {
            $w[] = 'indicó discapacidad pero no dijo cuál';
        }

        return [
            'data' => [
                'first_names' => $first,
                'last_names' => $last,
                'cedula' => $ced,
                'birth_date' => $birth?->toDateString(),
                'phone' => $phone,
                'address' => $address,
                'has_disability' => $hasDis,
                'disability_description' => $hasDis ? $desc : null,
            ],
            'errors' => $e,
            'warnings' => $w,
        ];
    }

    private function str(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_bool($v)) {
            return $v ? '1' : '';
        }
        if (is_float($v) && floor($v) == $v) {
            return (string) (int) $v; // celdas numéricas de Excel (cédula, celular)
        }

        return trim((string) $v);
    }

    private function bool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }

        return (bool) preg_match('/^(si|s|yes|true|1|x)\b/', Normalize::key($this->str($v)));
    }

    /** Acepta día/mes/año (Colombia), año-mes-día y fechas de Excel (número de serie). */
    private function date(mixed $v): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }

        $d = null;
        try {
            if ($v instanceof \DateTimeInterface) {
                $d = Carbon::instance($v)->startOfDay();
            } elseif (is_int($v) || is_float($v)) {
                $d = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->startOfDay();
            } else {
                $s = trim((string) $v);
                if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})#', $s, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                    $d = Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();
                } elseif (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $s, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                    $d = Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return ($d !== null && $d->year >= 1900 && $d->lte(now())) ? $d : null;
    }
}

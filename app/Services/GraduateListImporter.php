<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Graduate;
use App\Models\ImportBatch;
use App\Models\Program;
use App\Support\Normalize;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class GraduateListImporter
{
    /** Encabezados aceptados (en formato slug, como los entrega Laravel Excel). */
    private const ALIASES = [
        'name'     => ['nombre', 'nombres', 'nombre_del_graduado', 'nombre_completo', 'graduado'],
        'email'    => ['correo', 'correo_institucional', 'email', 'e_mail'],
        'ceremony' => ['ceremonia', 'hora_de_ceremonia', 'hora_ceremonia', 'horario', 'hora'],
        'program'  => ['programa', 'programa_academico'],
        'phone'    => ['celular', 'telefono', 'numero_de_celular', 'cel'],
        'cedula'   => ['cedula', 'cedula_de_ciudadania', 'documento', 'identificacion', 'cc'],
    ];

    /**
     * @param  array  $rows        filas con encabezados como llaves (Excel::toArray(...)[0])
     * @param  bool   $dryRun      true = simula: hace todo dentro de una transacción y la revierte
     * @param  bool   $markMissing marca como "retirados" a quienes no estén en el archivo
     *                             (usar SOLO con el listado completo)
     */
    public function run(array $rows, string $filename, ?int $userId = null, bool $dryRun = true, bool $markMissing = false): array
    {
        $report = [
            'dry_run' => $dryRun, 'rows_total' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0,
            'protected_skipped' => 0, 'marked_missing' => 0,
            'new_programs' => [], 'new_ceremonies' => [], 'errors' => [], 'warnings' => [],
        ];

        // Columnas obligatorias presentes
        if ($rows !== []) {
            $headers = array_keys($rows[0]);
            $required = ['name' => 'nombre', 'email' => 'correo institucional', 'ceremony' => 'hora de ceremonia', 'program' => 'programa'];
            foreach ($required as $field => $label) {
                if (! array_intersect(self::ALIASES[$field], $headers)) {
                    $report['errors'][] = ['row' => 1, 'email' => '', 'message' => "No se encontró la columna «{$label}»"];
                }
            }
            if ($report['errors']) {
                return $report;
            }
        }

        $programs    = Program::pluck('id', 'key')->all();
        $ceremonies  = Ceremony::pluck('id', 'key')->all();
        $existing    = Graduate::all()->keyBy('email');
        $cedulaOwner = Graduate::whereNotNull('cedula')->pluck('email', 'cedula')->all();

        $emailsInFile = [];
        $cedulasInFile = [];
        $valid = [];
        $newPrograms = [];
        $newCeremonies = [];

        // ---- 1. Validar todas las filas (nada se escribe todavía) ----
        foreach ($rows as $i => $raw) {
            $line = $i + 2; // la fila 1 es el encabezado
            $mapped = $this->mapColumns($raw);
            if (! array_filter($mapped, fn ($v) => filled($v))) {
                continue; // fila vacía
            }
            $report['rows_total']++;

            ['data' => $d, 'errors' => $errors, 'warnings' => $warnings] = $this->clean($mapped);
            $email = $d['email'];

            foreach ($warnings as $w) {
                $report['warnings'][] = ['row' => $line, 'email' => $email, 'message' => $w];
            }

            if ($email !== '') {
                if (isset($emailsInFile[$email])) {
                    $errors[] = "Correo repetido en el archivo (ya aparece en la fila {$emailsInFile[$email]})";
                } else {
                    $emailsInFile[$email] = $line; // visto, aunque la fila tenga otros errores
                }
            }

            if ($d['cedula'] !== null) {
                if (isset($cedulaOwner[$d['cedula']]) && $cedulaOwner[$d['cedula']] !== $email) {
                    $errors[] = "La cédula ya pertenece a otro graduado ({$cedulaOwner[$d['cedula']]})";
                } elseif (isset($cedulasInFile[$d['cedula']])) {
                    $errors[] = "Cédula repetida en el archivo (fila {$cedulasInFile[$d['cedula']]})";
                } else {
                    $cedulasInFile[$d['cedula']] = $line;
                }
            }

            if ($errors) {
                foreach ($errors as $e) {
                    $report['errors'][] = ['row' => $line, 'email' => $email, 'message' => $e];
                }
                continue;
            }

            $pk = Normalize::key($d['program']);
            $ck = Normalize::key($d['ceremony']);
            if (! isset($programs[$pk])) {
                $newPrograms[$pk] = $d['program'];
            }
            if (! isset($ceremonies[$ck])) {
                $newCeremonies[$ck] = $d['ceremony'];
            }
            $valid[] = $d + ['program_key' => $pk, 'ceremony_key' => $ck];
        }

        $report['new_programs'] = array_values($newPrograms);
        $report['new_ceremonies'] = array_values($newCeremonies);

        $missing = $markMissing
            ? $existing->filter(fn ($g) => ! isset($emailsInFile[$g->email]) && $g->removed_from_list_at === null)
            : collect();
        $report['marked_missing'] = $missing->count();

        // ---- 2. Aplicar. En simulación se revierte para que los conteos sean idénticos a los reales ----
        DB::beginTransaction();
        try {
            $batch = ImportBatch::create(['filename' => $filename, 'user_id' => $userId]);

            foreach ($newPrograms as $key => $name) {
                $programs[$key] = Program::create(['name' => $name, 'key' => $key])->id;
            }
            foreach ($newCeremonies as $key => $label) {
                $ceremonies[$key] = Ceremony::create(['label' => $label, 'key' => $key])->id;
            }

            foreach ($valid as $row) {
                $attrs = [
                    'full_name_list' => $row['name'],
                    'program_id' => $programs[$row['program_key']],
                    'ceremony_id' => $ceremonies[$row['ceremony_key']],
                    'last_import_id' => $batch->id,
                    'removed_from_list_at' => null,
                ];
                $g = $existing[$row['email']] ?? null;

                if ($g === null) {
                    Graduate::create($attrs + ['email' => $row['email'], 'phone' => $row['phone'], 'cedula' => $row['cedula']]);
                    $report['created']++;
                    continue;
                }

                if ($g->profile_confirmed_at === null) {
                    $attrs['phone'] = $row['phone'] ?? $g->phone;
                    $attrs['cedula'] = $row['cedula'] ?? $g->cedula;
                } elseif (
                    ($row['phone'] !== null && $row['phone'] !== $g->phone)
                    || ($row['cedula'] !== null && $row['cedula'] !== $g->cedula)
                ) {
                    // El estudiante ya confirmó sus datos: no se pisa lo que él editó.
                    $report['protected_skipped']++;
                }

                $g->fill($attrs);
                $meaningful = $g->isDirty(['full_name_list', 'program_id', 'ceremony_id', 'phone', 'cedula']);

                if ($g->profile_confirmed_at !== null && $g->isDirty(['program_id', 'ceremony_id'])) {
                    AuditLog::create([
                        'user_id' => $userId,
                        'graduate_id' => $g->id,
                        'event' => 'list_reassigned',
                        'auditable_type' => Graduate::class,
                        'auditable_id' => $g->id,
                        'old_values' => Arr::only($g->getOriginal(), ['program_id', 'ceremony_id']),
                        'new_values' => Arr::only($g->getAttributes(), ['program_id', 'ceremony_id']),
                    ]);
                }

                $g->save();
                $meaningful ? $report['updated']++ : $report['unchanged']++;
            }

            if ($missing->isNotEmpty()) {
                Graduate::whereIn('id', $missing->pluck('id'))->update(['removed_from_list_at' => now()]);
            }

            $batch->update([
                'rows_total' => $report['rows_total'],
                'created_count' => $report['created'],
                'updated_count' => $report['updated'],
                'unchanged_count' => $report['unchanged'],
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

    private function mapColumns(array $raw): array
    {
        $out = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $raw)) {
                    $out[$field] = $raw[$alias];
                    break;
                }
            }
        }

        return $out;
    }

    private function clean(array $r): array
    {
        $errors = [];
        $warnings = [];

        $email = Normalize::email($this->str($r['email'] ?? null));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Correo vacío o con formato inválido';
        } elseif (! in_array(substr(strrchr($email, '@'), 1), config('graduation.email_domains'), true)) {
            $errors[] = 'El correo no es del dominio institucional';
        }

        $name = trim(preg_replace('/\s+/', ' ', $this->str($r['name'] ?? null)));
        if ($name === '') {
            $errors[] = 'Falta el nombre';
        }

        $program = trim(preg_replace('/\s+/', ' ', $this->str($r['program'] ?? null)));
        if ($program === '') {
            $errors[] = 'Falta el programa';
        }

        $ceremony = $this->ceremonyLabel($r['ceremony'] ?? null);
        if ($ceremony === null) {
            $errors[] = 'Falta la hora de ceremonia';
        }

        $cedula = null;
        $rawCedula = $this->str($r['cedula'] ?? null);
        if ($rawCedula !== '') {
            $digits = Normalize::digits($rawCedula);
            if (strlen($digits) < 6 || strlen($digits) > 10) {
                $errors[] = "Cédula con formato inválido ({$rawCedula})";
            } else {
                $cedula = $digits;
            }
        }

        $rawPhone = $this->str($r['phone'] ?? null);
        $phone = Normalize::phone($rawPhone);
        if ($phone !== null && ! preg_match('/^3\d{9}$/', $phone)) {
            $warnings[] = "Celular no válido ({$rawPhone}): se dejó vacío para que el estudiante lo complete";
            $phone = null;
        }

        return [
            'data' => compact('email', 'name', 'program', 'ceremony', 'cedula', 'phone'),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function str(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_float($v) && floor($v) == $v) {
            return (string) (int) $v; // celdas numéricas de Excel (cédula, celular)
        }

        return trim((string) $v);
    }

    /** Texto ("10:00 a. m.") o celda de hora de Excel (fracción del día) => etiqueta de ceremonia. */
    private function ceremonyLabel(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('H:i');
        }
        if (is_int($v) || is_float($v)) {
            $dt = ExcelDate::excelToDateTimeObject((float) $v);

            return $v < 1 ? $dt->format('H:i') : $dt->format('Y-m-d H:i');
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}

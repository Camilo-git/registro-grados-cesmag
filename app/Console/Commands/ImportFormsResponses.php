<?php

namespace App\Console\Commands;

use App\Imports\RawSheet;
use App\Services\FormsResponsesImporter;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportFormsResponses extends Command
{
    protected $signature = 'graduates:import-forms
        {file : Ruta al .xlsx (recomendado) o .csv exportado de las respuestas de Google Forms}
        {--commit : Guarda los cambios (sin esta opción solo se simula)}
        {--export= : Carpeta donde guardar los reportes en CSV (sin registrar, sin invitados, etc.)}';

    protected $description = 'Importa las respuestas de Google Forms (graduado + invitados) y reporta quién falta';

    public function handle(FormsResponsesImporter $importer): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $rows = Excel::toArray(new RawSheet, $path)[0] ?? [];
        $commit = (bool) $this->option('commit');

        $r = $importer->run($rows, basename($path), null, ! $commit);

        $commit
            ? $this->info('IMPORTACIÓN GUARDADA')
            : $this->warn('SIMULACIÓN: no se guardó nada. Usa --commit para guardar.');

        $this->table(
            ['Respuestas', 'Usadas', 'Reemplazadas', 'Invitados guardados', 'Invitados quitados', 'Errores'],
            [[$r['responses_total'], $r['responses_used'], $r['duplicates_discarded'], $r['guests_saved'], $r['guests_removed'], count($r['errors'])]]
        );
        $this->line('Graduados SIN REGISTRAR:            ' . count($r['not_registered']));
        $this->line('Registrados SIN invitados:          ' . count($r['without_guests']));
        $this->line('Con menos invitados que su cupo:    ' . count($r['partial']));
        $this->line('Respondieron pero NO están en el listado: ' . count($r['unmatched']));

        foreach ($r['errors'] as $e) {
            $this->error("Fila {$e['row']} ({$e['email']}): {$e['message']}");
        }
        foreach ($r['warnings'] as $w) {
            $this->warn("Fila {$w['row']} ({$w['email']}): {$w['message']}");
        }

        if ($dir = $this->option('export')) {
            $this->exportReports($dir, $r);
        } else {
            $this->line('Tip: agrega --export=carpeta para guardar las listas de seguimiento en CSV.');
        }

        return self::SUCCESS;
    }

    private function exportReports(string $dir, array $r): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0775, true)) {
            $this->error("No pude crear la carpeta {$dir}");

            return;
        }

        $labels = [
            'email' => 'Correo', 'name' => 'Nombre', 'ceremony' => 'Ceremonia', 'program' => 'Programa',
            'phone' => 'Celular', 'guests' => 'Invitados registrados', 'quota' => 'Cupo', 'note' => 'Nota',
            'row' => 'Fila', 'message' => 'Mensaje', 'type' => 'Tipo',
        ];
        $rename = fn (array $rows) => array_map(
            fn ($row) => array_combine(array_map(fn ($k) => $labels[$k] ?? $k, array_keys($row)), array_values($row)),
            $rows
        );

        $files = [
            'sin_registrar.csv' => $r['not_registered'],
            'sin_invitados.csv' => $r['without_guests'],
            'invitados_incompletos.csv' => $r['partial'],
            'no_en_listado.csv' => $r['unmatched'],
            'observaciones.csv' => array_merge(
                array_map(fn ($e) => ['type' => 'ERROR'] + $e, $r['errors']),
                array_map(fn ($w) => ['type' => 'AVISO'] + $w, $r['warnings'])
            ),
        ];

        foreach ($files as $name => $rows) {
            if ($rows === []) {
                continue;
            }
            $rows = $rename($rows);
            $fh = fopen(rtrim($dir, '/') . '/' . $name, 'w');
            fwrite($fh, "\xEF\xBB\xBF"); // BOM: Excel lee bien las tildes
            fputcsv($fh, array_keys($rows[0]), ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($fh, $row, ',', '"', '\\');
            }
            fclose($fh);
            $this->line("  → {$dir}/{$name} (" . count($rows) . ' filas)');
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\FormsResponsesImporter;
use App\Services\GraduateListImporter;
use App\Support\SheetRows;
use Illuminate\Console\Command;
use Revolution\Google\Sheets\Facades\Sheets;

class SyncGraduatesFromSheets extends Command
{
    protected $signature = 'graduates:sync-sheets
        {--commit : Guarda los cambios (sin esta opción solo se simula)}
        {--mark-missing : Marca como retirados a los graduados que ya no estén en la pestaña Graduados}
        {--export= : Carpeta donde guardar los reportes de seguimiento en CSV}
        {--only= : "graduados" o "respuestas" para sincronizar solo una pestaña (por defecto, ambas)}';

    protected $description = 'Sincroniza graduados e invitados directo desde Google Sheets (pestañas Graduados y Respuestas)';

    public function handle(GraduateListImporter $graduateImporter, FormsResponsesImporter $responsesImporter): int
    {
        $spreadsheetId = config('graduation.spreadsheet_id');
        if (! $spreadsheetId) {
            $this->error('Falta GOOGLE_SHEETS_SPREADSHEET_ID en el .env');

            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');
        $only = $this->option('only');

        if ($only !== 'respuestas') {
            $this->syncGraduates($graduateImporter, $spreadsheetId, $commit);
        }
        if ($only !== 'graduados') {
            $this->syncResponses($responsesImporter, $spreadsheetId, $commit);
        }

        return self::SUCCESS;
    }

    private function syncGraduates(GraduateListImporter $importer, string $spreadsheetId, bool $commit): void
    {
        $this->info('--- Pestaña Graduados ---');

        $raw = Sheets::spreadsheet($spreadsheetId)
            ->sheet(config('graduation.graduates_sheet', 'Graduados'))
            ->all();

        $rows = SheetRows::toAssoc($raw);
        if ($rows === []) {
            $this->warn('La pestaña Graduados no tiene filas de datos todavía.');

            return;
        }

        $r = $importer->run($rows, 'Graduados (Sheets)', null, ! $commit, (bool) $this->option('mark-missing'));

        $commit ? $this->info('GRADUADOS: guardado') : $this->warn('GRADUADOS: simulación (usa --commit para guardar)');
        $this->table(
            ['Filas', 'Nuevos', 'Actualizados', 'Sin cambios', 'Errores', 'Retirados'],
            [[$r['rows_total'], $r['created'], $r['updated'], $r['unchanged'], count($r['errors']), $r['marked_missing']]]
        );
        foreach ($r['new_programs'] as $p) {
            $this->line("  + programa nuevo: {$p}");
        }
        foreach ($r['new_ceremonies'] as $c) {
            $this->line("  + ceremonia nueva: {$c}");
        }
        if ($r['protected_skipped'] > 0) {
            $this->line("  ! {$r['protected_skipped']} graduado(s) ya confirmaron sus datos: no se sobrescribió su celular/cédula.");
        }
        foreach ($r['warnings'] as $w) {
            $this->warn("Fila {$w['row']} ({$w['email']}): {$w['message']}");
        }
        foreach ($r['errors'] as $e) {
            $this->error("Fila {$e['row']} ({$e['email']}): {$e['message']}");
        }
    }

    private function syncResponses(FormsResponsesImporter $importer, string $spreadsheetId, bool $commit): void
    {
        $this->info('--- Pestaña Respuestas ---');

        $rows = Sheets::spreadsheet($spreadsheetId)
            ->sheet(config('graduation.responses_sheet', 'Respuestas'))
            ->all();

        if (count($rows) < 2) {
            $this->warn('La pestaña Respuestas no tiene respuestas todavía.');

            return;
        }

        $r = $importer->run($rows, 'Respuestas (Sheets)', null, ! $commit);

        $commit ? $this->info('RESPUESTAS: guardado') : $this->warn('RESPUESTAS: simulación (usa --commit para guardar)');
        $this->table(
            ['Respuestas', 'Usadas', 'Reemplazadas', 'Invitados guardados', 'Invitados quitados', 'Errores'],
            [[$r['responses_total'], $r['responses_used'], $r['duplicates_discarded'], $r['guests_saved'], $r['guests_removed'], count($r['errors'])]]
        );
        $this->line('Graduados SIN REGISTRAR:            '.count($r['not_registered']));
        $this->line('Registrados SIN invitados:          '.count($r['without_guests']));
        $this->line('Con menos invitados que su cupo:    '.count($r['partial']));
        $this->line('Respondieron pero NO están en el listado: '.count($r['unmatched']));
        foreach ($r['errors'] as $e) {
            $this->error("Fila {$e['row']} ({$e['email']}): {$e['message']}");
        }
        foreach ($r['warnings'] as $w) {
            $this->warn("Fila {$w['row']} ({$w['email']}): {$w['message']}");
        }

        if ($dir = $this->option('export')) {
            $this->exportReports($dir, $r);
        }
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
            $fh = fopen(rtrim($dir, '/').'/'.$name, 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, array_keys($rows[0]), ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($fh, $row, ',', '"', '\\');
            }
            fclose($fh);
            $this->line("  → {$dir}/{$name} (".count($rows).' filas)');
        }
    }
}

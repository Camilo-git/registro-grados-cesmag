<?php

namespace App\Console\Commands;

use App\Imports\ListSheet;
use App\Services\GraduateListImporter;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportGraduates extends Command
{
    protected $signature = 'graduates:import
        {file : Ruta al .xlsx o .csv}
        {--commit : Guarda los cambios (sin esta opción solo se simula)}
        {--mark-missing : Marca como retirados a quienes no estén en el archivo (solo con el listado completo)}';

    protected $description = 'Importa el listado de graduados (por defecto en modo simulación)';

    public function handle(GraduateListImporter $importer): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $rows = Excel::toArray(new ListSheet, $path)[0] ?? [];
        $commit = (bool) $this->option('commit');

        $r = $importer->run($rows, basename($path), null, ! $commit, (bool) $this->option('mark-missing'));

        $commit
            ? $this->info('IMPORTACIÓN GUARDADA')
            : $this->warn('SIMULACIÓN: no se guardó nada. Usa --commit para guardar.');

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

        return self::SUCCESS;
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Str;

class SheetRows
{
    /**
     * Convierte filas crudas de Google Sheets (primera fila = encabezados) en
     * arreglos asociativos con llaves "slug" (igual a como Laravel Excel
     * convierte los encabezados con WithHeadingRow: "Correo institucional"
     * => "correo_institucional"), para poder reutilizar GraduateListImporter
     * sin cambiarle ni una línea.
     */
    public static function toAssoc(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $headers = array_map(
            fn ($h) => Str::slug((string) $h, '_'),
            array_shift($rows)
        );

        return array_values(array_map(function (array $row) use ($headers) {
            $row = array_pad($row, count($headers), null);

            return array_combine($headers, array_slice($row, 0, count($headers)));
        }, $rows));
    }
}

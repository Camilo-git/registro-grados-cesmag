<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;

/** Lectura "cruda" (sin encabezados): Excel::toArray(new RawSheet, $ruta)[0] => lista de filas; la fila 0 son los encabezados. */
class RawSheet implements Import
{
}
<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/** Le indica a Laravel Excel que la fila 1 son encabezados (se convierten a slug: "Cédula" => "cedula"). */
class ListSheet implements Import, WithHeadingRow
{
}
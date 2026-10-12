{{-- x-filament-panels::page: el layout estándar que genera make:filament-page,
     con el menú y encabezado del panel ya incluidos.
     {{ $this->table }} es la línea que realmente dibuja la tabla con todas
     las columnas, búsqueda y botones que configuramos en Attendance.php.
     Sin esta línea, la página se ve "vacía" aunque el PHP esté bien. --}}
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
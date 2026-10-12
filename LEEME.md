# Base del sistema de graduandos: migraciones + importador del listado

## Instalación (en tu proyecto Laravel con PostgreSQL)
1. `composer require maatwebsite/excel`
2. Copia las carpetas `app/`, `config/` y `database/` dentro de tu proyecto.
3. En `.env` agrega `GRAD_EMAIL_DOMAINS=unicesmag.edu.co` (confirma el dominio real; acepta varios separados por coma).
4. `php artisan migrate`
5. Da rol admin a tu usuario (por defecto el rol es `operator`):
   `php artisan tinker` → `User::where('email','tu@correo')->update(['role'=>'admin']);`

## Importar el listado
- Simulación (no guarda nada): `php artisan graduates:import ruta/listado.xlsx`
- Guardar: `php artisan graduates:import ruta/listado.xlsx --commit`
- Con el listado **completo**, para marcar a quienes ya no aparecen: agrega `--mark-missing`
- Archivo de prueba: `samples/graduados_ejemplo.csv` (las filas 4 y 5 tienen errores a propósito).

## Columnas (fila 1 = encabezados; acepta variantes como "Correo", "Horario", "Teléfono")
Nombre del graduado · Correo institucional · Hora de ceremonia · Programa · Celular · Cédula (opcional)

## Comportamiento
- El correo institucional es la llave: volver a subir el listado actualiza sin duplicar.
- Programas y ceremonias que no existan se crean solos (comparación sin tildes ni mayúsculas); la simulación te los muestra antes.
- Los errores se reportan por fila (correo inválido o de otro dominio, correo o cédula repetidos, cédula mal formada); las filas válidas sí se importan.
- Celular inválido → advertencia y queda vacío para que el estudiante lo complete.
- Si el estudiante ya confirmó sus datos, un nuevo listado NO sobrescribe su celular ni su cédula. Si le cambia programa o ceremonia, queda registrado en `audit_logs`.
- Cada importación real queda en `import_batches` con su reporte.

---

# Importar las respuestas de Google Forms

Orden de trabajo: **1)** importa el listado de graduados (`graduates:import`), **2)** define el cupo por ceremonia
(por defecto 2; para 3: `php artisan tinker` → `App\Models\Ceremony::find(1)->update(['max_guests'=>3]);`),
**3)** importa las respuestas del Forms las veces que quieras (es acumulativo y seguro de repetir).

## Cómo exportar
En la hoja de respuestas de Google: **Archivo → Descargar → Microsoft Excel (.xlsx)** (mejor que CSV: respeta números y fechas).

## Comandos
- Simulación: `php artisan graduates:import-forms respuestas.xlsx`
- Guardar: `php artisan graduates:import-forms respuestas.xlsx --commit`
- Con listas de seguimiento en CSV (para abrir en Excel): agrega `--export=reportes`
  → `sin_registrar.csv`, `sin_invitados.csv`, `invitados_incompletos.csv`, `no_en_listado.csv`, `observaciones.csv`

## Cómo debe estar armado el formulario
- Recopilar correos **verificados** y limitar a usuarios de la organización (el correo es la llave del cruce).
- Un bloque de preguntas para el graduado y uno por invitado, con títulos como en `samples/respuestas_forms_ejemplo.csv`:
  `Invitado 1 - Nombres`, `Invitado 1 - Apellidos`, `... - Cédula`, `... - Fecha de nacimiento`, `... - Celular`,
  `... - Dirección (opcional)`, `... - ¿Tiene discapacidad?`, `... - ¿Cuál discapacidad?`
  (también reconoce "Nombres del invitado 2", "Segundo invitado: cédula", etc.)
- Para el graduado usa los mismos títulos sin "Invitado" (`Nombres`, `Apellidos`, `Cédula`, ...).
  La primera aparición de cada pregunta cuenta como graduado. "Primer apellido / Segundo apellido" no se confunden con invitados.
- Fechas en día/mes/año. Si el formulario también pregunta programa u horario, se ignoran (manda el listado).

## Reglas que aplica al importar
- Cruza por correo con los graduados del listado; si un correo responde varias veces, gana la última fila.
- Errores por persona (no se importa lo inválido, lo demás sí): datos faltantes, cédula mal formada, celular inválido (debe ser móvil de 10 dígitos),
  invitado menor de edad **a la fecha de la ceremonia** (o a hoy si aún no cargas la fecha), cédula repetida, cédula de un invitado que es graduado,
  invitado ya inscrito con otro graduado, invitados por encima del cupo.
- Si el graduado tiene errores, esa respuesta no se importa y aparece en `sin_registrar.csv` con la nota "Respondió, pero con errores".
- Al volver a importar se reemplazan los invitados que vinieron del Forms; **no se tocan** invitados ya atendidos ni agregados en puerta.
  Si una edición trae un invitado con error, se conserva el que ya estaba en ese lugar.
- Cambios de celular o cédula frente a lo que ya había quedan en `audit_logs`.
- Listas de seguimiento (se calculan sobre la base de datos ya actualizada): **sin registrar**, **registrados sin invitados**, **con menos invitados que su cupo**,
  y **respondieron pero no están en el listado**.

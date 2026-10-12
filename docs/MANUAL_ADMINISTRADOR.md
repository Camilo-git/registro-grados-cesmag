# Manual del Administrador — Sistema de Registro de Grados (Universidad CESMAG)

Bienvenido al manual de administración del sistema de registro de graduandos e invitados para las ceremonias de grado de la Universidad CESMAG. Este manual está dirigido al personal administrativo y operativo encargado de gestionar la plataforma a través del panel de control web (`/admin`).

---

## 1. Acceso al Panel de Administración

1. Abra su navegador web e ingrese a la dirección del sistema (por ejemplo: `http://localhost:8000/admin`).
2. Ingrese con su correo institucional y contraseña.
3. Dependiendo de su rol asignado (`admin` u `operator`), tendrá acceso a los módulos correspondientes.

---

## 2. Cómo Cargar y Actualizar el Listado de Graduados

El listado oficial de graduados es el punto de partida del sistema. Se puede actualizar de dos formas:

### Opción A: Sincronización directa desde Google Sheets (Recomendado)
El sistema está conectado a una hoja de Google Sheets configurada por el departamento de tecnología.
1. Asegúrese de que la pestaña **Graduados** contenga las columnas requeridas: *Nombre del graduado*, *Correo institucional*, *Hora de ceremonia*, *Programa*, *Celular* y *Cédula* (opcional).
2. Ejecute en la terminal del servidor el comando de sincronización:
   - Para simular y revisar cambios sin guardar: `php artisan graduates:sync-sheets --only=graduados`
   - Para guardar los cambios de forma definitiva: `php artisan graduates:sync-sheets --only=graduados --commit`
   - Si desea marcar automáticamente como retirados a quienes ya no aparezcan en la hoja: agregue `--mark-missing`.

### Opción B: Subiendo un archivo exportado
1. Exporte la hoja de Google Sheets a formato Microsoft Excel (`.xlsx`) o CSV.
2. Ejecute el comando Artisan correspondiente:
   - Simulación: `php artisan graduates:import ruta/listado.xlsx`
   - Guardar cambios: `php artisan graduates:import ruta/listado.xlsx --commit`

> **Nota importante:** El correo institucional es la llave única del sistema. Si un graduado ya confirmó sus datos mediante el formulario, el sistema protege su celular y cédula para no sobrescribir información ingresada por el estudiante.

---

## 3. Cómo Crear y Editar una Ceremonia

Para configurar las ceremonias y sus cupos de invitados:
1. En el menú lateral del panel de administración, haga clic en **Ceremonias**.
2. Para crear una nueva, presione **Crear ceremonia** (o edite una existente haciendo clic sobre ella).
3. Complete los campos principales:
   - **Nombre/hora de la ceremonia**: Por ejemplo, *"10:00 a. m."*. El sistema genera automáticamente el identificador interno.
   - **Fecha y hora real del evento**: Indique la fecha exacta (utilizada para validar automáticamente la mayoría de edad de los invitados).
   - **Cupo de invitados por graduando**: Seleccione **2 invitados** o **3 invitados** según corresponda.
   - Fechas opcionales de apertura y cierre de inscripciones.
4. Haga clic en **Guardar**.

> *Excepción individual de cupo:* Si un graduado en particular requiere un cupo especial (por ejemplo, 3 invitados en una ceremonia configurada para 2), puede editar al graduado específico en la sección **Graduandos** y rellenar el campo *"Excepción de cupo de invitados"*.

---

## 4. Cómo Leer el Panel de Estadísticas (Página Principal)

Al ingresar al panel de administración (`/admin`), la vista principal muestra un resumen en tiempo real del estado general del proceso:
- **Total de graduados**: Cantidad de estudiantes vigentes según el último listado importado.
- **Perfil confirmado**: Número y porcentaje de graduados que ya diligenciaron el formulario de registro de datos e invitados.
- **Aún no han registrado**: Graduados pendientes por completar su formulario.
- **Con un solo invitado**: Casos límite donde el graduado completó el formulario pero un invitado presentó errores de validación (cédula repetida, menor de edad, etc.).
- **Invitados/Graduandos que ya ingresaron**: Estadísticas de control de asistencia tomadas el día del evento en la puerta de acceso.

---

## 5. Cómo Usar la Página de Asistencia el Día del Evento

El día de la ceremonia, el personal de apoyo en puerta utiliza la página **Asistencia** en el panel de Filament (`/admin/attendance`).

1. Haga clic en **Asistencia** en el menú lateral. La vista cargará por defecto únicamente a los asistentes **pendientes** por ingresar para agilizar la búsqueda.
2. **Buscar**: Utilice la barra de búsqueda superior para encontrar rápidamente a un graduado escribiendo su **nombre, cédula** o incluso el **nombre/cédula de cualquiera de sus invitados**.
3. **Filtrar por ceremonia**: Use el desplegable superior para seleccionar exclusivamente la ceremonia en curso.
4. **Marcar asistencia individual**:
   - Para marcar la entrada del graduado, haga clic en el botón verde **Marcar Graduado**.
   - Para marcar la entrada de un invitado específico (Invitado 1, Invitado 2 o Invitado 3), haga clic en el botón correspondiente. El botón cambiará a rojo para permitir *desmarcar* en caso de un error operativo.
5. **Marcar todos presentes**: Si el graduado y todos sus invitados llegan juntos y desea agilizar el proceso de ingreso, utilice la acción en lote **Marcar todos presentes** en la fila del graduado para registrar la asistencia de todo el grupo con un solo clic.

---

## 6. Qué Hacer si un Graduado o Invitado no Aparece en el Sistema

- **El graduado no aparece**:
  1. Verifique que el estudiante aparezca en la pestaña **Graduados** del Google Sheets oficial y que se haya ejecutado la sincronización (`php artisan graduates:sync-sheets --commit`).
  2. Compruebe que no esté marcado con la fecha de retiro (`removed_from_list_at`). Si fue un error de sincronización, puede agregarlo manualmente desde el módulo **Graduandos** en el panel web.
- **El invitado no aparece o presenta problemas**:
  1. Verifique en el módulo **Invitados** si el registro fue rechazado por validación (por ejemplo, cédula duplicada o invitado menor de edad a la fecha de la ceremonia).
  2. Si es necesario autorizar un ingreso excepcional en puerta, el operador puede registrar o ajustar los datos directamente en el panel o verificar las notas de observación del reporte generado por el importador.

# Changelog — Sistema de Registro de Grados (Universidad CESMAG)

Todos los cambios notables de este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere al versionado semántico.

## [Unreleased] - 2026-10-11

### Agregado
- **Estructura base de datos**: Migraciones completas para usuarios (`role`), programas, ceremonias, lotes de importación (`import_batches`), graduandos (`graduates`), invitados (`guests`), registros de auditoría (`audit_logs`) y códigos de acceso OTP (`login_codes`).
- **Servicios de importación y sincronización**:
  - `GraduateListImporter`: Importación y actualización de listados de graduados desde archivos Excel/CSV y Google Sheets, con soporte para detección de programas/ceremonias nuevas y protección de datos confirmados.
  - `FormsResponsesImporter`: Procesamiento de respuestas de Google Forms (graduado e invitados), validación de cupos, mayoría de edad a la fecha de la ceremonia, unicidad de cédulas y generación de reportes de seguimiento en CSV (`sin_registrar.csv`, `sin_invitados.csv`, etc.).
- **Comandos Artisan**:
  - `graduates:import` para la carga de listados oficiales.
  - `graduates:import-forms` para la importación de respuestas del formulario.
  - `graduates:sync-sheets` para la sincronización directa bidireccional con Google Sheets.
- **Panel Administrativo (Filament v3)**:
  - Recursos y formularios de gestión para Programas, Ceremonias, Graduandos e Invitados.
  - Página personalizada de **Asistencia** en puerta, con búsqueda optimizada por graduado o invitado, filtros por ceremonia y opción de marcar asistencia individual o grupal ("Marcar todos presentes").
  - Widget estadístico en el panel principal (`GraduationStatsOverview`) con métricas en tiempo real de confirmados, pendientes y asistencia.
- **Documentación oficial**:
  - `README.md` actualizado con resumen, requisitos e instalación rápida.
  - `docs/MANUAL_ADMINISTRADOR.md` para operadores y administradores del panel web.
  - `docs/GUIA_RAPIDA_EVENTO.md` en formato checklist para el personal de puerta el día de la ceremonia.
  - `docs/POLITICA_DATOS.md` sobre el tratamiento de datos personales conforme a la Ley 1581 de 2012 (Colombia).

# Sistema de Registro y Asistencia de Grados — Universidad CESMAG

Sistema híbrido (Google Forms + Google Sheets + Laravel 13 + MySQL) diseñado para la gestión de graduandos, registro de invitados y toma de asistencia en tiempo real durante las ceremonias de grado de la **Universidad CESMAG**.

## Características Principales
- **Sincronización automatizada o por archivos**: Carga y actualización de listados de graduados y respuestas de Google Forms mediante comandos Artisan o sincronización directa con Google Sheets.
- **Gestión de Cupos Flexible**: Configuración de cupo de invitados por ceremonia (2 o 3) con soporte para excepciones individuales por graduando.
- **Validaciones Rigurosas**: Verificación de cédulas únicas, dominios de correo institucional válidos, mayoría de edad de los invitados a la fecha de la ceremonia y validación de números de celular móviles de 10 dígitos.
- **Panel Administrativo (Filament v3)**: Interfaz intuitiva para administradores y operadores (`/admin`), que incluye vistas estadísticas globales, gestión de programas, ceremonias, graduados, invitados y una vista de asistencia dual (graduado + invitados) con filtros rápidos.
- **Trazabilidad y Auditoría**: Registro detallado de cambios de perfil, excepciones y control de accesos en puerta mediante `audit_logs`.

---

## Requisitos del Sistema
- **PHP** >= 8.3 con extensiones requeridas por Laravel y PhpSpreadsheet.
- **Composer** (administrador de dependencias de PHP).
- **MySQL** o PostgreSQL (compatible con tipos relacionales y JSON).
- Node.js y npm (para compilación de assets de Filament).

---

## Instalación Rápida desde Cero

1. Clonar el repositorio y acceder a la carpeta del proyecto.
2. Instalar dependencias de PHP:
   ```bash
   composer install
   ```
3. Configurar el entorno:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
4. Configurar las credenciales de base de datos y Google Sheets en el archivo `.env` (`DB_*`, `GOOGLE_SHEETS_SPREADSHEET_ID`, etc.).
5. Ejecutar las migraciones:
   ```bash
   php artisan migrate
   ```
6. Compilar assets y levantar el servidor de desarrollo:
   ```bash
   npm install && npm run build
   php artisan serve
   ```

> 📌 Para una guía paso a paso detallada, consulte [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md).

---

## Cómo Correr el Proyecto en Desarrollo

Para iniciar el servidor local de desarrollo de Laravel:
```bash
php artisan serve
```
El panel de administración estará disponible en `http://127.0.0.1:8000/admin`.

---

## Enlaces a la Documentación

- **[Arquitectura Técnica](docs/ARQUITECTURA.md)**: Estructura de base de datos, servicios de importación y diseño del sistema.
- **[Manual del Administrador](docs/MANUAL_ADMINISTRADOR.md)**: Guía paso a paso para uso del panel de Filament, importación de datos y gestión de ceremonias.
- **[Guía Rápida de Evento](docs/GUIA_RAPIDA_EVENTO.md)**: Checklist exprés para el operador de asistencia el día de la ceremonia.
- **[Política de Tratamiento de Datos](docs/POLITICA_DATOS.md)**: Tratamiento y manejo de datos personales según la Ley 1581 de 2012 (Colombia).
- **[Historial de Cambios (Changelog)](CHANGELOG.md)**: Registro de versiones y actualizaciones del sistema.

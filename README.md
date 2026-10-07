# Sistema Escolar UVZ

Sistema universitario para la gestión escolar (admisión, vida académica, finanzas y portales)
desarrollado con:

- **Laravel 12**
- **PHP 8.2+**
- **Livewire 4**
- **Blade**
- **Tailwind CSS 4**
- **MySQL 8**
- **Pest**
- **Vite**

## Cobertura funcional principal

- admisión e inscripción
- alumnos
- programas y periodos
- planes de estudio
- materias y prerrequisitos
- docentes
- grupos y horarios
- matrícula por periodo
- carga académica
- evaluaciones
- calificaciones
- extraordinarios
- asistencia
- actas
- kardex e historial
- bajas
- equivalencias/revalidaciones
- becas y descuentos
- pagos y estado de cuenta
- servicio social
- prácticas profesionales
- egreso
- titulación
- portal docente
- portal alumno
- formatos PDF
- reportes operativos
- exportación CSV

## Roles

- **ADMIN**
- **ADMISIONES**
- **ADMINISTRACION**
- **CONTROL_ESCOLAR**
- **DOCENTE**
- **ALUMNO**

Cada rol ve sólo las pantallas y acciones que le corresponden; la autorización se resuelve en el
backend y es la única fuente de verdad.

## Datos

**Los datos incluidos para demostración son completamente sintéticos.** No contienen personas
reales, ni CURP, teléfonos, domicilios o información médica reales.

## Requisitos

- PHP 8.2 o superior con las extensiones de Laravel
- Composer
- MySQL 8 (o compatible)
- Node.js 20+ y npm

## Instalación local

1. Copiar el archivo de ejemplo de entorno (nunca se versiona el `.env` real):

   ```bash
   cp .env.example .env
   ```

2. Completar **localmente** en `.env` los valores que falten (`APP_KEY`, credenciales de la base
   de datos, etc.). El `.env.example` llega con valores de ejemplo vacíos y no incluye secretos.

3. Instalar dependencias y generar la clave de la aplicación:

   ```bash
   composer install
   php artisan key:generate
   ```

4. Crear la base de datos, ajustar `DB_*` en `.env` y ejecutar las migraciones (y opcionalmente
   los seeders de demostración):

   ```bash
   php artisan migrate
   php artisan migrate --seed
   ```

5. Compilar los assets:

   ```bash
   npm install
   npm run build
   ```

6. Arrancar el servidor de desarrollo:

   ```bash
   php artisan serve
   ```

## Pruebas

```bash
composer test
```

Ejecuta la suite completa con Pest.

## Documentación

El detalle de arquitectura, plan de implementación, roadmap y auditorías del proyecto vive en
[`docs/`](docs/).

## Licencia

Proyecto privado. Todos los derechos reservados.

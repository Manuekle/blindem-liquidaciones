# Blindem · Liquidaciones

Sistema de gestión de trabajadores, empresas, contratos laborales y liquidaciones en Colombia. Construido para la **prueba técnica** con un sistema visual de superficies neutras, Inter medium, formularios y tablas adaptables y exportación a Excel.

## Stack

- Laravel 12 / PHP 8.2+, Blade y CSS/JS vanilla (no necesita Node/Vite)
- PostgreSQL 16; pruebas adicionales en SQLite. Filtros con `whereLike` compatibles con los dialectos soportados por Laravel
- PhpSpreadsheet para XLSX con hoja de parámetros y fuentes
- Auth nativo Laravel, roles `admin` / `collaborator`, CSRF, validaciones server-side
- Docker y Docker Compose para ejecución local

## Funcionalidad

El motor de liquidación selecciona parámetros laborales por año, calcula automáticamente los días a partir de las fechas del contrato, deriva la indemnización del motivo de retiro, separa extras de recargos y conserva la consulta/exportación histórica. Consulta [reglas de cálculo](docs/REGLAS-DE-CALCULO.md) y [operación local](docs/OPERACION-LOCAL.md).

## Inicio rápido local (con Docker)

**Requisitos:** Docker Desktop/Engine y Docker Compose. No necesitas PHP, Composer ni Node en el equipo.

```bash
sh scripts/setup-local.sh
```

El script crea la configuración, conserva una clave existente, construye la imagen, espera la salud de los servicios y carga la demostración local. Abre **http://127.0.0.1:8000**.

| Rol | Correo | Contraseña demo |
|---|---|---|
| Administrador | `admin@blindem.test` | `Cambiar123!` |
| Colaborador | `colaborador@blindem.test` | `Cambiar123!` |

Los datos persisten en PostgreSQL y las sesiones sobreviven a recrear el contenedor. Para reconstruir cambios usa `docker compose up --build -d --wait`.

## Inicio rápido sin Docker

Requiere PHP 8.2+, Composer 2, PostgreSQL y extensiones `pdo_pgsql`, `zip`, `gd`, `intl`, `mbstring`, `xml`.

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configura DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan migrate --seed
php artisan serve
```

## Arquitectura

Todo corre en local con Docker Compose: un contenedor `app` (Apache + PHP 8.3 + Laravel 12) y un contenedor `postgres` con volumen persistente. No hay despliegue en la nube ni servicios externos; el puerto solo escucha en `127.0.0.1`. El motor de cálculo es una clase pura (`LaborSettlementCalculator`) que no toca la base de datos, y `SettlementInputs` le entrega los datos del contrato y la vigencia laboral del año de corte. Detalle en [operación local](docs/OPERACION-LOCAL.md).

## Permisos

| Operación | Colaborador | Administrador |
|---|:---:|:---:|
| Consultar dashboard | ✓ | ✓ |
| Consultar trabajadores, empresas y contratos | ✓ | ✓ |
| CRUD contratos | ✓ | ✓ |
| Calcular, exportar XLSX y guardar liquidación | ✓ | ✓ |
| Registrar y editar trabajadores | ✓ | ✓ |
| Eliminar trabajadores; CRUD empresas | — | ✓ |
| CRUD tipos documento, cargos, contratos y motivos de retiro | — | ✓ |

Validación con middleware `admin` y `auth`; las acciones restringidas no se exponen a colaboradores.

## Estructura

```text
app/
  Http/Controllers/      # autenticación, dashboard, CRUD y exportaciones
  Http/Middleware/       # restricción rol administrador
  Models/                # entidades Eloquent
  Services/LaborSettlementCalculator.php  # motor sin acoplamiento a base de datos
app/Models/LaborParameter.php # vigencias laborales administrables
database/migrations/    # modelo relacional, claves y restricciones
database/seeders/       # catálogos + datos de demostración
public/css/app.css      # tokens, componentes y diseño adaptable
public/js/app.js        # interacciones ligeras
resources/views/        # Blade / login / dashboard / formularios / detalle / liquidación
routes/web.php          # permisos y endpoints
scripts/verify_calculator.php  # pruebas autónomas PHP
tests/Unit/              # PHPUnit
```

## Sistema de diseño

La interfaz usa Inter medium local, tracking normal en lectura y `-0.04em` en títulos, sin logotipos ni mayúsculas sostenidas en las etiquetas. La escala es compacta: contenido de hasta 1080 px, formularios de hasta 800 px y campos de 12 px. La paleta usa fondo `#F5F5F5`, superficies `#FBFBFB` y azul `#2D56E9`; los iconos Lucide oficiales se sirven localmente con su licencia. Botones y campos comparten esquinas amplias y un acabado suave con brillo interior, bordes tenues `#ECECEE` y sombras cortas. El resumen agrupa las métricas y muestra los contratos recientes antes de los gráficos; cabecera, contenido y pie comparten alineación.

- [Especificación](docs/design-system.md): tipografía, paleta, espaciado y reglas por pantalla.
- [Guía visual](docs/design-system.html): componentes, campos, estados y ejemplo de liquidación.
- [Resumen](docs/ui-preview.html) y [acceso](docs/login-preview.html): vistas previas con datos de demostración.

Para revisar las vistas sin instalar PHP, desde la raíz del proyecto:

```bash
python3 -m http.server 8765 --bind 127.0.0.1
```

Abre `http://127.0.0.1:8765/docs/ui-preview.html`. Las vistas previas cargan `public/css/app.css` y `public/js/app.js`; no se conectan a la base de datos ni sustituyen la comprobación de los flujos de Laravel.

## Motor de liquidación

Valores enteros COP con redondeo HALF_UP por concepto. Los parámetros laborales por año incluyen salario mínimo, auxilio de referencia y tarifas. Las vigencias iniciales son 2023–2026; el administrador puede agregar las siguientes. Un año sin parámetros bloquea el cálculo.

| Concepto | Regla |
|---|---|
| Cesantías | Base prestacional × días / 360 |
| Intereses | Cesantías × días × tarifa anual / 360 |
| Prima | Base prestacional × días / 360 |
| Vacaciones pendientes | (Básico + recargos ordinarios) / 30 × días de descanso pendientes |
| Días automáticos | Si se dejan en blanco: vacaciones = 15 por cada 360 días de servicio − disfrutadas; salario y auxilio = días del último mes hasta la fecha de corte |
| Indemnización | Motivo de retiro y método asociado al tipo de contrato |
| Salud y pensión | Tarifa anual sobre salario básico pendiente; excluye vacaciones compensadas |

Las horas extras y pagos por descanso obligatorio se separan de los recargos ordinarios/nocturnos. Los registros antiguos con cifras combinadas deben revisarse antes de generar nuevos cálculos.

Los snapshots guardan identificación, entradas, tarifas, fuentes, resultado y versión. El historial permite abrir y descargar cada snapshot sin recalcularlo. Los contratos con historial se conservan.

La plantilla tiene diferencias de unidades y criterios en vacaciones, aportes e indemnización. [Reglas y casos de aceptación](docs/REGLAS-DE-CALCULO.md) explica las decisiones y compara los importes sin perpetuar errores de la plantilla.

### Fuentes jurídicas

- [Código Sustantivo del Trabajo, artículo 64](https://cancilleria.gov.co/normograma/compilacion/docs/codigo_sustantivo_trabajo.htm)
- [Decreto 159 de 2026, salario mínimo transitorio](https://normativa.colpensiones.gov.co/compilacion/docs/decreto_0159_2026.htm)
- [Decreto 1470 de 2025, auxilio de transporte para 2026](https://cancilleria.gov.co/normograma/compilacion/docs/decreto_1470_2025.htm)
- [Circular Externa 018 de 2012, vacaciones y aportes](https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=71322)
- [Ley 2466 de 2025, reforma laboral](https://normativa.colpensiones.gov.co/compilacion/docs/ley_2466_2025.htm)

## Pruebas

```bash
docker compose exec -T app vendor/bin/phpunit
docker compose exec -T postgres createdb -U blindem blindem_test
docker compose exec -T app vendor/bin/phpunit -c phpunit.postgres.xml
docker compose exec -T app php scripts/verify_calculator.php
docker compose exec -T app composer audit --no-dev
```

La base `blindem_test` se crea solo la primera vez. La clase base de pruebas bloquea bases ajenas al entorno de pruebas. Para backup y restauración de prueba consulta [operación local](docs/OPERACION-LOCAL.md).

## Limitaciones y siguientes pasos

- Las fórmulas cubren contratos laborales ordinarios y entradas explícitas; no sustituyen un sistema completo de nómina, PILA ni casos especiales.
- Se mantienen como mejoras independientes MFA, recuperación de contraseña y auditoría completa de cambios.

## Licencia

Proyecto de demostración técnica. No equivale a asesoría legal.

**Salario variable e indemnización:** el cálculo de indemnización usa salario básico más promedio de ingresos salariales variables reportados; en casos de salario variable se debe calcular el promedio con los pagos y tiempos efectivamente trabajados, conforme a la doctrina judicial (Sentencia SL1659-2025): https://cortesuprema.gov.co/corte-suprema-precisa-el-salario-base-para-liquidar-la-indemnizacion-por-despido-sin-justa-causa/

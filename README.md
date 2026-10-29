# Blindem · Liquidaciones

Sistema de gestión de trabajadores, empresas, contratos laborales y liquidaciones en Colombia. Construido para la **prueba técnica** con estilo visual minimalista premium (sidebar, tarjetas suaves, filtros, tablas legibles, diseño responsive) y exportación a Excel.

## Stack

- Laravel 12 / PHP 8.2+, Blade y CSS/JS vanilla (no necesita Node/Vite)
- PostgreSQL 16 (MySQL posible cambiando `DB_CONNECTION`, excepto filtros `ILIKE`)
- PhpSpreadsheet para XLSX con hoja de parámetros y fuentes
- Auth nativo Laravel, roles `admin` / `collaborator`, CSRF, validaciones server-side
- Docker y Docker Compose opcionales

## Inicio rápido local (con Docker)

**Requisitos:** Docker Engine/Compose.

```bash
# Genera una clave APP_KEY segura (una sola vez)
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;' 
# Pega el resultado en un .env que contenga APP_KEY=base64:...
cp .env.example .env
# En .env cambia DB_HOST=postgres, DB_DATABASE=blindem, DB_USERNAME=blindem,
# DB_PASSWORD=blindem_local; conserva SESSION_DRIVER=file.
# Docker Compose lee APP_KEY del .env

docker compose up --build -d
# Cargar datos de demostración SOLO EN DESARROLLO:
docker compose exec app php artisan db:seed
```

Abre **http://localhost:8000**.

Usuarios de demo (cambiar contraseña antes de exponer la aplicación a Internet):

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@blindem.test` | `Cambiar123!` |
| Colaborador | `colaborador@blindem.test` | `Cambiar123!` |

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

## Despliegue Railway

1. Crea proyecto de Railway con un servicio PostgreSQL.
2. Sube el repositorio privado y configura el servicio con el Dockerfile del proyecto.
3. Variables: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<dominio>`, `APP_KEY=<clave larga y aleatoria>` y `DB_CONNECTION=pgsql`, además de host, puerto, nombre, usuario y contraseña de PostgreSQL suministrados por Railway.
4. El contenedor ejecuta `php artisan migrate --force` al iniciar. **No ejecutar `db:seed` en producción:** crea usuarios por un procedimiento seguro.
5. Activa HTTPS y configura backups, políticas de contraseñas y límites antes de datos reales.

> Para producción se recomienda servidor web nginx/php-fpm u Octane; `artisan serve` en el Dockerfile está orientado a demostración/entrega técnica, no a alto tráfico.

## Permisos

| Operación | Colaborador | Administrador |
|---|:---:|:---:|
| Consultar dashboard | ✓ | ✓ |
| Consultar trabajadores, empresas y contratos | ✓ | ✓ |
| CRUD contratos | ✓ | ✓ |
| Calcular, exportar XLSX y guardar liquidación | ✓ | ✓ |
| CRUD trabajadores y empresas | — | ✓ |
| CRUD tipos documento, cargos, contratos y motivos de retiro | — | ✓ |

Validación con middleware `admin` y `auth`; las acciones restringidas no se exponen a colaboradores.

## Estructura

```text
app/
  Http/Controllers/      # autenticación, dashboard, CRUD y exportaciones
  Http/Middleware/       # restricción rol administrador
  Models/                # entidades Eloquent
  Services/LaborSettlementCalculator.php  # motor sin acoplamiento a base de datos
config/liquidations.php # SMLMV 2026 y tarifas de referencia
database/migrations/    # modelo relacional, claves y restricciones
database/seeders/       # catálogos + datos de demostración
public/css/app.css      # diseño responsive premium
public/js/app.js        # interacciones ligeras
resources/views/        # Blade / login / dashboard / formularios / detalle / liquidación
routes/web.php          # permisos y endpoints
scripts/verify_calculator.php  # pruebas autónomas PHP
tests/Unit/              # PHPUnit
```

## Motor de liquidación

Valores en COP, sin centavos (redondeo `HALF_UP`). Calcula:

| Concepto | Fórmula |
|---|---|
| Cesantías | `(salario + extras + auxilio habilitado) × días cesantías / 360` |
| Intereses | `cesantías × días cesantías × 0.12 / 360` |
| Prima | `base prestacional × días prima / 360` |
| Vacaciones **pendientes de disfrute** | `(salario + extras) / 30 × días de vacaciones pendientes` |
| Salario pendiente | `salario básico / 30 × días pendientes` |
| Transporte pendiente | `auxilio / 30 × días pendientes` |
| Indemnización indefinido | `<10 SMLMV: 30 días primer año +20 por año adicional (proporcional); >=10: 20+15; base salarial promedio que incluye extras salariales` |
| Indemnización fijo | `salario salarial promedio /30 × días restantes hasta finalización pactada` |
| Salud y pensión trabajador | `4%` cada uno sobre salario pendiente; se excluyen vacaciones compensadas |

**Importante:** el Excel original usa `base vacaciones × 15 /720 = $39.602` pero etiqueta esos 15 como **vacaciones pendientes**. Bajo esa definición, 15 días de vacaciones causadas equivalen a `base /30 × 15 = $950.453`; el otro divisor se usa cuando la cantidad introducida representa *días de servicio causantes*, y no días de vacaciones que faltan por disfrutar.

`cesantias_days` y `prima_days` pueden definirse explícitamente; vacíos aplican los días desde el inicio de su periodo correspondiente. Los días se cuentan mediante convención 30/360; la indemnización usa antigüedad calendario real. Casos particulares (salario integral, embargos, salarios variables complejos, incapacidades, horas extras no promedio, obra/labor, pactos colectivos, retención en fuente, aportes faltantes, cesantías ya consignadas o pagos anticipados) requieren ajustes y revisión profesional.

Los snapshots persistidos en `settlements` guardan **inputs, resultado y versión**, aunque se edite después el contrato.

### Fuentes jurídicas

- [Código Sustantivo del Trabajo, artículo 64](https://cancilleria.gov.co/normograma/compilacion/docs/codigo_sustantivo_trabajo.htm)
- [Decreto 159 de 2026, salario mínimo transitorio](https://normativa.colpensiones.gov.co/compilacion/docs/decreto_0159_2026.htm)
- [Decreto 1470 de 2025, auxilio de transporte para 2026](https://cancilleria.gov.co/normograma/compilacion/docs/decreto_1470_2025.htm)
- [Circular Externa 018 de 2012, vacaciones y aportes](https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=71322)
- [Ley 2466 de 2025, reforma laboral](https://normativa.colpensiones.gov.co/compilacion/docs/ley_2466_2025.htm)

## Pruebas

```bash
php scripts/verify_calculator.php    # sin Laravel ni Composer
php artisan test                      # requiere composer install
```

## Limitaciones y siguientes pasos

- Este proyecto entrega **código fuente**; el contenedor de autoría no dispone de Composer ni extensiones PHP de PostgreSQL/ZIP, así que no se ejecutaron migraciones ni flujo web end-to-end en este entorno.
- No genera el video de pantalla automáticamente. En `docs/GUIA-SUSTENTACION.md` está el recorrido para grabarlo.
- Antes de uso laboral real deben revisarse interpretaciones del contrato y autorización de deducciones con un profesional competente.
- Para producción, añadir auditoría de cambios, MFA, recuperación segura de contraseña, pruebas de integración en PostgreSQL y rate limiting detallado.

## Licencia

Proyecto de demostración técnica. No equivale a asesoría legal.

**Salario variable e indemnización:** el cálculo de indemnización usa salario básico más promedio de ingresos salariales variables reportados; en casos de salario variable se debe calcular el promedio con los pagos y tiempos efectivamente trabajados, conforme a la doctrina judicial (Sentencia SL1659-2025): https://cortesuprema.gov.co/corte-suprema-precisa-el-salario-base-para-liquidar-la-indemnizacion-por-despido-sin-justa-causa/

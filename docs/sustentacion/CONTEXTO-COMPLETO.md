# Contexto completo del proyecto (para Manuel)

Documento de referencia con todo lo que hay que saber del proyecto, de lo que se hizo y de lo que queda pendiente, escrito para preparar la sustentación y para pegarlo como contexto en cualquier otra herramienta. Estado al **4 de noviembre de 2026**.

---

## 1. Situación

- **Quién:** Manuel (usuario de Git `manudev`, repositorio `Manuekle/blindem-liquidaciones`).
- **Qué:** prueba técnica para **Blindem**. Entrega: una aplicación que liquida contratos laborales en Colombia usando como referencia el archivo `Formato_Liquidacion_e_Indemnizacion_Prueba Blindem.xlsx`.
- **Cuándo:** la sustentación (entrevista) es **mañana, 5 de noviembre de 2026**. Debe explicar desde la lógica de negocio hasta cómo se programó.
- **Qué se pidió en esta sesión:**
  1. Revisar y arrancar el proyecto.
  2. Que funcione con los datos del Excel, en local y en producción.
  3. Slides de sustentación con la UI de la app, sin tanto código, con iconos Hugeicons.
  4. Guion, preguntas y conceptos en texto plano, versión para teleprompter.
  5. Revisar la seguridad de las rutas (ids en URLs) y corregirla.
  6. Dejar local y prod consistentes y sin seeders corriendo en cada deploy.

---

## 2. El proyecto en una página

**Liquidaciones** (nombre visible; "Blindem Liquidaciones" en el repo) es una aplicación web que:

- Registra trabajadores, empresas y contratos.
- Calcula la liquidación: cesantías, intereses, prima, vacaciones, salario y auxilio pendientes, indemnización, aportes y neto.
- Explica cada cifra con su fórmula, la exporta a Excel y la guarda como "fotografía" inmutable.
- Tiene un asistente de IA que explica, responde con normas citadas, extrae datos de texto libre y revisa riesgos. La IA **nunca** calcula.

| Dato | Valor |
|---|---|
| Stack | Laravel 12, PHP 8.3, PostgreSQL 16, Blade + JS vanilla (sin build de frontend) |
| Contenedores | Docker (app Apache+PHP y postgres) |
| Producción | Render, servicio `blindem-liquidaciones`, URL `https://blindem-liquidaciones.onrender.com`, auto-deploy desde `main` |
| Repositorio | `https://github.com/Manuekle/blindem-liquidaciones` |
| Local | `http://127.0.0.1:8000` (Docker Compose, código montado) |
| IA local | Ollama (`qwen3.5:4b-mlx`), modo simulado por reglas si no hay modelo |
| Diseño | Hanken Grotesk, SN Pro, Geist Mono; fondo cálido, acento lima `#e4f222`; iconos Hugeicons |

---

## 3. Negocio (lo que hay que dominar)

### Qué es una liquidación laboral
Cuenta final de un contrato: lo que la empresa le debe al trabajador menos los descuentos.

### Conceptos y fórmulas
| Concepto | Fórmula |
|---|---|
| Base prestacional | básico + auxilio de transporte + promedio de extras |
| Cesantías | base × días / 360 |
| Intereses de cesantías | cesantías × días × 12% / 360 |
| Prima de servicios | base × días / 360 |
| Vacaciones | base de vacaciones × días (el divisor depende del método) |
| Salario pendiente | básico / 30 × días |
| Auxilio pendiente | auxilio / 30 × días |
| Indemnización | art. 64 CST, solo despido sin justa causa |
| Salud y pensión | 4% cada una |
| Neto | devengado − deducciones |

### Reglas clave
- Convención **30/360**; los días salen de las fechas y un valor escrito a mano prevalece.
- Auxilio de transporte solo hasta **2 SMLMV**.
- Indemnización en indefinido: **< 10 SMLMV** 30 días el primer año + 20 por año adicional; **≥ 10 SMLMV** 20 + 15. Término fijo: días que faltan. Obra o labor: valor manual sustentado, mínimo 15 días.
- Parámetros por año (SMLMV, auxilio, tarifas) con su decreto como fuente: 2023 $1.160.000 / 2024 $1.300.000 / 2025 $1.423.500 / 2026 $1.750.905.

### Los dos casos del Excel (Juan Carlos Pérez, CC 1098765432)
Datos comunes: básico $1.750.905, auxilio $249.095, extras promedio $150.000, 180 días de cesantías y prima, 15 días de vacaciones y de salario pendiente.

| | Hoja 1 | Hoja 2 |
|---|---|---|
| Fechas | 15/01/2023 → 30/06/2024 | 15/03/2021 → 30/06/2024 |
| Motivo | Terminación de contrato (sin indemnización) | Despido sin justa causa |
| Indemnización | — | 75,97 días = **$4.434.027** |
| **Neto (método Plantilla)** | **$3.180.899** | **$7.614.926** |
| Neto (método Legal ajustado) | $4.019.918 | $8.833.808 (indemnización $4.813.890) |

Desglose hoja 1: cesantías 1.075.000; intereses 64.500; prima 1.075.000; vacaciones 39.602; salario 875.453; auxilio 124.548; devengado 3.254.103; salud 36.602; pensión 36.602; neto 3.180.899.

### Hallazgos sobre el Excel (la parte que muestra criterio)
1. **Vacaciones:** divide por 720 una cantidad que son días de descanso → $39.602 en vez de $875.453.
2. **Salud y pensión:** la base incluye las vacaciones.
3. **Indemnización:** usa solo el básico, no los promedios salariales (la Corte Suprema, SL1659-2025, incluye los salariales).
4. **Término fijo:** usa 180 días constantes en vez de los días que faltan.

### Decisión de producto
No imponer una verdad: cada contrato tiene un **método de cálculo** (`settlement_method`):
- `template` = "Plantilla Blindem (Excel)": reproduce el Excel al peso.
- `legal` = "Legal ajustado" (por defecto): aplica el criterio de la norma.

El método queda guardado en el snapshot de cada liquidación.

---

## 4. Técnica

### Arquitectura
Monolito. Navegador (Blade + JS) → controladores delgados (validación, roles, CSRF) → servicios (reglas de negocio) → PostgreSQL.

### Archivos clave
| Qué | Archivo |
|---|---|
| Motor de cálculo (función pura, versión 2.2.0) | `app/Services/LaborSettlementCalculator.php` |
| Arma las entradas desde el contrato | `app/Services/SettlementInputs.php` |
| Pantalla, guardado, Excel exportado | `app/Http/Controllers/SettlementController.php` |
| Contratos (validación, método) | `app/Http/Controllers/ContractController.php` |
| Rutas y roles | `routes/web.php` |
| ID público en URLs | `app/Models/Concerns/HasPublicRouteKey.php` |
| Reglas por escrito | `docs/REGLAS-DE-CALCULO.md` |
| Pruebas del motor | `tests/Unit/LaborSettlementCalculatorTest.php` |
| Pruebas de flujo | `tests/Feature/SettlementWorkflowTest.php` |
| Arranque del contenedor | `docker/entrypoint.sh` |
| Slides | `docs/sustentacion/index.html` |

### Cómo funciona el motor
- Recibe un arreglo (fechas, salarios, parámetros del año, método, días opcionales) y devuelve importes enteros, días usados, advertencias y fórmulas en texto.
- No toca la base de datos. Redondeo mitad hacia arriba por concepto. Lanza `DomainException` con datos inválidos.
- El método Plantilla cambia: base de vacaciones (básico + extras), divisor 720, base de aportes (salario + vacaciones) y base e indemnización (solo básico, umbral `<= 10 SMLMV`, días con la fracción de la hoja).

### Datos
`workers`, `companies`, `contracts` (con `settlement_method`), catálogos (tipo de contrato con método, motivo de retiro con `implies_indemnification`, cargos), `labor_parameters` por año con fuentes, `settlements` (JSON de entradas y resultado + versión del motor = snapshot), `users` (admin / colaborador), notificaciones.

### Seguridad (estado actual)
- Toda ruta de negocio exige sesión y usuario activo; administración (usuarios, parámetros, catálogos, empresas, borrar trabajadores y **borrar contratos**) solo administrador.
- Contratos, trabajadores y liquidaciones usan **UUID aleatorio** (`public_id`) en la URL; el id numérico es interno. Un id numérico en la URL da 404.
- CSRF, límite de intentos de login y de peticiones de IA, cuentas desactivables, Excel exportado con texto escapado.
- **No hay aislamiento por empresa:** cualquier usuario autenticado ve todos los contratos (decisión consciente, una sola organización).

### IA
Dos proveedores: `simulated` (reglas, sin red) y `ollama`. RAG sobre `resources/knowledge/*.md` con citas. Todo lo que dice el modelo se verifica contra los datos; si no coincide se descarta.

### Pruebas
PHPUnit, base SQLite en memoria (no toca la base real). Último resultado: **492 aserciones, 0 fallos**. Se agregaron pruebas de aceptación con las cifras del Excel, de UUID en URLs, de borrado solo administrador y de conservación del método al editar.

---

## 5. Estado de los entornos

### Local (Docker, verificado hoy)
- 7 contratos, 5 liquidaciones guardadas, total guardado $31.461.193.
- Los dos contratos del Excel (Juan Carlos Pérez) con método Plantilla y netos **$3.180.899** y **$7.614.926**.
- Contratos demo: María Fernanda Gómez (2), Carlos Andrés Ruiz (indefinido y obra), Laura Sofía Martínez (activa).
- Recorrido HTTP con admin y colaborador: todas las páginas 200; `/contratos/24` da 404; colaborador recibe 403 al borrar.
- Respaldo de la base local antes de la limpieza: `/private/tmp/claude-501/-Users-manudev--claude-spares-spare-SyCMzE/69187c97-4e8b-4eb4-ae4f-9e0f64825ce3/scratchpad/local-backup-before-excel-only.sql`.

### Producción (Render) — lo que sé y lo que no
- Último deploy: commit `0010120` (docs). Las migraciones corrieron sin error, incluida la de UUID.
- `/login` responde 200; `/contratos/24` sin sesión da 404.
- **No pude iniciar sesión en prod** (no escribo contraseñas), así que **no verifiqué** los netos ni el método de cada contrato allí. Hay un navegador abierto en el login para que Manuel entre.
- Se borraron de prod los contratos que no eran del Excel (entre ellos **Joshua Guzman**, un término fijo que existía solo en prod, sin respaldo) y luego se cargaron los contratos demo con `DemoContractsSeeder`. En los logs de ese arranque `DemoContractsSeeder` terminó en 213 ms; no pude leer la base para confirmar el resultado final.
- Variables de Render dejadas a propósito: `RUN_SEED=false`, `SEED_DEMO_CONTRACTS=false`, `SEED_EXCEL_CASES=false`, `PURGE_TO_EXCEL=false`, `DEMO_DATASET=full`. `RUN_MIGRATIONS` sigue activo. Resultado: los deploys solo migran y **no siembran nada**.
- Base PostgreSQL de Render: plan gratuito, **expira el 2 de diciembre de 2026**.
- Servicio en plan gratuito: se duerme; abrirlo unos minutos antes de la demo.

---

## 6. Línea de tiempo

**Antes de hoy (2 de noviembre):** app completa con IA/RAG, parámetros por año, días automáticos, despliegue en Render, seeder demo, pie de login con stack.

**Hoy (4 de noviembre):**
1. Revisión del proyecto: corriendo en Docker, migraciones al día.
2. Se leyó el Excel y se comparó con el motor: el motor daba netos distintos por diseño.
3. **Decisión:** método Plantilla (`7120003`). Seeder con los dos casos y pruebas de aceptación.
4. Slides de sustentación, rehechas con la UI de la app e iconos Hugeicons (`028fc91`, `5fa84cf`, `6825a47`).
5. Cargar casos del Excel en prod (`a9fd799`), luego dejar solo Excel (`15643f0`), luego volver a demo + Excel (`6d871a1`) y apagar seeders.
6. Guion de estudio, texto plano y teleprompter (`bd34107`, `b56fe92`, `0010120`).
7. Seguridad de rutas (`d8c8818`): UUID público, borrar contrato solo administrador.
8. Corrección (`605b80a`): editar un contrato sin el campo método ya no lo resetea a `legal`.

---

## 7. Errores y correcciones que conviene conocer (honestidad en la entrevista)

- Se supuso que el seeder demo corría en cada deploy de prod. Los logs mostraron que solo corrían el de catálogos y el del Excel; los datos demo de prod los cargó alguien una vez.
- Un intento de activar `ALLOW_DEMO_SEED` en prod fue denegado por seguridad (crea cuentas con contraseña pública). Se usó un seeder de contratos que no crea usuarios.
- El contrato de la hoja 1 en local apareció con método `legal` por un bug (editar sin enviar el campo lo reseteaba). Corregido y con prueba; en local se restauró a Plantilla.
- Joshua Guzman se perdió en prod y no se puede recuperar sin sus datos.

---

## 8. Pendientes antes de la entrevista

1. **Iniciar sesión en prod** y comprobar: lista de contratos con URLs de UUID; los dos contratos del Excel con método **Plantilla** y netos $3.180.899 y $7.614.926.
2. **Cuentas demo en prod:** tienen una contraseña documentada en el repositorio. Cambiarlas o borrarlas.
3. **Notificaciones viejas** con enlaces de id numérico dan 404 (las nuevas funcionan).
4. Reemplazar `[TU NOMBRE]` en el guion del teleprompter.
5. Practicar en voz alta y con cronómetro; abrir prod 5 minutos antes.
6. Confirmar que no haya cambios sin subir (`git status`) y que el último deploy esté "live".

---

## 9. Entregables

| Archivo | Para qué |
|---|---|
| `docs/sustentacion/index.html` | Las 18 slides (flechas, N notas, F pantalla completa) |
| `docs/sustentacion/GUION.md` | Guion de estudio con cifras y preguntas |
| `docs/sustentacion/1-GUION-PRESENTACION.txt` | Qué decir en cada slide, con tiempos |
| `docs/sustentacion/2-PREGUNTAS-Y-RESPUESTAS.txt` | 34 preguntas con respuesta |
| `docs/sustentacion/3-CONCEPTOS-EXPLICADOS.txt` | Conceptos y ejemplo paso a paso |
| `docs/sustentacion/PROMPTER-1-guion.txt` | Guion para teleprompter |
| `docs/sustentacion/PROMPTER-2-preguntas.txt` | Respuestas para teleprompter |
| `docs/sustentacion/CONTEXTO-COMPLETO.md` | Este documento |

---

## 10. Cómo arrancar y operar

**Local**
```bash
sh scripts/setup-local.sh        # primera vez
docker compose up -d             # app en http://127.0.0.1:8000
docker exec blindem-liquidaciones-app-1 php artisan test
```

**Cargar los casos del Excel y demo (local)**
```bash
docker exec blindem-liquidaciones-app-1 php artisan db:seed --class=DemoContractsSeeder
docker exec blindem-liquidaciones-app-1 php artisan db:seed --class=DemoSettlementSeeder
```

**Dejar solo los casos del Excel (borra el resto, con confirmación)**
```bash
docker exec blindem-liquidaciones-app-1 php artisan blindem:excel-only
```

**Producción:** commit + push a `main`. Render construye, migra y publica.

---

## 11. Mensajes que deben quedar claros en la entrevista

1. El problema: liquidar a mano en Excel es lento, se equivoca y no deja rastro.
2. Reglas del negocio explícitas: 30/360, tope de 2 SMLMV para el auxilio, indemnización del art. 64 CST.
3. Encontré cuatro supuestos del Excel que difieren de la norma y **no los escondí**: dos métodos, elegibles por contrato y guardados en el historial.
4. Motor de cálculo puro, determinista y probado contra las cifras del Excel.
5. La IA explica, no calcula, y se verifica contra los datos.
6. Seguridad por capas: sesión + roles, URLs con UUID, CSRF, escapes.
7. Límites claros: no es nómina completa, PILA, salario integral ni embargos; una sola organización.
8. Frase de cierre: *"El Excel es el requisito, la norma es la defensa, y la aplicación deja elegir y registrar cuál se usó."*

---

## 12. Prompt de contexto compacto (para pegar en otra herramienta)

> Soy Manuel. Tengo mañana una sustentación para una prueba técnica de Blindem. Construí "Liquidaciones", una aplicación Laravel 12 / PHP 8.3 / PostgreSQL 16 en Docker, desplegada en Render (auto-deploy desde `main`), que calcula liquidaciones de contratos laborales colombianos (cesantías, intereses, prima, vacaciones, salario y auxilio pendientes, indemnización art. 64 CST, aportes de salud y pensión, neto) con un motor de cálculo puro (`LaborSettlementCalculator`, v2.2.0). El Excel de la prueba (Juan Carlos Pérez, dos hojas) da netos de $3.180.899 y $7.614.926; mi motor "Legal ajustado" da $4.019.918 y $8.833.808 porque el Excel divide las vacaciones por 720, incluye vacaciones en la base de aportes, usa solo el básico en la indemnización y usa 180 días fijos en término fijo. Por eso cada contrato elige entre dos métodos: "Plantilla" (reproduce el Excel al peso) y "Legal ajustado". Hay historial inmutable (snapshots), IA que solo explica (Ollama/simulada con RAG), roles admin/colaborador, URLs con UUID, 492 aserciones de pruebas. Ayúdame a practicar la presentación y las preguntas difíciles.

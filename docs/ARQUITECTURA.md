<p align="center"><img src="assets/logo.svg" width="88" height="88" alt="Icono de Blindem Liquidaciones"></p>

# Arquitectura y diseño del sistema

Blindem Liquidaciones gestiona trabajadores, empresas y contratos laborales en Colombia y calcula su liquidación: cesantías, intereses, prima, vacaciones, indemnización y aportes. Incluye un asistente de IA que explica la liquidación, responde preguntas con normas citadas, rellena contratos a partir de texto libre y convierte búsquedas en filtros.

Este documento describe cómo está construido y por qué. Los detalles de cada pieza están en:

| Documento | Contenido |
| --- | --- |
| [FUNCIONALIDADES.md](FUNCIONALIDADES.md) | Qué hace cada pantalla y qué facilita a cada rol |
| [INFRAESTRUCTURA.md](INFRAESTRUCTURA.md) | Docker, PostgreSQL y Ollama: imágenes, servicios, red, volúmenes, base de datos, modelos y diagnóstico |
| [IA.md](IA.md) | Asistente de IA: tareas, modo híbrido, RAG, verificación y configuración |
| [REGLAS-DE-CALCULO.md](REGLAS-DE-CALCULO.md) | Fórmulas, decisiones jurídicas y casos de aceptación |
| [OPERACION-LOCAL.md](OPERACION-LOCAL.md) | Puesta en marcha, actualización, backups y pruebas |
| [design-system.md](design-system.md) | Sistema visual, componentes e iconos |

## 1. Objetivos y restricciones

| Objetivo | Cómo se cumple |
| --- | --- |
| Cálculos correctos y explicables | Motor puro y determinista (`LaborSettlementCalculator`), enteros COP con redondeo HALF_UP por concepto, fórmulas y fuentes guardadas con cada resultado |
| Resultados históricos inmutables | Cada liquidación guardada es un snapshot con entradas, parámetros, resultado y versión del motor; se consulta y exporta sin recalcular |
| Funcionar sin internet | Todo corre en local con Docker; la IA usa Ollama en el mismo equipo o un modo simulado sin red |
| La IA nunca cambia una cifra ni inventa una norma | Cada tarea tiene una respuesta por reglas; lo que dice el modelo se verifica contra los datos y, si no coincide, se descarta |
| Simple de operar | Un comando (`sh scripts/setup-local.sh`), sin Node ni build de frontend, sin servicios en la nube |
| Seguro por defecto | Puerto solo en `127.0.0.1`, CSRF, roles, cuentas desactivables, límites de peticiones, pruebas bloqueadas fuera de bases de prueba |
| Que el equipo vea lo pendiente | Alertas calculadas desde los datos (retiros sin liquidar, contratos por vencer, años sin parámetros) y avisos de actividad del equipo |

Fuera de alcance: nómina completa, PILA, despliegue público, autenticación multifactor y auditoría completa de cambios.

## 2. Contexto

Quién usa el sistema y con qué se comunica.

```mermaid
flowchart LR
    admin["Administrador<br/><small>gestiona catálogos, parámetros<br/>laborales y empresas</small>"]
    collab["Colaborador<br/><small>registra trabajadores y contratos,<br/>calcula y exporta liquidaciones</small>"]
    system["Blindem Liquidaciones<br/><small>Laravel 12 · PostgreSQL 16</small>"]
    ollama["Ollama<br/><small>modelos de lenguaje y embeddings<br/>en el mismo equipo</small>"]
    excel["Archivo XLSX<br/><small>desglose, fórmulas y parámetros</small>"]

    admin -- navegador --> system
    collab -- navegador --> system
    system -- "HTTP /api/chat y /api/embed<br/>(opcional)" --> ollama
    system -- descarga --> excel
    system -- "enlace de acceso<br/>(SMTP o log)" --> mail["Correo<br/><small>recuperación de contraseña</small>"]
```

No hay integraciones externas obligatorias. El correo usa `MAIL_MAILER=log` por defecto (el enlace queda en `storage/logs`); Ollama es opcional: con `AI_DRIVER=simulated` el asistente responde con reglas locales.

## 3. Contenedores

Las piezas que se ejecutan y cómo se conectan.

```mermaid
flowchart TB
    browser["Navegador<br/><small>Blade renderizado + CSS/JS sin build</small>"]

    subgraph host["Equipo local"]
        subgraph compose["Docker Compose"]
            app["app<br/><small>Apache 2 + PHP 8.3 + Laravel 12<br/>puerto 8000</small>"]
            pg[("postgres<br/><small>PostgreSQL 16 alpine<br/>puerto 5432 solo en la red interna</small>")]
            vol[["volumen blindem_pg"]]
        end
        ollama["Ollama (servicio de Homebrew)<br/><small>puerto 11434 · Metal / MLX</small>"]
        models[["~/.ollama/models<br/><small>qwen3.5:4b-mlx · qwen3-embedding:0.6b</small>"]]
    end

    browser -- "127.0.0.1:8000" --> app
    app -- "pdo_pgsql · red de Compose" --> pg
    pg --- vol
    app -- "host.docker.internal:11434" --> ollama
    ollama --- models
```

| Contenedor | Responsabilidad | Estado |
| --- | --- | --- |
| `app` | Rutas, autenticación, validación, motor de cálculo, exportación XLSX, asistente de IA | Sin estado propio: el código se monta desde el repositorio y la caché de archivos es regenerable |
| `postgres` | Datos de negocio, snapshots de liquidaciones, sesiones | Persistente en el volumen `blindem_pg` |
| Ollama | Inferencia del modelo de chat y de embeddings | Fuera de Docker, para usar la GPU del Mac (Metal/MLX); los modelos viven en `~/.ollama` |

Ollama corre fuera de Docker a propósito: Docker Desktop en macOS no expone la GPU de Apple, y un modelo en CPU dentro de un contenedor es varias veces más lento.

## 4. Componentes de la aplicación

```mermaid
flowchart TB
    subgraph http["Capa HTTP"]
        routes["routes/web.php<br/><small>auth · admin · throttle</small>"]
        mw["Middleware<br/><small>auth · active · admin · throttle</small>"]
        controllers["Controladores<br/><small>Dashboard, Worker, Company, Contract,<br/>Settlement, Catalog, LaborParameter, Ai,<br/>User, Profile, PasswordReset, Search, Notification</small>"]
        views["Vistas Blade<br/><small>resources/views</small>"]
        js["public/js<br/><small>app · ui · validation · controls · nav<br/>command · guided · ai · agent · sounds</small>"]
        sw["sw.js<br/><small>página sin conexión</small>"]
    end

    subgraph support["Soporte"]
        notify["NotificationCenter<br/><small>alertas y avisos al equipo</small>"]
        table["Support/Table<br/><small>orden y paginación seguros</small>"]
    end

    subgraph domain["Dominio"]
        inputs["SettlementInputs<br/><small>contrato + vigencia del año → entradas</small>"]
        calc["LaborSettlementCalculator<br/><small>puro, sin base de datos · VERSION 2.1.0</small>"]
    end

    subgraph ai["Asistente de IA"]
        agent["AiAgent<br/><small>asistente flotante: intención<br/>y contrato por reglas</small>"]
        assistant["AiAssistant<br/><small>tareas, prompts, esquemas, verificación</small>"]
        rules["Simulated/*<br/><small>respuestas por reglas</small>"]
        grounding["Grounding<br/><small>cifras y citas conocidas</small>"]
        kb["Rag/KnowledgeBase<br/><small>BM25 + embeddings + RRF</small>"]
        client["AiClient<br/><small>SimulatedAiClient | OllamaAiClient</small>"]
        embedder["Rag/OllamaEmbedder"]
        notes[["resources/knowledge/*.md"]]
    end

    models["Modelos Eloquent<br/><small>Worker, Company, Contract, Settlement,<br/>catálogos, LaborParameter, User</small>"]
    xlsx["PhpSpreadsheet"]

    routes --> mw --> controllers
    controllers --> views
    js -- "fetch JSON /ia/*" --> routes
    controllers --> inputs --> calc
    inputs --> models
    controllers --> models
    controllers --> xlsx
    controllers --> notify
    controllers --> table
    js -- "fetch /buscar" --> routes
    controllers --> agent --> assistant
    controllers --> assistant
    assistant --> rules
    assistant --> grounding
    assistant --> kb
    assistant --> client
    kb --> notes
    kb --> embedder
```

Reglas de dependencia:

- El motor de cálculo no conoce Laravel ni la base de datos: recibe un arreglo y devuelve otro. Por eso se prueba de forma aislada (`tests/Unit/LaborSettlementCalculatorTest.php` y `scripts/verify_calculator.php`).
- `AiAssistant` solo habla con el proveedor a través de la interfaz `AiClient::run()`. Cambiar de Ollama a otro proveedor no toca las tareas.
- Las respuestas por reglas (`Simulated/*`) son a la vez el modo sin red y la referencia verificada del modo con modelo.
- `AiAgent` no tiene lógica de negocio propia: decide la intención y el contrato y delega en las tareas de `AiAssistant`.
- Los controladores avisan a `NotificationCenter` cuando cambian datos que alimentan las alertas; las alertas se recalculan desde la base, no se guardan como estado independiente.
- El JavaScript es progresivo: sin él, los formularios, enlaces y tablas siguen funcionando con su comportamiento nativo.

### Estructura de carpetas

```text
app/
  Http/Controllers/          un controlador por recurso + Ai, User, Profile, PasswordReset, Search, Notification
  Http/Middleware/           EnsureAdmin, EnsureActiveUser
  Models/                    entidades Eloquent
  Notifications/             ActivityNotification (avisos) y AlertNotification (pendientes)
  Providers/                 elige el proveedor de IA y arma la base de conocimiento
  Services/
    LaborSettlementCalculator.php   motor de cálculo puro
    SettlementInputs.php            entradas del motor desde un contrato
    NotificationCenter.php          alertas de pendientes y avisos al equipo
    Ai/                             asistente: AiAssistant, AiAgent, clientes, Grounding
      Rag/                          KnowledgeBase, Embedder, OllamaEmbedder
      Simulated/                    respuestas por reglas de cada tarea
  Support/Table.php          orden por columnas permitidas y tamaño de página
config/ai.php                proveedor, modelo, RAG
routes/console.php           blindem:admin (crear administrador sin datos demo)
database/migrations/         esquema, restricciones e índices
database/seeders/            catálogos, usuarios y datos de demostración
docker/                      Apache, entrypoint y php.ini de producción
resources/knowledge/         notas legales para el RAG (Markdown)
resources/views/             Blade
public/css, public/js        estilos e interacciones sin build (vendor/: zod, blobatar, cuelume)
public/icons/                sprite de Hugeicons
public/sw.js, offline.html   página de respaldo cuando no hay conexión
scripts/                     setup-local, backup, verify_calculator
tests/                       PHPUnit (unitarias y de funcionalidad)
```

## 5. Flujos principales

### 5.1 Calcular y guardar una liquidación

```mermaid
sequenceDiagram
    autonumber
    actor U as Colaborador
    participant C as SettlementController
    participant I as SettlementInputs
    participant DB as PostgreSQL
    participant M as LaborSettlementCalculator

    U->>C: GET /contratos/{id}/liquidacion
    C->>I: fromContract(contrato)
    I->>DB: contrato, catálogos, labor_parameters del año de corte
    alt no hay parámetros para ese año
        I-->>C: DomainException
        C-->>U: cálculo bloqueado con el motivo
    else
        I-->>C: entradas (fechas, salario, días, tarifas, fuentes)
        C->>M: calculate(entradas)
        M-->>C: resultado + fórmulas + versión
        C-->>U: vista con desglose y neto
    end
    U->>C: POST /contratos/{id}/liquidacion
    C->>DB: INSERT settlements (inputs JSON, result JSON, net_amount, calculation_version)
    C-->>U: snapshot guardado en el historial
```

El snapshot guarda entradas y resultado completos. Si mañana cambian los parámetros del año, el contrato o la versión del motor, la liquidación guardada se sigue mostrando y exportando igual.

### 5.2 Pregunta al asistente con fuentes (RAG)

```mermaid
sequenceDiagram
    autonumber
    actor U as Usuario
    participant JS as ai.js
    participant A as AiAssistant
    participant R as SettlementAnswer (reglas)
    participant K as KnowledgeBase
    participant O as Ollama

    U->>JS: «¿Me pueden negar el certificado laboral?»
    JS->>A: POST /ia/contratos/{id}/preguntar
    A->>R: respuesta por reglas
    A->>K: search(pregunta)
    K->>O: /api/embed (consulta)
    K-->>A: fuentes S1..S3 con norma y texto
    alt la regla cubre el tema y la mejor fuente es de la liquidación
        A-->>JS: respuesta por reglas con las cifras del cálculo
    else no hay fuentes
        A-->>JS: «no encontré información suficiente» (sin llamar al modelo)
    else
        A->>O: /api/chat con pregunta, cifras y fuentes numeradas
        O-->>A: {status, answer, citations}
        A->>A: verificar citas, normas y cifras
        alt verificación correcta
            A-->>JS: respuesta citada [S1] + fuentes
        else
            A-->>JS: fragmento de la mejor fuente (fallback)
        end
    end
    JS-->>U: respuesta, «Fuentes (n)» y origen
```

### 5.3 Autenticación, cuentas y permisos

```mermaid
flowchart LR
    req["Petición"] --> guest{"¿sesión?"}
    guest -- no --> login["/login<br/><small>throttle 5/min<br/>solo cuentas activas</small>"]
    login -. "¿olvidaste tu contraseña?" .-> reset["/olvide-contrasena<br/><small>enlace por correo · throttle 5/min</small>"]
    guest -- sí --> auth["auth"] --> active{"EnsureActiveUser<br/><small>¿cuenta activa?</small>"}
    active -- no --> out["cierra sesión<br/>y vuelve a /login"]
    active -- sí --> kind{"¿qué ruta?"}
    kind -- "/ia/*" --> t40["throttle 40/min"] --> aictl["AiController"]
    kind -- "/buscar" --> t60["throttle 60/min"] --> search["SearchController"]
    kind -- "administración" --> ensure["EnsureAdmin<br/><small>403 si no es admin</small>"] --> ctl["Controlador"]
    kind -- "resto" --> ctl
```

- Las sesiones se guardan en la tabla `sessions` de PostgreSQL, así que sobreviven a recrear el contenedor `app`. Desde **Perfil y seguridad** cada persona ve sus sesiones abiertas, las cierra y cambia su contraseña (al cambiarla se renueva el `remember_token`).
- El administrador gestiona **usuarios**: los crea, cambia su rol, los desactiva y les envía un enlace para definir contraseña. Una cuenta desactivada no puede iniciar sesión y pierde el acceso en su siguiente petición aunque conserve la cookie.
- Los formularios llevan token CSRF y las validaciones se hacen en el servidor (el cliente valida con zod a partir de los mismos atributos HTML). `Gate::before` concede todo al rol `admin`.

### 5.4 Notificaciones y pendientes

```mermaid
sequenceDiagram
    autonumber
    actor A as Colaborador A
    participant C as Controlador
    participant N as NotificationCenter
    participant DB as notifications
    actor B as Resto del equipo

    A->>C: guarda contrato, retiro, trabajador o liquidación
    C->>N: announce(actor, título, enlace)
    N->>DB: ActivityNotification para cada usuario activo excepto A
    C->>N: refresh() (cambia la marca de caché)
    B->>N: siguiente petición: sync(usuario)
    N->>DB: crea, conserva o borra AlertNotification
    Note over N,DB: retiros sin liquidar, fijos que vencen en 30 días,<br/>año sin parámetros (solo administradores)
    B-->>B: campana con contador y panel de avisos
```

Cada alerta tiene un id estable derivado de su causa: se crea una vez, conserva su estado de lectura y desaparece sola cuando el trabajo se hace (por ejemplo, al guardar la liquidación). `sync` corre como máximo una vez por minuto por usuario, salvo que los datos cambien.

### 5.5 Navegación y búsqueda

- **Paleta de comandos** (`⌘K` / `Ctrl+K`, `command.js`): ir a cualquier pantalla, ejecutar acciones, buscar contratos, trabajadores y empresas (`GET /buscar`) o pasar la pregunta al asistente. `?` muestra los atajos (`G` + letra para navegar).
- **Tablas** con orden por columna y tamaño de página (`Support/Table`): solo se ordena por claves permitidas, así que la URL nunca llega a una columna arbitraria.
- **Formularios guiados** (`guided.js`): el asistente pregunta un dato a la vez y llena el formulario real; el formulario clásico está a un clic.
- **Sin conexión:** `sw.js` solo guarda la página `offline.html` y sus recursos; las páginas de la aplicación siempre vienen de la red, para no mostrar datos laborales viejos.

## 6. Modelo de datos

```mermaid
erDiagram
    users ||--o{ contracts : "crea (created_by)"
    users ||--o{ settlements : "calcula (calculated_by)"
    document_types ||--o{ workers : identifica
    workers ||--o{ contracts : tiene
    companies ||--o{ contracts : emplea
    job_titles ||--o{ contracts : cargo
    contract_types ||--o{ contracts : tipo
    termination_reasons |o--o{ contracts : "motivo de retiro"
    contracts ||--o{ settlements : snapshots
    users ||--o{ notifications : recibe

    users {
        bigint id PK
        string email UK
        enum role "admin | collaborator"
        boolean active "false = sin acceso"
    }
    notifications {
        uuid id PK
        string type "Activity | Alert"
        bigint notifiable_id FK
        json data "título, texto, enlace, icono, tono"
        timestamp read_at
    }
    workers {
        bigint id PK
        bigint document_type_id FK
        string document_number "único con el tipo"
        string full_name
    }
    companies {
        bigint id PK
        string nit UK
        string name
    }
    contract_types {
        bigint id PK
        string code UK
        string calculation_method "fijo | indefinido | obra | manual"
    }
    termination_reasons {
        bigint id PK
        string code UK
        boolean implies_indemnification
    }
    contracts {
        bigint id PK
        date start_date
        date end_date "null si está activo"
        date agreed_end_date "término fijo"
        bigint basic_salary "COP enteros"
        bigint average_extras
        bigint average_surcharges
        decimal vacation_days_pending "null = automático"
        enum status "active | terminated"
        boolean salary_components_reviewed
    }
    settlements {
        bigint id PK
        bigint contract_id FK
        json inputs "entradas y parámetros usados"
        json result "conceptos, fórmulas, versión"
        bigint net_amount
        string calculation_version
    }
    labor_parameters {
        bigint id PK
        smallint year UK
        bigint min_wage
        bigint transport_allowance
        decimal health_rate
        decimal pension_rate
        decimal interest_rate
        string wage_source
        string transport_source
    }
```

Decisiones del modelo:

- **Dinero en enteros.** Los montos son `bigint` en pesos, sin decimales: no hay errores de coma flotante y el redondeo HALF_UP se aplica una sola vez por concepto.
- **Integridad referencial estricta.** Los catálogos usan `restrictOnDelete`: no se puede borrar un cargo, una empresa o un tipo en uso. Un contrato con liquidaciones guardadas tampoco se puede eliminar.
- **Vigencias por año.** `labor_parameters` tiene una fila por año con su fuente legal. Un contrato que termina en un año sin parámetros no se calcula, en lugar de usar los del año actual en silencio.
- **Snapshots en JSON.** `settlements.inputs` y `settlements.result` son JSON porque el resultado evoluciona con la versión del motor; `net_amount` va en columna propia para ordenar y sumar.
- **Notificaciones nativas de Laravel.** La tabla `notifications` guarda avisos y alertas; `password_reset_tokens` guarda los enlaces de recuperación con caducidad.
- **Índices.** Cada clave foránea tiene índice (migración `2026_11_01_000001_index_foreign_keys`), además de `contracts(status, start_date)` para el listado y `settlements(contract_id, id)` para el historial.

## 7. Asistente de IA

Resumen; el detalle está en [IA.md](IA.md).

```mermaid
flowchart LR
    task["Tarea<br/><small>explicar, preguntar, extraer,<br/>buscar, revisar, resumen, carta</small>"]
    rules["Respuesta por reglas<br/><small>siempre correcta</small>"]
    model["Modelo (Ollama)<br/><small>redacta y entiende texto libre</small>"]
    verify{"Verificación<br/><small>ids, fechas, montos,<br/>normas, citas</small>"}
    out["Respuesta<br/><small>source: rules | ai | mixed<br/>fallback · sources</small>"]

    task --> rules
    task -- "solo tareas de comprensión" --> model
    rules --> verify
    model --> verify
    verify -- "lo verificable" --> out
    verify -- "si falla" --> rules
```

| Tarea | Proveedor |
| --- | --- |
| Revisión del contrato, «Para revisar hoy», carta | Solo reglas: son cumplimiento legal o documentos con cifras exactas |
| Explicación de la liquidación | El modelo reescribe la explicación por reglas; cada `$` debe ser una cifra del cálculo |
| Preguntas | Reglas si el tema es de la liquidación; si no, RAG con fuentes citadas |
| Rellenar contrato y búsqueda | Reglas + aportes del modelo que aparezcan en el texto o en los catálogos |

## 8. Atributos de calidad

| Atributo | Decisión | Medida |
| --- | --- | --- |
| Corrección | Motor puro con pruebas unitarias y casos de aceptación; IA verificada contra los datos | 16 pruebas del motor; `verify_calculator.php` |
| Rendimiento | Sin build ni SPA; consultas con índices y `withCount`; RAG con embeddings en caché | Búsqueda de fuentes ~40 ms; pregunta con fuentes 4–9 s con `qwen3.5:4b-mlx` en un M2 |
| Disponibilidad de la IA | Si Ollama falla, la interfaz muestra el motivo y permite reintentar; los embeddings caen a BM25 | Las funciones de cálculo nunca dependen de la IA |
| Seguridad | Puerto local, CSRF, roles, throttle, credenciales fuera de Git, pruebas bloqueadas fuera de `blindem_test` | Usuario `www-data` en la imagen de producción |
| Mantenibilidad | Proveedor de IA detrás de una interfaz; conocimiento legal en Markdown; sin dependencias de frontend | Agregar una norma es agregar un archivo y una pregunta de prueba |
| Portabilidad | Docker Compose; `host-gateway` para llegar a Ollama también en Linux | Mismo comando en macOS y Linux |

## 9. Decisiones de arquitectura

| # | Decisión | Alternativas | Motivo |
| --- | --- | --- | --- |
| 1 | Monolito Laravel con Blade, sin SPA | React/Vue + API | Pocos usuarios, formularios y tablas; menos piezas y sin build |
| 2 | Motor de cálculo como clase pura | Cálculo en modelos o en SQL | Pruebas aisladas, resultados reproducibles y versionados |
| 3 | Snapshots JSON de cada liquidación | Recalcular al consultar | Un cambio de norma o de datos no debe alterar una liquidación ya entregada |
| 4 | PostgreSQL 16 | MySQL, SQLite | JSON nativo, restricciones e índices sólidos; SQLite queda para pruebas rápidas |
| 5 | Ollama local con modelos pequeños | API en la nube | Datos laborales sensibles no salen del equipo; funciona sin internet |
| 6 | Modo híbrido reglas + modelo | Solo modelo | Un modelo de 4B inventa cifras y normas; las reglas garantizan la corrección |
| 7 | RAG en PHP (BM25 + embeddings + RRF) sin base vectorial | LangChain, pgvector, Chroma | Unas 20 notas caben en memoria; sin servicios extra; la búsqueda tarda ~40 ms |
| 8 | Ollama fuera de Docker | Contenedor de Ollama | Acceso a la GPU de Apple (Metal/MLX) |
| 9 | Iconos Hugeicons en un sprite SVG local | CDN o paquete JS | Sin red ni build; licencia MIT incluida |

## 10. Pruebas

```mermaid
flowchart LR
    unit["Unitarias<br/><small>LaborSettlementCalculatorTest</small>"]
    feature["Funcionalidad<br/><small>SettlementWorkflowTest · UsersTest · AccountTest<br/>SearchTest · TablesTest · NotificationsTest<br/>AiAssistantTest · AiAgentTest · OllamaAiClientTest<br/>KnowledgeBaseTest</small>"]
    script["verify_calculator.php<br/><small>casos de aceptación sin PHPUnit</small>"]
    sqlite[("SQLite en memoria<br/><small>por defecto</small>")]
    pgtest[("PostgreSQL blindem_test<br/><small>phpunit.postgres.xml</small>")]

    unit --> sqlite
    feature --> sqlite
    feature -. "misma suite" .-> pgtest
```

- La suite tiene 101 pruebas. Además del motor y la liquidación cubre permisos por rol, cuentas desactivadas, recuperación de contraseña, sesiones, búsqueda, orden de tablas y el ciclo de vida de las alertas.
- Las pruebas de IA no llaman a ningún modelo: usan el modo simulado o `Http::fake`. `OllamaAiClientTest` cubre la verificación, los respaldos, el reintento sin `format` y los errores de conexión.
- `KnowledgeBaseTest` mide la recuperación: 12 preguntas de referencia deben traer su nota en primer lugar (recall@1) y las preguntas ajenas no deben traer fuentes.
- `tests/TestCase.php` aborta si la conexión no es SQLite en memoria o `blindem_test` con `APP_ENV=testing`, para que una prueba nunca borre la base real.

## 11. Limitaciones y evolución

- **Un solo equipo.** El diseño asume uso local. Para publicarlo hace falta HTTPS, un proxy, la imagen `production` (ya existe en el `Dockerfile`), secretos fuera de `.env` y copias de seguridad automáticas.
- **Modelo pequeño.** `qwen3.5:4b-mlx` responde rápido pero a veces resume de más; las respuestas muestran sus fuentes para que se puedan revisar. Un modelo de 7–9B o uno en Ollama Cloud mejora la redacción sin cambiar código.
- **Base de conocimiento curada a mano.** Las notas están revisadas a noviembre de 2026. Si crecen a cientos de documentos, conviene pasar los embeddings a `pgvector` y agregar un reranker.
- **Pendientes de seguridad.** Autenticación multifactor y auditoría completa de cambios. La recuperación de contraseña ya existe, pero necesita un SMTP real (`MAIL_MAILER=smtp`) para enviar correos fuera del log.

# Sistema de diseño de liquidaciones

## Objetivo

Una interfaz de trabajo para consultar personas y contratos, revisar una liquidación y guardar o exportar su resultado. La información, los formularios y las acciones deben leerse con facilidad. Este documento es la referencia para las vistas y los estilos compartidos de la aplicación.

## Principios

- Inter como única familia, con peso medium real (`500`) en todos los roles. Jerarquía por tamaño, color y espacio.
- Texto, navegación, etiquetas, botones y tablas con tracking `0`. Títulos y cifras destacadas con tracking `-0.04em`.
- Escritura en estilo oración, sin encabezados, etiquetas o llamadas en mayúsculas sostenidas. Las etiquetas de interfaz usan «nit», «documento», «pesos colombianos» y «excel». Los nombres y datos guardados conservan su escritura original.
- Sin logos, isotipos, monogramas, marcas de terceros ni wordmarks. El identificador del espacio es el texto funcional «Liquidaciones», con el mismo tratamiento que la navegación.
- Un único lenguaje de controles, tablas, formularios y estados en toda la aplicación.
- La implementación visual conserva cálculos, permisos, rutas, validaciones y datos existentes.

## Decisiones sobre la interfaz anterior

| Hallazgo | Evidencia | Decisión de diseño |
| --- | --- | --- |
| Texto de trabajo demasiado pequeño | Tablas de 11 px; cabeceras de 9 px; descripciones de 10.5 px | Texto principal de 12 px; auxiliares de 11 px |
| Jerarquía basada en demasiados pesos | Pesos entre 400 y 800; título de 750 | Peso 500 constante; escala de tamaños y color |
| Espaciado tipográfico disperso | Navegación secundaria con 1.1 px; tablas con 0.5 px; títulos con valores fijos | Tracking 0 en lectura y `-0.04em` en títulos |
| Mayúsculas repetidas | Grupos de navegación, cejas, cabeceras y secciones del desglose | Estilo oración en todo el texto de interfaz |
| Marca y decoración dominantes | Símbolo inclinado, «blindem.», monograma en login, órbitas, puntos y barras de varios colores | Identificación funcional y superficies neutras |
| Texto secundario muy tenue | Descripciones `#9ca0ad`; cabeceras `#9ba2ae` sobre blanco | Texto secundario `#636363` |
| Elementos que parecen acciones sin serlo | Campana, chevron del usuario y «Filtros avanzados» sin interacción propia | Mostrar únicamente controles con una acción real |
| Gráfico con unidad confusa | El eje muestra porcentajes y las barras representan cantidades | Eje en número de contratos; cero representado como cero |
| Información decorativa sin respaldo | «↗ +» y crecimiento de login «+24%» | Mostrar únicamente cifras procedentes de datos reales |

## Dirección visual

Se consideraron tres enfoques:

1. **Superficies neutras con relieve mínimo — recomendado.** Fondo gris claro, paneles blancos, separadores finos y controles con una sombra corta. Funciona tanto para tablas como para formularios.
2. **Plano compacto.** Menos paneles y mayor densidad. Favorece listados extensos, pero exige una lectura más concentrada.
3. **Panel analítico con más color.** Prioriza gráficos y tarjetas. Es útil para análisis, pero desplaza las tareas centrales de consultar y liquidar contratos.

Se usa el primer enfoque. Su rasgo propio es la claridad del expediente laboral: identificación visible, fechas y dinero alineados, estado junto al contrato y neto destacado con tipografía. El color señala acciones o estados; el tamaño de las tarjetas responde a la información que contienen.

## Fundamentos

### Color

La paleta usa `#F5F5F5` como fondo, `#FBFBFB` como superficie y `#2D56E9` como azul principal. Los tonos de presión y foco suave se derivan del azul; los colores semánticos se conservan suaves. Los bordes son tenues y el redondeado amplio.

| Token | Valor | Uso |
| --- | --- | --- |
| `primary` | `#2D56E9` | Acción principal, casillas e interruptores activos |
| `primary-focus` | `#2D56E9` | Borde de foco |
| `primary-link` | `#2D56E9` | Enlaces y acentos de datos |
| `primary-pressed` | `#2243BF` | Acción presionada y base del relieve azul |
| `primary-tint` | `#EDF1FF` | Halo de foco y superficies de apoyo |
| `on-primary` | `#FFFFFF` | Texto e iconos sobre acciones azules |
| `ink` | `#000000` | Títulos, contenido y cifras |
| `body-secondary` | `#636363` | Descripciones y metadatos |
| `muted` | `#A3A3A3` | Placeholders, iconos auxiliares y controles deshabilitados |
| `surface` | `#FBFBFB` | Paneles y navegación |
| `surface-warm` | `#F5F5F5` | Fondo y superficie secundaria neutra |
| `hairline` | `#ECECEE` | Bordes y separadores |
| `accent-amber` | `#FFB110` | Observaciones |
| `accent-amber-deep` | `#E89D01` | Detalles de observación |
| `accent-coral` | `#F64932` | Contorno destructivo y de error |
| `accent-red` | `#E32D14` | Error, deducciones y eliminación |
| `success` | `#1AAE39` | Indicador de estado activo y gráfico de estado |
| `success-tint` | `#D0F4D8` | Superficie de éxito con texto negro |
| `accent-indigo` | `#1313BA` | Texto de etiquetas lavanda |
| `chip-lavender` | `#EADBFA` | Superficie de etiquetas de recuento |

Los alias del CSS (`bg`, `text`, `secondary`, `border`, etc.) remiten a estos tokens. Los degradados y las sombras añaden profundidad sutil. El ámbar, el coral, el verde y el lavanda se reservan para estados concretos; no se introducen en cada panel como decoración. Los estados siempre incluyen texto.

### Tipografía

| Rol | Tamaño / interlineado | Peso | Tracking |
| --- | --- | --- | --- |
| Título de página y acceso | 20 / 28 px | 500 | `-0.04em` |
| Neto de liquidación | 26 / 32 px; 24 / 30 px en móvil | 500 | `-0.04em` |
| Métrica | 24 / 30 px; importes entre 18 y 22 px | 500 | `-0.04em` |
| Título de panel | 14 / 20 px | 500 | `-0.04em` |
| Texto, navegación, botones, etiquetas y tablas | 12 / 18 px | 500 | `0` |
| Inputs, selects, búsquedas y áreas de texto | 12 / 18 px en todos los anchos | 500 | `0` |
| Metadatos y cabecera de tabla | 11 / 16 px | 500 | `0` |

Se carga únicamente Inter medium. Los elementos semánticos `strong`, `b` y los encabezados heredan el peso 500. Importes, fechas y cantidades usan cifras tabulares. No se usa `text-transform` para modificar los datos.

### Espacio, forma y profundidad

| Familia | Valores |
| --- | --- |
| Espaciado habitual | 4, 6, 8, 12, 16 y 20 px; 24–32 px solo entre bloques mayores |
| Radios | 5 px en casillas; 6 px en etiquetas; 12 px en campos y botones de 32 px; 13 px en controles móviles de 36 px; 16 px en paneles |
| Campo y botón estándar | 32 px de alto; 36 px en móvil |
| Acción solo con icono | 32 × 32 px; 36 × 36 px en móvil |
| Icono Lucide | 14 × 14 px sobre cuadrícula 24 × 24 |
| Fila de tabla | 48 px cuando contiene nombre y documento |
| Sombra de panel | Brillo interior y sombras inferiores de 1 y 2 px; menor profundidad que los botones |
| Sombra de campo y navegación | Microrelieve interior y sombra inferior de 1 px |
| Sombra de botón | Brillo interior, borde doble óptico y sombras inferiores cortas |
| Foco | Borde azul y un halo suave en campos; anillo separado en botones y enlaces |

Los paneles tienen un único borde y una sombra corta. Se eliminan las órbitas, los monogramas, los asteriscos decorativos, los remates de color y los gradientes extensos de la interfaz actual.

## Estructura de la aplicación

Escritorio desde 1024 px: barra lateral de 200 px, encabezado de 52 px y contenido con margen interior de 20 px. El contenido usa un ancho máximo de 1080 px; los formularios se limitan a 800 px. La cabecera y el pie comparten el mismo eje horizontal del contenido; las páginas de formulario usan un contenedor de 840 px incluidos sus márgenes interiores. La navegación y el encabezado comparten el mismo fondo neutro; el contenido principal agrupa los paneles blancos.

Entre 768 y 1023 px: navegación plegable, margen de 16 px y dos métricas por fila. Debajo de 768 px: margen de 12 px, controles de 36 px y formularios en una columna. Las métricas conservan dos columnas en móvil; las acciones de cabecera se ajustan a su contenido. Las tablas tienen desplazamiento horizontal dentro de su contenedor y no ensanchan el documento.

```text
Liquidaciones     Espacio de trabajo / Resumen              Usuario
──────────────────────────────────────────────────────────────────
Resumen           Resumen                          Nuevo contrato
Contratos         Contratos, personas y actividad de tu operación.
Trabajadores
Empresas          Contratos  Trabajadores  Empresas  Liquidado

Administración    Actividad de contratos         Estado de contratos
Documentos        Cantidades por mes             Activos / retirados
Cargos
Tipos de contrato Contratos recientes                    Ver todos
Motivos de retiro Trabajador · empresa · tipo · estado · fecha

Cerrar sesión
```

La barra lateral no lleva logo ni tarjeta promocional. La campana y el chevron de cuenta se retiran mientras no exista una acción asociada. No se añaden pestañas, filtros ni botones que no estén respaldados por funciones existentes.

## Componentes

| Componente | Anatomía y estados |
| --- | --- |
| Navegación | Icono lineal de 16 px, texto de 12 px, altura 32 px. Activo con fondo azul suave, texto azul y borde tenue; hover gris claro; página actual identificada semánticamente |
| Encabezado de página | Un título directo, descripción de una línea y acción principal a la derecha; apilado en móvil |
| Botón | Relieve suave: cara con degradado vertical, contorno claro, brillo interior y sombra inferior. Primario azul; secundario blanco grisáceo; destructivo rojo con texto blanco; acción de cancelación contextual en rojo sobre blanco. Radio 12 px (13 px en móvil), Inter 500 y tracking 0. Deshabilitado con relieve reducido y carga con verbo específico de la acción |
| Campo | Etiqueta persistente, superficie degradada sutil, brillo interior, contorno cálido uniforme. Radio 12 px (13 px en móvil) y texto de 12 px. Mismo tratamiento para texto, números, fechas, selectores, búsquedas y edición de catálogos. Foco azul con un único halo, error rojo y estados deshabilitado y de solo lectura. El placeholder nunca sustituye la etiqueta |
| Filtro | Mismos campos que el formulario, organizados en una zona compacta. «Aplicar filtros» y «Limpiar» conservan su comportamiento actual |
| Panel | Superficie blanca con brillo interior, borde cálido y sombra corta. Radio 16 px. Título y descripción opcional, contenido y pie opcional. Padding habitual de 12–20 px; 12 px en móvil. Sin encabezados decorativos vacíos |
| Métrica | Etiqueta, cifra y contexto verificable. Cuatro métricas en escritorio, dos en pantallas intermedias; dos también en móviles menores de 480 px |
| Tabla | Encabezados en estilo oración, texto 12 px y auxiliares 11 px. Importes a la derecha; nombres a la izquierda. Acciones con nombre accesible |
| Estado | Texto «Activo» o «Retirado», punto y fondo suave. No depende solo del color |
| Detalle | Lista de término y valor. Los valores largos se parten; los importes mantienen alineación y cifras tabulares |
| Aviso | Icono, mensaje concreto y siguiente acción si corresponde. Éxito, error, información y observación con el mismo espaciado |
| Vacío | Explica si faltan registros o si los filtros no encontraron resultados. Ofrece crear o limpiar filtros solo cuando la acción está disponible para el rol |
| Paginación | Cantidad mostrada y página actual junto al par anterior/siguiente, unidos en una única superficie con separador interior. Extremos claramente deshabilitados |
| Interruptor y casilla | Casilla semántica nativa de 16 × 16 px con cara blanca, borde suave, marca Lucide blanca sobre azul y foco azul. El área de su etiqueta mide 32 px de alto en móvil; el dibujo de la casilla conserva su tamaño. Interruptor con canal ligeramente hundido y pieza móvil elevada; activado en azul. Conservan valores, etiquetas y envío existentes |

Los iconos son Lucide oficiales, funcionales, lineales y de un solo color: cuadrícula 24 × 24, trazo 2 y representación habitual de 14 px. Se sirve una selección local de `lucide-static` 1.64.0 desde `public/icons/lucide.svg`, con licencia ISC incluida. Los campos de fecha conservan su selector nativo con un calendario Lucide decorativo; los selectores usan el chevron Lucide. Los botones solo con icono tienen nombre accesible. No se incorporan logos ni avatares decorativos.

## Aplicación por pantalla

### Acceso

Panel blanco centrado, ancho máximo 340 px, título «Inicia sesión», breve ayuda, correo, contraseña, mantener sesión y botón. El fondo es neutro. Se retiran logo, monograma, órbitas, estadísticas inventadas y bloque promocional. Se conservan autenticación, autocompletado y mensajes del servidor.

### Resumen

Título «Resumen» y botón «Nuevo contrato». Cuatro métricas con datos reales agrupadas en una franja continua con separadores. Los contratos recientes aparecen antes de los gráficos para priorizar el acceso al expediente. Actividad por mes con eje de cantidades, seis etiquetas y valores legibles. Estado actual con total y leyenda textual. Tabla de contratos recientes con la misma anatomía del listado completo. Cuando no hay contratos, los gráficos muestran el vacío sin sugerir actividad inexistente.

### Contratos y directorios

Un encabezado y un panel principal con filtros, resultados y paginación. El listado de contratos conserva todos sus filtros. Trabajadores y empresas conservan sus búsquedas. Nombres y documentos ganan presencia; se eliminan iniciales decorativas y cabeceras en mayúsculas. Las restricciones por rol se mantienen en cada acción.

### Formularios

Secciones «Información general», «Base salarial», «Días para la liquidación» e «Indemnización y deducciones», separadas por espacio y una línea suave. Dos columnas en datos generales, tres en valores numéricos y una en móvil, dentro de un formulario de 800 px como máximo. Etiquetas, ayudas y errores con el mismo sistema. «Cancelar» y la acción de guardado cierran el formulario. Se conservan nombres de campos, valores previos, límites y validaciones.

### Contrato y trabajador

Identidad, empresa, estado y acciones de consulta arriba. Datos agrupados en paneles de detalle. Historial con la tabla común. La eliminación se presenta al final, separada del flujo principal, y conserva la confirmación existente.

### Liquidación

El neto se destaca en un panel azul intenso con texto blanco y cifra de 26 px y contexto de moneda; devengado y deducciones son métricas secundarias. Desglose principal y parámetros en dos columnas en escritorio, una en móvil. Los conceptos, fórmulas, advertencias y resultados se mantienen completos. «Exportar a excel» es la acción principal y «Guardar en historial» la secundaria. El historial se describe como «Resultados guardados» en lugar de exponer términos de implementación.

### Catálogos

Listado editable y formulario de creación comparten campos y botones. Controles de guardar y eliminar tienen nombres accesibles. Los códigos conservan sus valores originales y la lógica existente.

## Interacción y accesibilidad

Los enlaces navegan y los botones ejecutan acciones. El foco de teclado siempre es visible. Cada icono accionable tiene un nombre accesible. Los errores del servidor aparecen debajo del control, fuera de su etiqueta, asociados mediante `aria-describedby` y `aria-invalid`. Al editar o restablecer el formulario se retira el error correspondiente al valor anterior, conservando las ayudas asociadas. El servidor mantiene la validación final. Los prefijos monetarios se ocultan del nombre accesible. El menú móvil debe poder abrirse y cerrarse por teclado y anunciar su estado.

Se usa `body-secondary` para texto auxiliar de lectura; `muted` se reserva para placeholders, iconos auxiliares y estados deshabilitados. Las etiquetas persistentes usan texto negro y los estados suaves conservan texto oscuro. Se revisan anchos de 1440, 1024, 768 y 390 px, navegación con teclado, zoom al 200 %, nombres largos, importes extensos, errores y estados sin datos.

## Integración y comprobación

La fuente de verdad visual es `public/css/app.css`, organizada en tokens, fundamentos, estructura, componentes y adaptación de pantalla. Las vistas Blade conservan sus rutas y enlaces. Las vistas de documentación reutilizan esa hoja; `docs/preview.css` solo la importa para mantener la compatibilidad.

La aplicación se considera terminada cuando:

- Toda la interfaz usa Inter 500; texto con tracking 0 y títulos con `-0.04em`.
- No quedan logos, monogramas, wordmarks ni texto de interfaz en mayúsculas sostenidas.
- Las tablas, campos, botones y avisos usan los mismos tokens en todas las pantallas.
- Los gráficos representan cantidades reales, incluido el cero, y las cifras monetarias no se recortan.
- Los permisos, cálculos, formularios y exportaciones mantienen su funcionamiento.
- Se ha revisado visualmente escritorio y móvil, y se han comprobado los estados de foco, error y vacío.

## Implementación

El sistema se aplica al acceso, el resumen, los directorios, los formularios, los detalles, los catálogos y la liquidación. Inter medium e iconos Lucide se sirven localmente con sus licencias, sin una dependencia de red durante el uso de la aplicación. Los estilos y scripts incluyen una versión de recurso para que el navegador reciba las correcciones tras una actualización.

La guía distingue el formulario normal del ejemplo explícito de error del servidor. Los campos de texto y las casillas tienen selectores de estilo separados, también en móvil; las casillas no heredan el alto, relleno o tamaño tipográfico del campo de texto. Las fechas y los prefijos monetarios usan envoltorios válidos y etiquetas enlazadas por `for`/`id`.

Capturas de las vistas previas con datos de ejemplo:

- [Resumen en escritorio](screenshots/dashboard-desktop.jpg).
- [Resumen en móvil](screenshots/dashboard-mobile.jpg).
- [Acceso en móvil](screenshots/login-mobile.jpg).
- [Detalle de botones](screenshots/buttons-desktop.jpg).
- [Campos y estados en escritorio](screenshots/components-desktop.jpg).
- [Campos en móvil](screenshots/components-mobile.jpg).

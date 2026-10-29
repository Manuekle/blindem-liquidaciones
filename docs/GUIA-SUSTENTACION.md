# Guía para sustentar la prueba técnica de Blindem

**Entrega requerida:** 4 de noviembre de 2026, antes de las 10:00 a. m. (Colombia). La sustentación indicada en el correo inicia el jueves; **confirma con la empresa el día exacto**, porque el 4 de noviembre de 2026 es miércoles.

## Video funcional sugerido (4 a 6 minutos)

| Tiempo | Pantalla | Explicación |
|---|---|---|
| 0:00–0:30 | Login y dashboard | Qué problema resuelve y stack Laravel + PostgreSQL |
| 0:30–1:00 | Trabajadores / empresas | Modelo relacional y múltiples contratos |
| 1:00–1:40 | Contratos | Crear/editar, estado, filtros por tipo y número de documento |
| 1:40–3:20 | Liquidación | Cálculo detallado de cesantías, intereses, prima, vacaciones, salario, auxilio, indemnización y descuentos |
| 3:20–4:00 | Excel | Exportar XLSX, revisar segunda hoja de parámetros y fuentes |
| 4:00–4:40 | Administrador | CRUD de catálogos, permisos colaborador, validaciones |
| 4:40–5:00 | Historial | Guardar snapshot de resultados con versión de cálculo |

## Guion oral (natural)

“Esta es mi solución a la prueba de Blindem. Construí una aplicación con Laravel y PostgreSQL. Aquí puedo registrar empresas y trabajadores y asociar distintos contratos, incluso en varias empresas. El colaborador gestiona contratos y consulta sus liquidaciones, mientras el administrador puede configurar los catálogos generales. Para calcular una liquidación, primero registro fechas, salarios y días pendientes, y después el sistema presenta cada concepto por separado. El Excel que me compartieron tenía ciertas inconsistencias, especialmente en vacaciones, por lo que documenté y corregí la interpretación de los días. También implementé la indemnización según el tipo de contrato y el rango salarial. Cada cálculo se puede exportar a Excel y guardar en un historial con sus parámetros, para poder auditar resultados. Finalmente, adapté la interfaz para escritorio y móvil y agregué validaciones en los formularios y pruebas sobre las fórmulas.”

## Caso de demo recomendado

1. Iniciar sesión como administrador.
2. Abrir contrato semilla de Juan Carlos Pérez.
3. Mostrar salario básico 1.750.905, auxilio 249.095, extras 150.000; cesantías/prima 180 días, vacaciones pendientes 15 días, salario/auxilio pendientes 15 días.
4. Abrir liquidación y explicar el resultado con indemnización por despido sin justa causa.
5. Exportar el Excel, abrir la hoja de parámetros y guardar en el historial.
6. Probar colaborador: acceso a contratos y bloqueo de `catalogos/*` con HTTP 403.

**Advertencia:** el video es una guía para grabarlo después de desplegar y ejecutar la aplicación. Este paquete no incluye una grabación funcional real.

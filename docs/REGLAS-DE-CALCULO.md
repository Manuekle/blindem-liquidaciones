# Reglas y casos de aceptación del motor de liquidación

Las liquidaciones corresponden a contratos laborales ordinarios del sector privado en Colombia. Los campos de promedios se calculan previamente a partir de la nómina efectiva: la aplicación no reconstruye pagos mensuales ni liquida salario integral, incapacidades, embargos, retención o aportes patronales.

## Entradas y bases

- `average_extras`: promedio salarial de horas extras y trabajo en días de descanso obligatorio. Integra prestaciones e indemnización; se excluye de la base de vacaciones.
- `average_surcharges`: promedio salarial de recargos ordinarios/nocturnos que sí integran vacaciones. No deben duplicarse en el campo anterior.
- Base prestacional: básico + ambos promedios + auxilio habilitado. El auxilio se excluye si el salario y promedios superan dos salarios mínimos de la vigencia.
- Base vacaciones: básico + recargos ordinarios/nocturnos. `vacation_days_pending` son días de descanso ya causados y no disfrutados, nunca días de servicio. Importe = base / 30 × días de descanso.
- Base indemnización: básico + ambos promedios salariales, sin transporte. Motivo de retiro y método del tipo de contrato determinan su aplicación. Cambiar el código del tipo no cambia su método.
- Salario pendiente: básico / 30 × días pendientes; el promedio histórico variable no se vuelve a pagar como una deuda de nómina. Si hay componentes efectivamente adeudados adicionales, debe tratarse su liquidación de nómina específica.
- Salud y pensión: tarifas registradas para el año, sobre el salario pendiente, excluyendo vacaciones compensadas, prestaciones, transporte e indemnización. Este cálculo no sustituye la PILA ni su determinación completa del IBC.
- Cesantías: base prestacional × días / 360. Intereses: cesantías redondeadas × días × tarifa anual / 360. Prima: base prestacional × días / 360. Los periodos automáticos son año y semestre de la fecha de corte, respectivamente; se permiten días explícitos para saldos pendientes.
- Importes enteros COP, redondeo de mitad hacia arriba por concepto. Neto = suma de devengados − suma de deducciones.

Para registros anteriores con un importe combinado no se inventa su composición: se conserva y se exige revisión explícita al editar. Los snapshots anteriores no se recalculan con estas reglas.

## Días automáticos

Para no digitar lo que se deduce de las fechas, los campos de días pueden dejarse en blanco. Un valor escrito (incluido 0) siempre prevalece, y el formulario muestra en cada campo el valor que se usará.

- Cesantías: días 30/360 desde el 1 de enero del año de corte (o desde el ingreso, si es posterior), máximo 360.
- Prima: días 30/360 desde el inicio del semestre de corte (o desde el ingreso), máximo 180.
- Vacaciones pendientes: 15 días hábiles por cada 360 días de servicio, menos los días ya disfrutados que se registren en el contrato. Es la misma regla de la plantilla (`base × días trabajados / 720`) expresada en días de descanso: `base / 30 × (días × 15 / 360)`.
- Salario y auxilio pendientes: días 30/360 del último mes hasta la fecha de corte (o desde el ingreso, si es posterior).

La liquidación y el Excel indican qué valores fueron automáticos. Para un contrato activo la fecha de corte es hoy, así que los días avanzan solos.

## Vigencia e indemnización

La fecha de retiro determina el salario mínimo y las tarifas. Para una simulación de contrato activo se usa la fecha actual. Si falta la vigencia, no se calcula ni guarda un resultado.

| Año | Salario mínimo | Auxilio de referencia |
| --- | ---: | ---: |
| 2023 | $1.160.000 | $140.606 |
| 2024 | $1.300.000 | $162.000 |
| 2025 | $1.423.500 | $200.000 |
| 2026 | $1.750.905 | $249.095 |

Las fuentes oficiales quedan asociadas al registro anual y se copian al snapshot y al Excel. La cifra de auxilio registrada en el contrato se conserva como entrada explícita; el formulario muestra la referencia de la vigencia y adapta los valores sugeridos en contratos nuevos cuando aún no se han modificado manualmente.

El motivo de retiro es obligatorio en contratos retirados. `implies_indemnification` es la fuente de la decisión; no hay una casilla independiente que pueda contradecirlo. En indefinidos, menos de diez SMLMV aplica 30 días el primer año y 20 por año adicional; desde diez SMLMV aplica 20 y 15. El motor conserva la convención de la guía para antigüedad: días calendario inclusivos y proporcionalidad sobre 365. En fijos se requieren las fechas pactadas y se pagan los días calendario faltantes. Obra/labor requiere valor manual sustentado, con mínimo de quince días de salario; otros métodos manuales requieren importe positivo. La indemnización automática puede reemplazarse por un importe manual positivo sustentado.

## Comparación reproducible con la plantilla

Caso de la primera hoja: 2023-01-15 a 2024-06-30, básico $1.750.905, variable $150.000, transporte explícito $249.095, 180 días cesantías/prima, 15 días de descanso pendientes, 15 días salario/transporte, sin indemnización.

| Concepto | Plantilla original | Variable como horas extras | Variable como recargo ordinario |
| --- | ---: | ---: | ---: |
| Cesantías | $1.075.000 | $1.075.000 | $1.075.000 |
| Intereses | $64.500 | $64.500 | $64.500 |
| Prima | $1.075.000 | $1.075.000 | $1.075.000 |
| Vacaciones | $39.602 | $875.453 | $950.453 |
| Salud | $36.602 | $35.018 | $35.018 |
| Pensión | $36.602 | $35.018 | $35.018 |
| Neto | $3.180.899 | $4.019.918 | $4.094.918 |

La plantilla divide por 720 una cantidad etiquetada como días de vacaciones pendientes. Ese divisor corresponde a días de servicio causantes, no a días de descanso ya adquiridos. También incluye la compensación de vacaciones en la base de salud/pensión. Las diferencias se explicitan en lugar de convertirlas en un modo alternativo ambiguo.

En la segunda hoja, 2021-03-15 a 2024-06-30, el motor calcula 75,973 días y $4.813.890 de indemnización con base salarial $1.900.905. La plantilla muestra $4.434.027 usando solamente básico. Con la variable separada como extras, el neto del motor es $8.833.808; con variable ordinaria, $8.908.808. No se reutilizan los 180 días constantes de la fórmula de término fijo del Excel ni su condición `<= 10 SMLMV`.

## Conservación del historial

Cada snapshot nuevo guarda nombres, documentos, empresa, cargo, tipo/motivo, fechas, inputs, tarifas, fuentes, resultado y versión. Consultarlo y exportarlo no vuelve a consultar la vigencia ni a calcular. Las versiones antiguas sin nombres guardados se identifican expresamente y muestran identificación actual junto con sus importes originales. No se pueden eliminar contratos con historial ni motivos de retiro en uso.

## Fuentes consultadas

- [CST, artículos 64, 189 y 192](https://www.cancilleria.gov.co/sites/default/files/Normograma/docs/codigo_sustantivo_trabajo.htm).
- [Función Pública, Concepto 195021 de 2022: vacaciones y aportes](https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=195887).
- [Corte Suprema: base salarial de indemnización, SL1659-2025](https://cortesuprema.gov.co/corte-suprema-precisa-el-salario-base-para-liquidar-la-indemnizacion-por-despido-sin-justa-causa/).
- [SMLMV 2024, Decreto 2292 de 2023](https://www.cancilleria.gov.co/sites/default/files/Normograma/docs/decreto_2292_2023.htm).
- [Auxilio 2024, Decreto 2293 de 2023](https://www.cancilleria.gov.co/normograma/compilacion/docs/decreto_2293_2023.htm).
- [SMLMV 2025, Decreto 1572 de 2024](https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=257156).
- [SMLMV 2026, Decreto 159 de 2026](https://normativa.colpensiones.gov.co/compilacion/docs/decreto_0159_2026.htm).

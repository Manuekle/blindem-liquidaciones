# Guion de estudio para la sustentación

Complemento de las slides ([index.html](index.html)). Léelo en este orden: primero las ideas clave, luego el guion por slide, luego practica las preguntas. Tiempo objetivo: **12 a 15 minutos** de exposición + demo + preguntas.

---

## 1. Las 5 ideas que no puedes olvidar

1. **Problema:** liquidar a mano en Excel es lento, se equivoca y no deja rastro.
2. **Solución:** una app que calcula con la norma del año, explica cada cifra y guarda el resultado tal como se entregó.
3. **Decisión clave:** el Excel de la prueba tiene supuestos distintos a la norma, así que hay **dos métodos**: *Plantilla Blindem* (da el Excel al peso) y *Legal ajustado*.
4. **Técnica:** monolito Laravel 12 con un **motor de cálculo puro** (sin base de datos), por eso es fácil de probar y siempre da lo mismo.
5. **Confianza:** las cifras del Excel son pruebas automáticas, y la IA solo explica, nunca calcula.

Frase de cierre sugerida: *"El Excel es el requisito, la norma es la defensa, y la app deja elegir y registrar cuál se usó."*

---

## 2. Cifras para memorizar

**Hoja 1** (15/01/2023 a 30/06/2024, sin indemnización)

| Concepto | Valor |
|---|---:|
| Cesantías | $1.075.000 |
| Intereses de cesantías | $64.500 |
| Prima | $1.075.000 |
| Vacaciones (método Plantilla) | $39.602 |
| Salario pendiente (15 días) | $875.453 |
| Auxilio pendiente (15 días) | $124.548 |
| Total devengado | $3.254.103 |
| Salud y pensión (4% cada una) | $36.602 + $36.602 |
| **Neto** | **$3.180.899** |

**Hoja 2** (15/03/2021 a 30/06/2024, despido sin justa causa)

- 1.204 días calendario = 3 años y 109 días.
- 75,97 días de indemnización → **$4.434.027** (sobre el básico $1.750.905).
- Devengado $7.688.130 → **neto $7.614.926**.

**Método Legal ajustado** (para comparar): hoja 1 neto $4.019.918; hoja 2 indemnización $4.813.890 y neto $8.833.808.

**Datos base:** básico $1.750.905, auxilio $249.095, extras promedio $150.000, SMLMV 2024 $1.300.000.

---

## 3. Guion por slide (qué decir)

Cada bloque es ~30 a 45 segundos. No leas la slide: cuenta la idea con tus palabras.

**1. Portada**
> "Soy [nombre]. Construí una aplicación que automatiza la liquidación de contratos laborales en Colombia. Les voy a contar primero el problema de negocio y las reglas, y después cómo lo programé, probé y desplegué."

**2. Agenda**
> "Dos bloques: negocio y técnica. Empiezo por negocio porque cada decisión técnica nace de una regla de negocio."

**3. El problema**
> "Hoy se liquida en plantillas Excel. Se cuentan los días a mano con la convención 30/360, el salario mínimo y el auxilio cambian cada año y la plantilla se queda vieja, y no queda rastro de con qué parámetros se calculó. Un error se paga en dinero o en una demanda. Mi objetivo: calcular con la norma del año, explicar cada cifra y guardar el resultado."

**4. Qué se paga**
> "Al terminar un contrato se paga cesantías, intereses sobre cesantías, prima, vacaciones, el salario y auxilio pendientes, y la indemnización si hay despido sin justa causa. Se descuenta salud y pensión. El neto es devengado menos deducciones, en pesos enteros."

**5. Reglas clave**
> "Cuatro reglas gobiernan casi todo. Año comercial de 360 días. El auxilio de transporte solo entra si se gana hasta 2 salarios mínimos. En indefinido, la indemnización es 30 días el primer año y 20 por cada año adicional si se gana menos de 10 salarios mínimos; desde 10, son 20 y 15. En término fijo se pagan los días que faltan para terminar el plazo."

**6. El Excel**
> "La prueba trae dos escenarios del mismo trabajador, Juan Carlos Pérez. La hoja 1 es una terminación sin indemnización: neto $3.180.899. La hoja 2 es un despido sin justa causa desde 2021: indemnización $4.434.027 y neto $7.614.926."

**7. Hallazgos** *(la parte que más demuestra criterio)*
> "Al revisar las fórmulas encontré cuatro supuestos que difieren de la norma. Uno: vacaciones divide por 720 una cantidad que son días de descanso, y da $39.602 cuando debería ser $875.453. Dos: salud y pensión incluyen las vacaciones. Tres: la indemnización usa solo el básico, no los promedios salariales. Cuatro: en término fijo usa 180 días fijos. No los corregí a escondidas ni los copié a ciegas: los documenté."

**8. Decisión**
> "Entonces no impuse una sola verdad. El contrato elige método. 'Plantilla Blindem' reproduce el Excel al peso, y 'Legal ajustado' aplica el criterio de la norma. El método queda guardado en cada liquidación."

**9. Solución en pantalla**
> "Así se ve: arriba el neto, abajo cada concepto con sus días y su fórmula, y una explicación en lenguaje claro para revisar con el trabajador."

**10. Arquitectura**
> "Es un monolito: Laravel 12 con PHP 8.3 y PostgreSQL 16. El navegador pide páginas a controladores delgados; las reglas viven en servicios; los datos en la base. Lo elegí por simplicidad: un contenedor para la app y otro para la base."

**11. Datos**
> "Cuatro ideas: personas, contratos, parámetros por año con su decreto como fuente, e historial. Cada liquidación guarda sus entradas, su resultado y la versión del motor."

**12. Motor**
> "El motor es una función pura: recibe fechas, salarios, parámetros y método; aplica las reglas; devuelve importes enteros y las fórmulas en texto. No toca la base de datos, por eso es fácil de probar y siempre da la misma respuesta. Redondea mitad hacia arriba por concepto y tiene número de versión."

**13. Flujo**
> "Contrato, parámetros del año de retiro, cálculo, Excel e historial. Lo importante: el historial es inmutable. Si cambia la norma, lo pasado no se recalcula."

**14. IA**
> "La IA es un complemento. Explica la liquidación, responde preguntas citando normas y extrae datos de un texto libre. Tiene un modo simulado por reglas sin red y otro con un modelo local. Lo que dice el modelo se verifica contra los datos y, si no coincide, se descarta. Nunca cambia una cifra."

**15. Calidad y seguridad**
> "Las cifras del Excel son pruebas automáticas: si alguien rompe una fórmula, el test falla. Son 482 aserciones y no tocan la base real. En seguridad: roles, protección CSRF, límite de peticiones, y el Excel exportado escapa el texto para evitar inyección de fórmulas."

**16. Despliegue**
> "Mismo contenedor en local y producción. Hago commit, push a main, Render construye la imagen Docker, aplica las migraciones al arrancar y queda en vivo con PostgreSQL gestionado."

**17. Demo** → ver sección 4.

**18. Cierre**
> "Dejé fuera nómina completa, PILA, salario integral y embargos a propósito. Lo siguiente sería importar el Excel directamente, revisión jurídica de casos límite y auditoría de cambios. Quedo atento a sus preguntas."

---

## 4. Demo en vivo (4 minutos)

Prepara local y prod abiertos en dos pestañas. Si prod está lento (plan gratuito, tarda en despertar), usa local.

1. **Contratos** → abre el de Juan Carlos Pérez con ingreso 15/01/2023 (hoja 1).
2. **Liquidación** → muestra el neto **$3.180.899** y baja por el desglose: "cada concepto trae días y fórmula".
3. **Editar datos** → cambia el método de cálculo a *Legal ajustado* → vuelve a la liquidación → muestra que el neto sube a $4.019.918. Explica por qué (vacaciones). **Vuelve a dejarlo en Plantilla.**
4. **Hoja 2** → abre el contrato de 2021 → indemnización $4.434.027 y neto $7.614.926.
5. **Exportar a Excel** → abre el archivo: hoja de liquidación y hoja de parámetros y fuentes.
6. **Guardar y historial** → "queda guardada como una fotografía, no se recalcula".
7. *(Opcional)* **Explicar liquidación** con el botón de IA.

> Antes de la demo: confirma que **el método quedó en Plantilla** en los dos contratos del Excel y que los netos coinciden.

---

## 5. Conceptos que debes dominar (por si preguntan)

- **Cesantías:** ahorro del trabajador por año de servicio. Base × días / 360.
- **Intereses de cesantías:** 12% anual sobre las cesantías, proporcional a los días.
- **Prima de servicios:** un salario por año, pagado por semestres. Base × días / 360.
- **Vacaciones:** 15 días hábiles de descanso por año. Se compensan en dinero al terminar.
- **Base prestacional:** salario + auxilio de transporte + promedio de extras y recargos.
- **Auxilio de transporte:** se paga a quien gana hasta 2 salarios mínimos.
- **Indemnización (art. 64 CST):** compensación por despido sin justa causa.
- **SMLMV:** salario mínimo mensual legal vigente (2024: $1.300.000).
- **30/360:** convención en la que todos los meses tienen 30 días y el año 360.
- **Snapshot:** copia fija del cálculo con sus entradas, resultado y versión del motor.

---

## 6. Preguntas probables y cómo responder

**¿Por qué dos métodos y no uno?**
El Excel es el requisito de la prueba y la norma es la defensa jurídica. No quise decidir por el usuario: el contrato elige y queda registrado en el historial.

**¿Cuál es el correcto?**
Depende del criterio. *Plantilla* reproduce lo que pidió el cliente. *Legal ajustado* corrige vacaciones, aportes y base de indemnización según la norma y la jurisprudencia citada, pero para un caso real conviene revisión jurídica.

**¿Por qué no usar IA para calcular?**
Con dinero se necesita un resultado exacto, repetible y auditable. La IA puede equivocarse. Por eso solo explica; el motor decide la cifra, y lo que dice el modelo se verifica contra los datos.

**¿Qué pasa si cambia el salario mínimo?**
Se actualiza la tabla de parámetros del año. Las liquidaciones ya guardadas no cambian, porque son una copia fija.

**¿Cómo evitas errores de redondeo?**
Todo en pesos enteros, con redondeo mitad hacia arriba por concepto, igual que el `ROUND` del Excel.

**¿Cómo sabes que coincide con el Excel?**
Hay pruebas automáticas que comparan cada cifra de las dos hojas con el resultado del motor. Si algo cambia, el test falla.

**¿Por qué Laravel y un monolito?**
Entrega rápida, migraciones, validación y pruebas integradas, y operación simple: un contenedor de app y uno de base de datos. Para este tamaño de problema, microservicios serían complejidad sin beneficio.

**¿Cómo despliegas?**
Push a `main`, Render construye la imagen Docker, aplica migraciones al arrancar y publica. Sin pasos manuales.

**¿Qué falta o qué mejorarías?**
Importar el Excel para crear el contrato automáticamente, revisión jurídica de casos límite, auditoría de cambios y autenticación en dos pasos. Dejé fuera nómina completa, PILA, salario integral y embargos a propósito.

**¿Cómo manejas la seguridad?**
Roles de administrador y colaborador, protección CSRF, límite de peticiones y escape de texto en el Excel exportado. Las cuentas demo no deben existir en producción real.

**¿Qué haría si el modelo de IA no está disponible?**
Hay un modo simulado por reglas que funciona sin red. La aplicación nunca depende de la IA para calcular.

### Preguntas trampa (responde con honestidad)

- **"¿Esto está listo para producción real?"** → Es una base sólida y probada para el caso del Excel, pero faltaría revisión jurídica, auditoría de cambios y MFA antes de usarlo con clientes.
- **"¿Por qué el Excel y tu motor dan distinto?"** → Porque el Excel divide vacaciones por 720 y usa solo el básico en la indemnización. Lo documenté y por eso ofrezco el método Plantilla, que da el Excel al peso.
- **"No sé / no lo probé"** → Dilo directo. Ejemplo: *"No lo verifiqué en ese caso. Lo que sí probé es X."*

---

## 7. Dónde está cada cosa en el código (por si te piden mostrar)

| Qué | Archivo |
|---|---|
| Motor de cálculo | `app/Services/LaborSettlementCalculator.php` |
| Arma las entradas desde el contrato | `app/Services/SettlementInputs.php` |
| Pantalla, guardado y Excel exportado | `app/Http/Controllers/SettlementController.php` |
| Pruebas del motor (casos del Excel) | `tests/Unit/LaborSettlementCalculatorTest.php` |
| Reglas y decisiones por escrito | `docs/REGLAS-DE-CALCULO.md` |
| Parámetros por año | migraciones en `database/migrations/` |
| Datos del Excel precargados | `database/seeders/DemoContractsSeeder.php` |
| Despliegue | `Dockerfile`, `docker/entrypoint.sh` |

Si piden "muéstrame el código", abre el motor y señala solo tres cosas: la variable `$template` (el método), el cálculo de vacaciones (÷720 vs ÷30) y que el servicio no usa base de datos.

---

## 8. Checklist del día anterior

- [ ] Abrir las slides en el navegador y recorrerlas con las flechas. **N** muestra tus notas.
- [ ] Probar la demo completa en local y en prod.
- [ ] Confirmar que los dos contratos del Excel están en método **Plantilla** con netos $3.180.899 y $7.614.926.
- [ ] Prod se duerme en el plan gratuito: abrirlo 5 minutos antes.
- [ ] Cambiar o borrar las cuentas demo de prod (contraseña documentada en el repo).
- [ ] Tener a la mano el Excel original abierto por si piden comparar.
- [ ] Cargar el portátil, tener internet de respaldo y la versión local lista.

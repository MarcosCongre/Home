# Brief: Exponer metadatos de Task en el controlador HTTP

## Solicitud original
Actualizar `TaskController` para leer `day`, `time`, `category`, `recurrence` y `priority` como strings opcionales en las operaciones de creación y actualización, validar `day` y `priority`, pasarlos a sus commands y añadirlos a la respuesta serializada de cada tarea.

## Objetivo
Completar el contrato HTTP para que los metadatos de tarea puedan recibirse en las solicitudes y devolverse en las respuestas con los nombres que ya consume el mapper del frontend.

## Enfoque propuesto
Extraer los cinco valores opcionales del payload tanto en `create()` como en `update()` y entregarlos a los commands correspondientes. Rechazar los valores no permitidos de día o prioridad mediante `InvalidArgumentException`, aprovechando el manejo existente en `index.php`, que responde HTTP 400. Añadir los cinco campos al array de `serializeTask()`.

## Alcance
### Incluido
- Leer y pasar los campos opcionales `day`, `time`, `category`, `recurrence` y `priority` al crear y actualizar tareas.
- Aceptar para `day` únicamente `Mon`, `Tue`, `Wed`, `Thu`, `Fri`, `Sat` o `Sun`.
- Aceptar para `priority` únicamente `low`, `med` o `high`.
- Lanzar `InvalidArgumentException` cuando `day` o `priority` no cumplan esos valores permitidos; la capa HTTP existente devuelve estado 400.
- Incluir en la serialización `day`, `time`, `category`, `recurrence` y `priority`, usando sus respectivos getters de `Task`.

### Fuera de alcance
- Cambios en el mapper del frontend, que ya reconoce estos nombres.
- Nuevas migraciones de base de datos: la tabla `tasks` ya tiene las cinco columnas.
- Reglas de validación de `time`, `category` y `recurrence`, fuera de las validaciones solicitadas para `day` y `priority`.

## Supuestos
- Los campos ausentes o `null` se consideran opcionales y se transmiten como `null`.
- Las validaciones de día y prioridad aplican cuando esos campos tienen un valor; los valores permitidos distinguen mayúsculas y minúsculas tal como están especificados.
- El esquema ya aplicado define `day VARCHAR(3)`, `time VARCHAR(8)`, `category VARCHAR(64)`, `recurrence VARCHAR(32)` y `priority VARCHAR(8) NOT NULL DEFAULT 'med'`; el controller debe respetar estas capacidades y el default de prioridad.
- Al centralizar los campos en `serializeTask()`, también aparecerán en otras respuestas que reutilizan este método, como listado y finalización.

## Preguntas abiertas
- No se especifica si una cadena vacía debe tratarse como un campo ausente o como un valor inválido. Conviene mantener un comportamiento consistente con la representación de opcionales que use el controller.

## Siguiente paso
Este brief queda como entrada para elaborar el plan técnico de implementación del controller.
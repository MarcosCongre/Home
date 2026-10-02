# Brief: Propagar metadatos de Task desde commands y handlers

## Solicitud original
Ampliar `CreateTaskCommand` y `UpdateTaskCommand` con los campos nullable `day`, `time`, `category`, `recurrence` y `priority`, y hacer que sus handlers transmitan esos valores al flujo de creación o actualización de `Task`.

## Objetivo
Transportar los nuevos metadatos desde la capa de aplicación hasta la entidad tanto al crear como al actualizar una tarea.

## Enfoque propuesto
Extender ambos commands para aceptar los cinco valores opcionales y modificar sus handlers para incluirlos en la llamada existente a `Task::create()` o `Task::update()`, según la API final de la entidad. Mantener el comportamiento existente de título, estado y miembro asignado.

## Alcance
### Incluido
- Añadir a ambos commands las propiedades nullable solicitadas: `?string $day`, `?string $time`, `?string $category`, `?string $recurrence` y `?string $priority`.
- Pasar esos valores desde `CreateTaskHandler` al crear la entidad.
- Pasar esos valores desde `UpdateTaskHandler` al actualizar la entidad.
- Conservar el flujo actual de asignación de miembro y estado.

### Fuera de alcance
- Cambios en persistencia, esquema de base de datos, endpoints o interfaz.
- Añadir validaciones o transformar los valores recibidos por los commands.

## Supuestos
- Los valores de los commands pueden omitirse y llegar como `null`.
- La implementación aprovechará los métodos de creación y actualización definidos para la entidad en el punto de trabajo correspondiente.
- La tabla `tasks` ya dispone de las columnas correspondientes; commands y handlers solo transportan los valores y no requieren cambios de esquema.

## Preguntas abiertas
- El brief de la entidad define `priority` como `string` con valor predeterminado `med`, mientras que este pedido especifica `?string $priority` en los commands. Debe confirmarse que `null` representa “sin prioridad explícita” y que al crear se conserva `med` como valor efectivo.
- En actualizaciones, debe confirmarse si un `null` significa conservar el valor existente o limpiar el campo. La firma nullable por sí sola no distingue entre “no enviado” y “borrar”.

## Siguiente paso
Este brief queda como entrada para elaborar el plan técnico de implementación de commands y handlers.
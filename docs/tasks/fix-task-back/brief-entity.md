# Brief: Ampliar la entidad Task con metadatos

## Solicitud original
Ampliar `App\Domain\Tasks\Task` con día, hora, categoría, recurrencia y prioridad. Los campos deben conservar valores opcionales o predeterminados, poder actualizarse y exponerse mediante getters, manteniendo la compatibilidad con los argumentos nombrados existentes.

## Objetivo
Permitir que una tarea represente información de planificación y clasificación adicional sin perder compatibilidad con la construcción actual de tareas.

## Enfoque propuesto
Incorporar los cinco datos como estado de la entidad, con valores predeterminados apropiados; permitir que `update()` los reciba y los asigne, y ofrecer un getter para cada uno. La creación de tareas podrá conservarse sin cambios o aceptar estos datos si el flujo que la invoque los necesita; se prioriza la alternativa más sencilla.

## Alcance
### Incluido
- Agregar `day`, `time`, `category` y `recurrence` como valores opcionales, y `priority` con valor predeterminado `med`.
- Añadir los parámetros al final del constructor para no romper los argumentos nombrados existentes.
- Extender `update()` para aceptar y asignar los cinco valores.
- Añadir los getters `day()`, `time()`, `category()`, `recurrence()` y `priority()`.

### Fuera de alcance
- Cambios de esquema o persistencia en repositorios y base de datos.
- Cambios en handlers, endpoints o interfaz para enviar/mostrar estos valores.
- Definir validaciones o un catálogo de valores permitidos para prioridad, categoría o recurrencia.

## Supuestos
- `null` es válido para día, hora, categoría y recurrencia.
- La prioridad predeterminada `med` es el valor acordado y debe aplicarse a tareas que no reciban prioridad explícita.
- La tabla `tasks` ya tiene las cinco columnas mediante la migración aplicada; este punto sigue limitado a la entidad de dominio y no requiere otra migración de base de datos.
- Los metadatos se mantienen como cadenas sin validaciones de formato en la entidad; las longitudes y valores permitidos de almacenamiento se definen en la base de datos y en el controller.

## Preguntas abiertas
- No se especifican formatos ni restricciones para día, hora, categoría o recurrencia; se mantienen como cadenas sin reglas adicionales en este alcance.

## Siguiente paso
Este brief queda como entrada para elaborar el plan técnico de implementación.
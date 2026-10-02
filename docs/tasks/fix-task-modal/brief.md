# Brief: Calendar Task Modal Integration (Revision 2)

> Revision 2 expands the original modal brief to include the explicitly requested CalendarView integration. It supersedes the earlier open question about whether calendar integration was in scope.

## Original Request

Corregir `CalendarView` para que deje de contener estado y controles copiados de `MembersView`, y permita crear y editar tareas mediante `TaskModal`. Conectar las operaciones de tareas desde `App.tsx`, utilizar el contrato `TaskFormData` para crear y actualizar, y conservar los controles existentes de días, filtro por usuario y tarjetas de tareas.

## Objective

Asegurar que calendario y modal compartan un flujo coherente de creación y edición de tareas: una cabecera única, selección de día y filtro por usuario conservados, tareas del día editables directamente y campos del formulario enviados por las operaciones de aplicación.

## Proposed Solution Approach

Alinear `CalendarView` con las propiedades de tareas, usuarios y callbacks de creación/actualización; usar `TaskFormData` como contrato del modal y de las operaciones de guardado; mantener los estilos y controles establecidos; y eliminar únicamente la lógica copiada de selección, edición y borrado de miembros. Los campos de tarea deben llegar a la API desde el frontend. La persistencia de esos metadatos en backend queda como una fase separada.

## Scope

### In Scope

- Definir el contrato de `CalendarView` para `tasks`, `users`, `onSelect`, `onCreateTask` y `onUpdateTask`, con el payload compartido `TaskFormData`.
- Conservar solo el estado de día activo, filtro de usuario y modal de tarea.
- Mantener una sola cabecera de calendario con acción para crear; eliminar el encabezado duplicado y los controles de edición/borrado de miembros.
- Mantener selector de días, filtro de usuarios, tarjetas con categoría/recurrencia/hora/avatar y estado vacío; abrir el modal de tarea al crear o seleccionar una tarjeta.
- Renderizar el modal y guardar con la operación correspondiente para alta o edición, cerrándolo tras guardar.
- Conectar las operaciones reales de `useTasks` desde `App.tsx` y transportar día, hora, categoría, recurrencia y prioridad por las capas frontend existentes.
- Preservar los estilos visuales establecidos.

### Out of Scope

- Rediseñar otras pantallas o cambiar el estilo general de la aplicación.
- Implementar la persistencia backend de día, hora, categoría, recurrencia o prioridad; esto corresponde a una fase backend posterior y no bloquea el frontend.
- Cambiar flujos de miembros o de otras pantallas.

## Assumptions

- `TaskModal` exporta `TaskFormData` y es la fuente de verdad del payload del formulario.
- El usuario confirma que la integración de `TaskModal` dentro de `CalendarView` sí pertenece al alcance.
- Los metadatos de tarea ya se incluyen en las peticiones frontend, pero el backend actual no los persiste: su esquema, modelo de dominio y repositorio solo manejan título, estado, hogar, miembro asignado y marcas temporales.

## Open Questions / Missing Information

- No hay preguntas bloqueantes para la integración frontend.
- La persistencia backend de los campos adicionales sigue pendiente para una fase backend; el frontend debe quedar conectado sin esperarla.

## Next Step

`orch-dev` debe actualizar el plan técnico existente para incorporar el alcance confirmado de CalendarView y definir comprobaciones para creación, edición, filtros y transporte de los campos del formulario.

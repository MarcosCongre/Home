# Brief: Fix Task Modal

## Original Request

Corregir el modal de tareas de la vista de calendario para que represente y guarde correctamente una tarea, use los tipos de dominio reales de tareas y miembros, y presente estados y etiquetas adecuados al crear o editar.

## Objective

Asegurar que el formulario del modal inicialice, valide y guarde los datos de tarea de forma coherente, sin artefactos visuales ajenos al modal de miembros ni encabezados duplicados.

## Proposed Solution Approach

Alinear el modal con los tipos y datos de dominio existentes, corregir el comportamiento de sus campos y controles, y garantizar que el envío refleje el estado válido del formulario. Mantener la apariencia actual del modal, su overlay y el comportamiento de propagación de eventos.

## Scope

### In Scope

- Usar `Task` y los tipos de dominio reales de `User`/`Member`, además del contrato `TaskFormData` y las props requeridas.
- Inicializar correctamente los campos de la tarea y controlar el campo de hora y el estado del botón de categoría.
- Guardar el payload del formulario con el título recortado y evitar el envío cuando el título esté vacío.
- Eliminar artefactos de avatar/color del modal de miembros y el encabezado duplicado de calendario.
- Usar etiquetas de acción adecuadas para añadir o editar una tarea.
- Preservar el estilo visual, el overlay y el comportamiento de `stopPropagation` actuales.

### Out of Scope

- Rediseñar otras pantallas o cambiar el estilo general de la aplicación.
- Modificar el dominio de tareas o miembros más allá de lo necesario para integrar correctamente el modal.
- Cambiar otros flujos del calendario que no estén relacionados con este formulario.

## Assumptions

- Los tipos de dominio reales de tareas y miembros ya existen en el proyecto y son la fuente de verdad.
- La solicitud describe el comportamiento funcional y visual esperado con suficiente detalle para elaborar el plan.

## Open Questions / Missing Information

- No se identifican preguntas bloqueantes en esta etapa.

## Next Step

`orch-dev` debe convertir este brief en un plan técnico acotado y definir las comprobaciones para verificar el comportamiento del modal.

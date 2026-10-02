# Plan: Corregir TaskModal

## Resumen

Corregir el formulario aislado `TaskModal` para que modele y emita datos de tarea usando los tipos de dominio existentes, sin alterar la apariencia establecida del bottom sheet. La revisión encontró que `TaskModal` no está importado ni renderizado desde `src`; la integración con `CalendarView` se deja como una decisión de alcance separada.

## Tareas

| ID | Tarea | Prioridad | Dependencias | Archivos afectados | Complejidad |
|---|---|---|---|---|---|
| T1 | Corregir contrato, estado y envío del formulario. Exportar `TaskFormData`; usar `Member as User` y `Task`; definir las props solicitadas; inicializar title/day/time/assignee/category/recurrence/priority desde `initial` o defaults; limpiar imports y restos de miembro; controlar los inputs, corregir selección de categoría y enviar el payload requerido. Preservar overlay, sheet, propagación de eventos y paleta visual. | Alta | Ninguna | `src/presentation/screens/TaskModal.tsx` | Completada |
| T2 | Validar tipos y compilación del frontend, revisar que el nuevo contrato no cause incompatibilidades y registrar cualquier problema preexistente detectado fuera de alcance. | Alta | T1 | `src/presentation/screens/TaskModal.tsx`, `src/presentation/screens/homeScreens.tsx` | Parcial: build aprobado; TypeScript bloqueado por errores ajenos a T1 |
| T3 | Resolver el límite de integración del modal con el calendario: confirmar si esta corrección debe incluir el renderizado/cableado de `TaskModal` desde `CalendarView`; solo si se confirma, alinear callbacks y datos de creación/edición sin cambiar la API solicitada del modal. | Media | T1 | `src/presentation/screens/homeScreens.tsx`, `src/presentation/screens/TaskModal.tsx` | Pendiente de confirmación |

## Criterios de aceptación

- `TaskFormData` está exportado con `title`, `assignee`, `day`, `time`, `category`, `recurrence` y `priority` en los tipos especificados.
- Los valores iniciales y los defaults son válidos para creación y edición, incluyendo `assignee` como `number | ''`.
- No quedan referencias a `task` indefinida ni estados/elementos de miembro ajenos al formulario de tarea.
- El nombre se presenta como `Task name`; la hora está controlada; los botones de categoría actualizan `category`.
- Guardar envía el título recortado y el resto de los campos indicados, y se deshabilita con título vacío.
- Las etiquetas reflejan `Add task` o `Edit task` y `Add task` o `Save changes` según corresponda.
- Se conservan el overlay `absolute inset-0 z-50`, el `stopPropagation` del sheet, el bottom-sheet redondeado y los colores existentes.

## Validación

1. Ejecutar `pnpm exec tsc --noEmit` para comprobar todos los archivos incluidos por `tsconfig.json` en modo estricto.
2. Ejecutar `pnpm build` para validar la compilación de producción.
3. No existe script de tests frontend en `package.json`; la comprobación funcional manual debe cubrir creación con título vacío y válido, edición con datos iniciales, selección de día/miembro/categoría/recurrencia/prioridad y valor de hora.

## Supuestos y decisiones pendientes

- El alcance solicitado es corregir el componente y su contrato sin rediseño.
- La búsqueda no encontró un consumidor de `TaskModal`; `CalendarView` mantiene `taskModal` y `onTaskModel`, pero no renderiza el componente. Confirmar si se espera incluir esa integración antes de ejecutar T3. No se presupone cambiar otros flujos del calendario.
- El proyecto usa `pnpm` y TypeScript está configurado con `strict: true`; no se encontró comando de test frontend.

## Orden

`T1 -> T2`; `T3` requiere confirmar el alcance de integración y puede ejecutarse después de T1.

## Ejecución

- T1 completada en `src/presentation/screens/TaskModal.tsx`.
- `pnpm exec tsc --noEmit`: falla con nueve errores en `src/App.tsx` y `src/presentation/screens/homeScreens.tsx`; no reporta errores en `TaskModal.tsx`. Incluye incompatibilidad del callback de completar tareas, props faltantes de `CalendarView`, parámetros implícitos `any` y discrepancias de tipo del ID de miembro.
- `pnpm build`: aprobado. Vite emitió advertencias preexistentes de configuración nativa y optimización CSS.
- T3 no ejecutada: añadir el modal a `CalendarView` excede la corrección del componente y requiere confirmación de alcance.
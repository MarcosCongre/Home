# Plan: Integrar TaskModal en CalendarView

**Fecha**: 2026-10-01 | **Brief**: [brief.md](brief.md), revisión 2

## Resumen

Conectar el `TaskModal` ya existente con el calendario para crear y editar tareas, eliminar del calendario el estado/cabecera copiados de miembros y transmitir el formulario completo mediante `useTasks` y `taskApi`. Se conserva la apariencia y los controles actuales de días, usuarios y tarjetas.

## Contexto técnico

- **Aplicación**: SPA React 19, TypeScript 5.7, Vite 8.
- **Capas afectadas**: `src/presentation/screens`, `src/application/tasks`, `src/infrastructure/http` y `src/App.tsx`.
- **Contrato existente**: `TaskModal.tsx` exporta `TaskFormData`; `taskApi.ts` ya incluye los campos `day`, `time`, `category`, `recurrence` y `priority` en las peticiones.
- **Cambio requerido**: `useTasks.createTask` es actualmente posicional; convertirlo a entrada de objeto. `useTasks.updateTask` ya acepta los metadatos, pero debe alinearse con el payload completo del modal.
- **Validación disponible**: `package.json` define `pnpm build`, no un script de pruebas frontend. Se planifican `pnpm exec tsc --noEmit` y `pnpm build`.
- **Límite backend**: el esquema, modelo y repositorio backend actuales no persisten los cinco metadatos. Eso queda fuera de este plan y no bloquea que el frontend los envíe.

## Comprobación de restricciones

- No se encontró `constitution.md` en los artefactos de la tarea; el alcance sigue el brief aprobado y limita los cambios al flujo frontend.
- No hay cambios de arquitectura ni dependencias nuevas; no se identificaron guías de migración aplicables.
- No se planifican cambios backend ni rediseño visual.

## Requisitos de la revisión

- **REQ-001**: `CalendarView` tiene el contrato solicitado y conserva únicamente `activeDay`, `filterUser` y `taskModal` como estado propio.
- **REQ-002**: el calendario muestra una sola cabecera «Week of Sep 15» / «Calendar» con el botón «+» para crear.
- **REQ-003**: selector de días, filtros de usuario, tarjetas con stripe/badges/avatar y empty state se conservan; seleccionar una tarjeta abre la edición modal.
- **REQ-004**: `TaskModal` se renderiza dentro de `CalendarView`, recibe tarea inicial o día por defecto, y guarda por la operación de creación o actualización adecuada.
- **REQ-005**: `App.tsx` conecta `createTask` y `updateTask`; las operaciones de aplicación aceptan y transmiten título, asignación, día, hora, categoría, recurrencia y prioridad.
- **REQ-006**: se mantienen los estilos existentes y la persistencia backend de metadatos permanece fuera del alcance.

## Pasos de implementación

### Paso 1: Contrato de operaciones de tareas

- **Requisitos**: REQ-005, REQ-006
- **Descripción**: cambiar `createTask` en `src/application/tasks/useTasks.ts` para recibir un objeto tipado con título, assignee, day, time, category, recurrence y priority; alinear la entrada de `updateTask` con el mismo conjunto de campos sin perder el soporte del status existente. Reutilizar el mapeo de `src/infrastructure/http/taskApi.ts`, que ya transmite los metadatos, y normalizar el assignee vacío en el límite de API si el contrato de tipos lo requiere. No importar tipos de presentación en la capa de aplicación.

### Paso 2: Integración del modal en CalendarView

- **Requisitos**: REQ-001, REQ-002, REQ-003, REQ-004, REQ-006
- **Descripción**: en `src/presentation/screens/homeScreens.tsx`, importar `TaskModal` y `TaskFormData`; actualizar la firma de `CalendarView`; quitar `selected`, `modal`, `selectedUser`, `deleteTarget` y el encabezado duplicado de Household; preservar `DAYS`, filtro de usuarios, tarjetas y estado vacío; hacer que «+» cree y que cada tarjeta abra la tarea en el modal; enrutar `onSave` a la operación correcta y cerrar el modal al completar el guardado. No redirigir la tarjeta a `onSelect`.

### Paso 3: Cableado desde la aplicación

- **Requisitos**: REQ-004, REQ-005
- **Descripción**: en `src/App.tsx`, obtener `createTask` y `updateTask` de `useTasks` y pasarlos a `CalendarView` junto con `tasks`, `users` y `openDetail` como `onSelect`.

### Paso 4: Validación de integración

- **Requisitos**: REQ-001 a REQ-006
- **Descripción**: ejecutar typecheck y build; confirmar por revisión funcional que alta/edición conserva los campos enviados, que filtros y empty state siguen funcionando y que no reaparecen controles de miembros en calendario. Registrar como limitación conocida que el backend no persiste los metadatos.

## Desglose ejecutable

### Fase 1: Contrato de operaciones

- [x] T001 [Plan:1] Cambiar `createTask` en `src/application/tasks/useTasks.ts` a un payload de objeto con title, assignee, day, time, category, recurrence y priority; alinear el payload de actualización y adaptar el tipo/mapeo de `src/infrastructure/http/taskApi.ts` solo donde sea necesario.

### Fase 2: Vista de calendario y conexión

- [x] T002 [Plan:2] Actualizar `src/presentation/screens/homeScreens.tsx`: contrato de `CalendarView`, imports del modal/tipo, estados permitidos, cabecera única, apertura por tarjeta, filtros/tarjetas/empty state existentes y guardado modal de alta/edición.
- [x] T003 [Plan:3] Actualizar `src/App.tsx` para pasar `createTask` y `updateTask` de `useTasks` a `CalendarView` junto a las props existentes.

### Fase 3: Verificación

- [x] T004 [Plan:4] Ejecutar `pnpm exec tsc --noEmit` y `pnpm build`; resolver solo regresiones de este flujo y anotar errores ajenos o limitaciones del backend sin ampliar el alcance.

## Criterios de aceptación

- La firma de `CalendarView` tiene `tasks`, `users`, `onSelect`, `onCreateTask` y `onUpdateTask`, y usa `TaskFormData` desde `TaskModal`.
- Solo quedan los tres estados de calendario indicados; la cabecera duplicada y las acciones de edición/borrado de miembros desaparecen.
- El botón «+» abre creación; cada tarjeta abre su edición; día, filtro, badges, avatar y empty state conservan su comportamiento y estilo.
- El modal recibe `initial` o `defaultDay`, los usuarios y callbacks de cierre/guardado; el guardado llama la operación adecuada con todos los campos y luego cierra el modal.
- `App.tsx` pasa las funciones reales de `useTasks`; `createTask` recibe objeto en lugar de argumentos posicionales; `taskApi` recibe los metadatos.
- Typecheck y build terminan correctamente, o los fallos no relacionados quedan identificados sin mezclarse con esta tarea.
- No se afirma que los metadatos se persistan en backend.

## Estrategia de validación

- `pnpm exec tsc --noEmit`: validar firmas y compatibilidad entre presentación, aplicación y API.
- `pnpm build`: validar bundle de producción.
- Revisión funcional del flujo: crear con el botón «+»; abrir una tarjeta y guardar cambios; cambiar día, usuario, categoría, recurrencia y prioridad; confirmar filtrado y el mensaje `No tasks for {activeDay}`.
- No hay script de tests frontend en `package.json`; no se introduce una nueva dependencia de pruebas para este cambio acotado.

## Mapeo de requisitos

| REQ ID | Descripción | Pasos | Evidencia de implementación |
|---|---|---|---|
| REQ-001 | Contrato y estado limpio de CalendarView | 2 | `src/presentation/screens/homeScreens.tsx` |
| REQ-002 | Cabecera única y acción de creación | 2 | `src/presentation/screens/homeScreens.tsx` |
| REQ-003 | Navegación por día, filtro y edición desde tarjeta | 2, 4 | `src/presentation/screens/homeScreens.tsx` |
| REQ-004 | Render y guardado de TaskModal | 2, 3 | `src/presentation/screens/homeScreens.tsx`, `src/App.tsx` |
| REQ-005 | Payload completo de create/update conectado desde App | 1, 3 | `src/application/tasks/useTasks.ts`, `src/infrastructure/http/taskApi.ts`, `src/App.tsx` |
| REQ-006 | Preservar estilos y mantener backend fuera de alcance | 1, 2, 4 | Frontend sin cambios backend; verificación de regresión |

## Orden y dependencias

`T001 -> T002 -> T003 -> T004`. El payload de aplicación se define antes de cablear el modal; typecheck/build se ejecutan después de integrar el flujo completo.

## Limitaciones conocidas

El frontend puede enviar `day`, `time`, `category`, `recurrence` y `priority`, pero el backend actual no los persiste. Su implementación requiere una fase backend independiente.

## Ejecución

- T001-T003 implementadas: `useTasks` recibe payloads de objeto, `taskApi` admite assignee vacío, `CalendarView` abre el modal para alta/edición y `App.tsx` conecta ambos callbacks.
- Ajuste tras validación funcional: combinar la respuesta del API con el payload local al crear/actualizar conserva `day`, `time`, `category`, `recurrence` y `priority` en el estado del cliente; después del alta, CalendarView enfoca el día y asignación elegidos para que el filtro anterior no oculte la tarea.
- `pnpm build`: aprobado. Vite reportó advertencias de configuración nativa y optimización CSS.
- `pnpm exec tsc --noEmit`: sigue fallando por cuatro errores ajenos a esta integración: el tipo de `userId` al completar en `App.tsx`; dos tipos de `n.user` en notificaciones; y el ID temporal string usado para crear miembros en `homeScreens.tsx`. No hay errores reportados en el contrato de CalendarView, TaskModal, useTasks ni taskApi.
- `git diff --check`: aprobado.
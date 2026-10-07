# Plan: Hook de aplicación useAlerts

**Fecha**: 2026-10-04 | **Brief**: [brief-5.md](brief-5.md), revisión 1

## Resumen

Implementar `src/application/alerts/useAlerts.ts` siguiendo el patrón de `useTasks` y `useMembers`: un estado centralizado con `useReducer`, operaciones de carga, actualización optimista y recarga de reconciliación en caso de error. El hook debe alimentar a la UI con `alerts`, `isLoading`, `error`, `dismissAlert`, `dismissAll`, `loadAlerts` y `unreadCount`, sin introducir cambios visuales ni de backend.

## Contexto técnico

- **Aplicación**: SPA React 19 + TypeScript 5.7 + Vite 8.
- **Capas afectadas**: `src/application/alerts`, `src/infrastructure/http`, `src/infrastructure/config`, `src/infrastructure/demo`.
- **Patrón existente**: `useTasks` y `useMembers` usan `useReducer`, `load-start`, `load-success` y `load-failure`, y realizan la carga al montar con `useEffect`.
- **Cambio requerido**: crear un hook con manejo de estado local, carga desde la API y polling periódico para no-demo. Mantener el diseño compatible con `Alert` y `alertApi` definidos en el brief-4.
- **Restricción de alineación**: los fixtures de `NOTIFS` actuales no están tipados como `Alert` completo; la solución debe mantener la compatibilidad con la UI sin convertir el hook en un adaptador demasiado pesado.
- **Validación disponible**: `pnpm exec tsc --noEmit` y `pnpm build` sirven como comprobaciones de compilación y bundle del frontend.

## Comprobación de restricciones

- El alcance sigue el brief y no incluye pantallas ni navegación; se trabaja únicamente en la capa de aplicación.
- La lógica de API se reutiliza desde `alertApi.ts`; no se introduce una segunda fuente de verdad ni un cliente paralelo.
- La actualización optimista se limita a estado local, con recuperación mediante `loadAlerts()` si falla la petición real.

## Requisitos de la revisión

- **REQ-001**: `useAlerts` usa `useReducer` con estado `alerts`, `isLoading`, `error` y se inicializa desde `NOTIFS` si `environment.demoMode` está activo.
- **REQ-002**: el hook expone `alerts`, `isLoading`, `error`, `dismissAlert`, `dismissAll`, `loadAlerts` y `unreadCount`.
- **REQ-003**: `dismissAlert(id)` actualiza el estado optimistamente, elimina la alerta localmente antes de llamar a la API y, si falla, recarga desde servidor.
- **REQ-004**: `dismissAll()` hace lo mismo con todas las alertas del estado actual antes de la llamada real de API; si falla, se recarga.
- **REQ-005**: `loadAlerts()` dispara `load-start`, llama a `listAlerts()`, y en éxito actualiza `alerts` y `error` con `load-success`. En error usa `load-failure`.
- **REQ-006**: en un `useEffect`, `loadAlerts()` se invoca al montar y, cuando no está en demo, se configura un intervalo cada 15 segundos que se limpia al desmontar.
- **REQ-007**: `unreadCount` es derivado del estado actual y cuenta los elementos con `status === 'unread'`.
- **REQ-008**: no se introducen componentes visuales ni se extienden endpoints/backend.

## Pasos de implementación

### Paso 1: Definir el estado del hook

- **Requisitos**: REQ-001, REQ-007
- **Descripción**: crear el tipo `AlertState` y el reducer `alertReducer` con acciones `load-start`, `load-success`, `load-failure`, `set-alerts`, `dismiss-alert` y `dismiss-all`. El estado inicial debe usar `NOTIFS` cuando `environment.demoMode` sea verdadero; en el resto, inicializar con un arreglo vacío pero sin bloquear la primera carga.

### Paso 2: Cargar alertas al montar

- **Requisitos**: REQ-005, REQ-006
- **Descripción**: implementar `loadAlerts` con `useCallback`, invocando `dispatch({ type: 'load-start' })`, o bien la llamada real `listAlerts()`. En caso exitoso, aplicar `load-success` con `alerts: payload`; en error, `load-failure` con el mensaje de error estándar del proyecto. Añadir el `useEffect` que llama a `loadAlerts()` al montar y, si `environment.demoMode` es falso, activa un `setInterval` de 15 segundos para repetir la carga y limpia el intervalo al desmontar.

### Paso 3: Gestión optimista de descarte

- **Requisitos**: REQ-003, REQ-004
- **Descripción**: definir el flujo de `dismissAlert(id)` y `dismissAll()`. El primer caso aplicará una actualización local inmediata, filtrando la alerta por id del estado. El segundo será una operación de conjunto sobre todas las alertas. En ambas ramas se llama a la API real; si la operación falla, se dispara `loadAlerts()` para recalcular desde servidor y dejar el estado consistente.

### Paso 4: Exposición del valor derivado

- **Requisitos**: REQ-002, REQ-007
- **Descripción**: devolver desde el hook `alerts`, `isLoading`, `error`, `dismissAlert`, `dismissAll`, `loadAlerts` y `unreadCount`. El campo derivado se calcula con `state.alerts.filter(alert => alert.status === 'unread').length` para evitar un contador separado y mantener la fuente de verdad única.

### Paso 5: Validación de contrato frontend

- **Requisitos**: REQ-001 a REQ-008
- **Descripción**: ejecutar `pnpm exec tsc --noEmit` y comprobar que el hook se integra bien con `Alert` y `alertApi`. Resolver solo errores directamente atribuibles al hook o a la adaptación de fixtures demo; no introducir cambios de UI ni de backend.

## Desglose ejecutable

### Fase 1: Estado y carga

- [ ] T001 [Plan:1] Definir `AlertState`, reducer y estado inicial con `NOTIFS`/demoMode y `load-start`/`load-success`/`load-failure`.
- [ ] T002 [Plan:2] Implementar `loadAlerts` y el `useEffect` de carga inicial y polling de 15s sin demo.

### Fase 2: Operaciones optimistas

- [ ] T003 [Plan:3] Implementar `dismissAlert(id)` y `dismissAll()` con actualización local y recarga en fallo.
- [ ] T004 [Plan:4] Calcular `unreadCount` derivado desde `state.alerts` y devolverlo junto al resto del API del hook.

### Fase 3: Verificación

- [ ] T005 [Plan:5] Ejecutar `pnpm exec tsc --noEmit` y corregir solo los errores del alcance del hook.

## Criterios de aceptación

- Existe `src/application/alerts/useAlerts.ts` con `useReducer` y un estado inicial consistente con `environment.demoMode`.
- `loadAlerts()` carga desde `alertApi.listAlerts()` y expone `isLoading` y `error` siguiendo el patrón de `useTasks` y `useMembers`.
- `dismissAlert` y `dismissAll` actualizan optimistamente el estado, deshacen en caso de error y posteriormente cargan de nuevo.
- El polling se activa solo fuera de modo demo y se limpia al desmontar.
- `unreadCount` refleja el número de alertas con `status === 'unread'` sin estado paralelo.
- El frontend compila sin romper el contrato de `Alert` ni de `alertApi`.

## Estrategia de validación

- `pnpm exec tsc --noEmit`: comprobar las firmas del hook y su integración con el contrato `Alert` y `alertApi`.
- Revisión de flujo: carga inicial, recarga periódica fuera de demo, optimista y reconciliación tras error.
- Confirmar que no se añaden renderizados de UI ni lógica de navegación.

## Mapeo de requisitos

| REQ ID | Descripción | Pasos | Evidencia esperada |
|---|---|---|---|
| REQ-001 | Estado y estado inicial | 1 | `useAlerts` reducer/initialState |
| REQ-002 | API del hook | 4 | `return { alerts, ... }` |
| REQ-003 | `dismissAlert` optimista + fallback | 3 | action/reducer y recarga |
| REQ-004 | `dismissAll` optimista + fallback | 3 | action/reducer y recarga |
| REQ-005 | `loadAlerts` y carga | 2 | `listAlerts()` + `load-success`/`load-failure` |
| REQ-006 | polling 15s + limpieza | 2 | `useEffect` con `setInterval` |
| REQ-007 | `unreadCount` derivado | 4 | `filter(alert => alert.status === 'unread')` |
| REQ-008 | Alcance estrictamente frontend | 5 | no cambios backend/UI |

## Orden y dependencias

`T001 -> T002 -> T003 -> T004 -> T005`.

El estado del reducer debe existir antes de la carga y antes de la lógica optimista. Las operaciones de descarte dependen de `alertApi` ya definido en el brief-4. La validación final ocurre una vez que el flujo completo queda integrado.

## Limitaciones conocidas

- El fixture demo actual `NOTIFS` no cumple plenamente el contrato de `Alert` (`id` string y sin `status`), así que la implementación del hook debe asumir una adaptación mínima previa o un mapeo temporal antes del uso directo en estado.
- Si `listAlerts()` falla durante la recarga tras error de descarte, el hook debe dejar `error` como indicador; no se añade una lógica de reintento automática más allá del brief.
- No se incluye ningún cambio de UI ni persistencia del backend; este plan asume que el hook será consumido por la capa visual después de su creación.

## Ejecución prevista

- Crear `useAlerts` con reducer y carga inicial.
- Añadir desactivación optimista con recarga mínima en error.
- Habilitar polling solo en modo no-demo cada 15 segundos.
- Ejecutar `pnpm exec tsc --noEmit` para validar el alcance del hook y ajustar la compatibilidad de `NOTIFS` si se requiere.

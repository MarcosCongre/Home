# Plan: Integración de alertas reales en Notifications

**Fecha**: 2026-10-04 | **Brief**: [brief-6.md](brief-6.md)

## Resumen

Conectar la pantalla de alertas y la navegación con el estado real de `useAlerts`, sustituyendo el uso directo de `NOTIFS` por el flujo centralizado de aplicación. La integración debe conservar el diseño actual de `Notifications`, mover la lógica de descarte a props de aplicación y usar el contador derivado `unreadCount` para el badge del tab de Alerts.

## Contexto técnico

- **Aplicación**: SPA React 19 + TypeScript 5.7 + Vite 8.
- **Capas afectadas**: `src/App.tsx`, `src/presentation/screens/homeScreens.tsx`, `src/application/alerts`, `src/infrastructure/http`, `src/infrastructure/demo`.
- **Patrón existente**: `App.tsx` ya conecta hooks de `useMembers` y `useTasks` con las pantallas; la navegación renderiza un badge de Alerts en función de un contador local.
- **Cambio requerido**: inyectar `useAlerts()` en `App.tsx`, reemplazar la lógica local de `dismissed` y `NOTIFS` por `alerts`, `dismissAlert`, `dismissAll` y `unreadCount`, y ajustar la firma de `Notifications` para recibir únicamente los datos y callbacks necesarios.
- **Restricción de alineación**: la UI actual asocia `user` con `users` para mostrar avatar. El backend/modelo nuevo define `user` como `string | null`, así que hay que decidir si el avatar sigue siendo una decisión de presentación o si se elimina esa dependencia de manera segura.
- **Validación disponible**: `pnpm exec tsc --noEmit` y `pnpm build` cubren la integración del frontend.

## Comprobación de restricciones

- El alcance no incluye cambios visuales de la pantalla, ni endpoints ni backend.
- Los cambios se limitan a la composición de props y a la fuente de datos de alertas desde `App.tsx` hacia `Notifications`.
- La lógica de cálculo del badge se mueve a `unreadCount` sin duplicar contador local.

## Requisitos de la revisión

- **REQ-001**: `Notifications` deja de importar y usar `NOTIFS` como fuente de verdad.
- **REQ-002**: `Notifications` recibe `alerts`, `onDismiss` y `onDismissAll` como props.
- **REQ-003**: `Notifications` conserva la agrupación visual actual entre `urgent` y `rest` y renderiza icono, body, hora, estado y cierre individual.
- **REQ-004**: `Clear all` invoca `onDismissAll`, y cada cierre individual invoca `onDismiss(alert.id)`.
- **REQ-005**: `App.tsx` usa `const { alerts, dismissAlert, dismissAll, unreadCount } = useAlerts()` y pasa esos valores al tab de alerts.
- **REQ-006**: el badge de navegación usa `unreadCount` en lugar de `NOTIFS.filter(n => n.urgent).length`.
- **REQ-007**: se mantiene el diseño actual de `Notifications` y la asociación de avatar con `users` se conserva solo si el contrato de `Alert.user` lo permite.
- **REQ-008**: no se añade lógica de backend ni nuevas pantallas.

## Pasos de implementación

### Paso 1: Integrar el hook en App

- **Requisitos**: REQ-005, REQ-006, REQ-008
- **Descripción**: en `src/App.tsx`, importar `useAlerts`, llamar al hook y extraer `alerts`, `dismissAlert`, `dismissAll`, `unreadCount`. Sustituir el cálculo local `NOTIFS.filter(n => n.urgent).length` por `unreadCount` para el badge del tab `Alerts`. Mantener `Retry` de error y no mezclar otras capas de errores que no sean las de `useMembers`/`useTasks`.

### Paso 2: Reescribir la firma de Notifications

- **Requisitos**: REQ-001, REQ-002, REQ-003, REQ-004
- **Descripción**: cambiar `Notifications({ users }: { users: User[] })` por una firma que reciba `alerts`, `onDismiss`, `onDismissAll`, y mantener `users` solo si se necesita para avatar. Quitar el `useState` local `dismissed` y calcular `visible` directamente desde `alerts`, sin depender del fixture `NOTIFS`. Mantener los elementos visuales actuales: `urgent` vs `Earlier`, líneas de texto, hora y botón de cierre.

### Paso 3: Delegar la lógica de descarte a props

- **Requisitos**: REQ-004, REQ-008
- **Descripción**: reemplazar `setDismissed(...)` por `onDismissAll()` y `onDismiss(n.id)`. Asegurar que el clear all se dispara desde la cabecera y que cada alerta individual usa el callback del hook. No introducir doble lógica ni estados locales de UI para el descarte.

### Paso 4: Alinear avatar y usuario con la nueva API

- **Requisitos**: REQ-007
- **Descripción**: revisar cómo se representa `user` en `Alert`. Si el backend devuelve nombre completo, mantener la visualización con un avatar derivado de `members` o, si no existe, simplificar la visualización local para no romper la firma. La clave es no cambiar la estructura del `Alert` ni la lógica del hook; solo ajustar la adaptación de presentación en la pantalla.

### Paso 5: Validación de integración

- **Requisitos**: REQ-001 a REQ-008
- **Descripción**: ejecutar `pnpm exec tsc --noEmit` para comprobar que la firma de `Notifications` y la generación del badge se integran con `useAlerts` y sin resolver problemas ajenos a esta integración. Si aparece un error de `user`/avatar, documentarlo como una adaptación de presentación con alcance acotado.

## Desglose ejecutable

### Fase 1: Conexión desde App

- [ ] T001 [Plan:1] Importar y usar `useAlerts()` en `src/App.tsx`; reemplazar el contador local por `unreadCount`.
- [ ] T002 [Plan:2] Pasar `alerts`, `dismissAlert` y `dismissAll` a `Notifications` cuando la pestaña `alerts` está activa.

### Fase 2: Pantalla de notificaciones

- [ ] T003 [Plan:3] Reescribir `Notifications` para usar `alerts` y los callbacks, sin `NOTIFS` ni estado local `dismissed`.
- [ ] T004 [Plan:4] Mantener la presentación visual actual y decidir la compatibilidad del avatar con `user`/`users`.

### Fase 3: Verificación

- [ ] T005 [Plan:5] Ejecutar `pnpm exec tsc --noEmit` para confirmar la integración de la capa de aplicación y pantalla.

## Criterios de aceptación

- `src/App.tsx` usa `useAlerts()` y el badge del tab `Alerts` muestra `unreadCount`.
- El componente `Notifications` ya no usa `NOTIFS` ni estado local `dismissed`.
- El render actual de alertas se mantiene intacto y conserva la separación por prioridad (`Needs attention`/`Earlier`).
- `Clear all` llama a `onDismissAll` y cada cierre individual llama a `onDismiss(alert.id)`.
- La lista renderiza el contenido real recibido por props (`icon`, `title`, `body`, `time`, `urgent`, `user`, `id`).
- El proyecto compila y no aparecen regresiones de tipo en la integración de la pantalla.

## Estrategia de validación

- `pnpm exec tsc --noEmit`: comprobar que la integración de `Notifications` con `useAlerts` y `App.tsx` no rompe la firma del frontend.
- Revisión visual del componente: no cambios de estilo ni de organización visual.
- Confirmación funcional: con data real, `Clear all` elimina todas; cada cierre quita una; badge refleja `unreadCount`.

## Mapeo de requisitos

| REQ ID | Descripción | Pasos | Evidencia esperada |
|---|---|---|---|
| REQ-001 | Eliminar `NOTIFS` de `Notifications` | 2, 3 | `homeScreens.tsx` sin import de demo |
| REQ-002 | props `alerts`, `onDismiss`, `onDismissAll` | 2, 3 | firma del componente |
| REQ-003 | render visual existente | 3 | diseño “urgent/rest” |
| REQ-004 | botones delegados al hook | 3 | callbacks invocados |
| REQ-005 | `App.tsx` conecta `useAlerts` | 1 | `useAlerts` + props |
| REQ-006 | badge usa `unreadCount` | 1 | badge del tab |
| REQ-007 | avatar/user compatibility | 4 | ajuste mínimo de presentación |
| REQ-008 | alcance frontend | 1-5 | no backend/UI extra |

## Orden y dependencias

`T001 -> T002 -> T003 -> T004 -> T005`.

La conexión del hook en `App.tsx` debe preceder al cambio de firma de `Notifications`, porque la nueva pantalla depende del estado real del hook. La validación final ocurre una vez que la pantalla y el badge están conectados.

## Limitaciones conocidas

- La compatibilidad de `user` con el avatar visual sigue siendo un punto de decisión: el contrato del alert real puede devolver un nombre y no un id numérico. La solución debe mantener la estructura de la pantalla, pero no se debe ampliar el alcance a un cambio de modelo del backend.
- Si `useAlerts` aún no está completamente consolidado, el ajuste del tab `Alerts` debe depender solo de su público actual: `alerts`, `unreadCount`, `dismissAlert` y `dismissAll`.
- No se introducen cambios de estilo visual ni nuevas pantallas; solo se mueve el origen de datos y los callbacks.

## Ejecución prevista

- Reemplazar el estado local `dismissed` del componente por props del hook.
- Usar `unreadCount` para el badge del tab de Alerts.
- Ejecutar `pnpm exec tsc --noEmit` para confirmar la integración del flujo en `App.tsx` y `Notifications`.

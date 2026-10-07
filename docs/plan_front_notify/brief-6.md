# Brief: Integración de alertas reales en Notifications

## Solicitud original
Actualizar `Notifications` para renderizar las alertas recibidas por props y delegar los descartes al hook de aplicación. Conectar `useAlerts` desde `App.tsx` y usar su contador de no leídas para el badge de navegación.

## Objetivo
Sustituir el estado local basado en datos demo por el estado centralizado de Alerts, manteniendo el diseño actual de la pantalla y reflejando correctamente las alertas no leídas.

## Enfoque propuesto
Modificar `Notifications` en `src/presentation/screens/homeScreens.tsx` para recibir `alerts`, `onDismiss` y `onDismissAll`, eliminar su dependencia directa de `NOTIFS` y delegar las acciones. En `src/App.tsx`, inicializar `useAlerts`, conectar el estado y los handlers con la pantalla y mostrar `unreadCount` en el badge.

## Alcance
### Incluido
- Eliminar el import de `NOTIFS` de `homeScreens.tsx` y el estado local de alertas descartadas dentro de `Notifications`.
- Recibir mediante props `alerts`, `onDismiss` y `onDismissAll`.
- Renderizar las alertas recibidas conservando el diseño actual: icono, título, body, hora y tratamiento visual de alertas `urgent`.
- Hacer que el control de descarte individual invoque `onDismiss(alert.id)` y que `Clear all` invoque `onDismissAll`.
- Importar e invocar `useAlerts()` desde `src/App.tsx`, pasar `alerts`, `dismissAlert` y `dismissAll` a `Notifications` y retirar el import de `NOTIFS` de ese archivo.
- Sustituir el cálculo local de alertas urgentes por `unreadCount` del hook para el badge de navegación.

### Excluido
- Cambios en el diseño visual de Notifications, endpoints HTTP, persistencia o reglas de generación de alertas.
- Cambios en las acciones de las demás pantallas.

## Supuestos
- `useAlerts` expone las alertas y operaciones descritas en `brief-5.md`.
- El modelo `Alert` expone los campos visuales `icon`, `title`, `body`, `time`, `urgent`, `user` y `id` descritos en `brief-4.md`.
- Las alertas siguen agrupándose visualmente entre urgentes y no urgentes, como en el diseño actual.

## Preguntas abiertas
- La pantalla actual convierte `user` en un avatar buscando un identificador numérico en `users`, pero el tipo `Alert` del brief-4 define `user` como `string | null` y la API devuelve el nombre del miembro. Debe decidirse cómo conservar esa parte visual sin alterar el contrato solicitado para las props.
- La solicitud enumera las props `alerts`, `onDismiss` y `onDismissAll`; si se conserva la visualización de avatar actual, habrá que confirmar si `users` continúa siendo una prop adicional o si se elimina esa asociación.

## Siguiente paso
Usar este brief junto con `brief-4.md` y `brief-5.md` como entrada para `orch-dev`, que elaborará el plan técnico de integración de la pantalla y el badge.
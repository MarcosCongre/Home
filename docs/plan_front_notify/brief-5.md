# Brief: Hook de aplicación useAlerts

## Solicitud original
Crear `src/application/alerts/useAlerts.ts` siguiendo la arquitectura de `src/application/tasks/useTasks.ts`, con reducer para cargar alertas, operaciones optimistas de descarte y actualización periódica desde la API.

## Objetivo
Centralizar el estado y las operaciones de alertas para que la UI pueda listar, descartar alertas individuales o todas, mostrar carga y errores, y consultar el total no leído.

## Enfoque propuesto
Implementar el hook con `useReducer` y acciones `load-start`, `load-success`, `load-failure` y `set-alerts`, reutilizando el tipo `Alert`, `alertApi` y `NOTIFS` definidos o referidos en el brief-4. Cargar los datos al montar; en modo no-demo, mantenerlos actualizados mediante un intervalo que se limpia al desmontar.

## Alcance
### Incluido
- Inicializar el estado de alertas desde `NOTIFS` cuando `environment.demoMode` está activo.
- Exponer `alerts`, `isLoading`, `error`, `dismissAlert(id)`, `dismissAll()` y `loadAlerts()`.
- Implementar `dismissAlert` y `dismissAll` con actualización optimista del estado, eliminando de la lista la alerta correspondiente o todas las alertas antes de llamar a la API.
- Ante un error al descartar, volver a cargar las alertas mediante `loadAlerts()` para reconciliar el estado con el servidor.
- En un `useEffect`, llamar `loadAlerts()` al montar y configurar una repetición cada 15 segundos; no configurar polling cuando `environment.demoMode` está activo y limpiar el intervalo al desmontar.
- Exponer `unreadCount`, calculado como el número de alertas cuyo `status` es `unread`.

### Excluido
- Componentes visuales, cambios de presentación y navegación de pantallas.
- Cambios en los endpoints o en la persistencia backend.

## Supuestos
- El hook consume `listAlerts`, `dismissAlert` y `dismissAllAlerts` desde `src/infrastructure/http/alertApi.ts`.
- En modo demo, `NOTIFS` proporciona el estado inicial local; la carga inicial del efecto se conserva según la solicitud, mientras que el intervalo periódico se omite.
- `unreadCount` es un valor derivado del estado actual, no un contador mantenido por separado.

## Preguntas abiertas
- Los elementos actuales de `NOTIFS` usan ids de texto y no tienen campo `status`, pero el tipo `Alert` del brief-4 requiere `id: number` y `status`. Debe decidirse o completarse la adaptación de datos demo antes de que `NOTIFS` pueda usarse directamente como estado inicial tipado.
- La solicitud especifica recargar ante error de descarte, pero no define el comportamiento si esa recarga también falla; se seguirá el manejo de error previsto para `loadAlerts`.

## Siguiente paso
Usar este brief junto con `brief-4.md` como entrada para `orch-dev`, que preparará el plan técnico para el contrato frontend y su hook de aplicación.
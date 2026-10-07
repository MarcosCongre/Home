# Brief: Casos de uso de Alerts y alertas automáticas de tareas

## Solicitud original
Crear `home-backend/src/Application/Alerts/` siguiendo la organización de Application/Tasks, con carpetas por caso de uso y pares Query o Command + Handler. Integrar además la creación automática de alertas al completar una tarea y al crear una tarea asignada.

## Objetivo
Permitir listar y descartar alertas de un hogar, crear alertas desde otros handlers y notificar eventos relevantes de tareas mediante el caso de uso `CreateAlert`.

## Enfoque propuesto
Añadir los casos de uso `ListAlerts`, `DismissAlert`, `DismissAllAlerts` y `CreateAlert`, cada uno con su Query o Command y Handler correspondiente. Reutilizar `CreateAlertHandler` desde los handlers de tareas para generar alertas, manteniendo compatibilidad con el modelo y repositorio definidos en el brief de dominio Alerts.

## Alcance
### Incluido
- `ListAlerts`: recibe `householdId` y devuelve sus alertas ordenadas por `createdAt` descendente.
- `DismissAlert`: recibe el identificador de una alerta y la marca como `dismissed`.
- `DismissAllAlerts`: recibe `householdId` y descarta todas las alertas de ese hogar.
- `CreateAlert`: recibe los datos necesarios para crear y guardar una alerta; estará disponible para otros handlers y pruebas.
- `CompleteTaskHandler`: inyecta `CreateAlertHandler` y genera al completar una tarea una alerta con título `Tarea completada`, body que incluya el título de la tarea y el miembro relacionado, y `urgent=false`.
- `CreateTaskHandler`: inyecta `CreateAlertHandler` y genera una alerta cuando se crea una tarea asignada.
- Mantener la convención de carpetas por caso de uso y los pares Query/Command + Handler del módulo Application/Tasks.

### Excluido
- Adaptadores de persistencia, endpoints HTTP, cambios de interfaz frontend y otros tipos de notificación fuera de las alertas solicitadas.

## Supuestos
- Los casos de uso usarán el dominio `App\Domain\Alerts` definido en el brief de dominio anterior.
- En `CreateTask`, la alerta automática solo se genera cuando la tarea tiene un miembro asignado.
- La alerta automática de creación usará el household y el miembro asignado a la tarea.

## Preguntas abiertas
- Para la alerta de tarea completada, ¿el miembro que debe aparecer en el body es el miembro asignado a la tarea o el miembro que ejecutó la acción de completarla? El formato exacto del body tampoco está especificado.
- No se especifican el título, body, icono ni miembro destinatario de la alerta automática de tarea asignada; deben definirse durante la planificación o implementación según las convenciones existentes.

## Siguiente paso
Usar este brief como entrada para `orch-dev`, que elaborará el plan técnico y resolverá las decisiones abiertas antes de implementar.
# Plan: Casos de uso de Alerts y alertas automáticas de tareas

**Fecha**: 2026-10-02 | **Brief**: [brief-2.md](brief-2.md)

## Resumen

Crear el módulo `App\Application\Alerts` siguiendo el patrón de `Application/Tasks`, con los casos de uso `ListAlerts`, `DismissAlert`, `DismissAllAlerts` y `CreateAlert`. Además, integrar la creación automática de alertas cuando se completa una tarea o cuando se crea una tarea asignada, respetando el dominio `App\Domain\Alerts` ya definido y manteniendo un estilo de mensajes alineado con la aplicación actual.

## Contexto técnico

- **Referencia de aplicación**: `home-backend/src/Application/Tasks/`
- **Patrón a seguir**: carpetas por caso de uso, con `Query` o `Command` + `Handler` por cada flujo.
- **Dominio base**: `App\Domain\Alerts` con la entidad `Alert` y `AlertRepositoryInterface`.
- **Flujo de integración**: `CreateTaskHandler` y `CompleteTaskHandler` deben invocar `CreateAlertHandler` cuando corresponda.
- **Decisión resuelta**: en el body de la alerta de tareas asignadas, debe aparecer el miembro al que se le asignó la tarea; para los mensajes del caso de uso, se reutilizará un estilo textual cercano al de la app actual: título breve, body descriptivo y tono natural.

## Requisitos

- **REQ-001**: crear la estructura `home-backend/src/Application/Alerts/` con subcarpetas por caso de uso.
- **REQ-002**: cada caso de uso tendrá su `Query` o `Command` y su `Handler` siguiendo el patrón de `Application/Tasks`.
- **REQ-003**: `ListAlerts` debe recibir `householdId` y devolver las alertas ordenadas por `createdAt` descendente.
- **REQ-004**: `DismissAlert` debe marcar una alerta como `dismissed` por su id.
- **REQ-005**: `DismissAllAlerts` debe descartarlas todas para un hogar concreto.
- **REQ-006**: `CreateAlert` debe construir y guardar un `Alert` usando el repositorio de dominio y quedar disponible para otros handlers.
- **REQ-007**: `CompleteTaskHandler` deberá generar una alerta cuando una tarea se complete con un título del tipo `Tarea completada`.
- **REQ-008**: el body de la alerta de tarea completada debe incluir el título de la tarea y el miembro al que estaba asignada.
- **REQ-009**: `CreateTaskHandler` deberá generar una alerta automática cuando una tarea se cree con `assignedMemberId` distinto de `null`.
- **REQ-010**: para la alerta de tarea asignada, el body debe incluir el nombre o identificación del miembro al que se asignó la tarea.
- **REQ-011**: el formato de texto del body debe seguir el mismo tono y estilo de mensajes de la app actual: directo, legible y consistente con la UI existente.
- **REQ-012**: no se incluyen endpoints HTTP ni adaptadores de persistencia en esta fase; el trabajo se limita a Application y dominio.

## Tareas

| ID | Tarea | Prioridad | Dependencias | Archivos afectados | Complejidad |
|---|---|---|---|---|---|
| T1 | Crear la estructura del módulo `Application/Alerts` y la convención de carpetas por caso de uso. | Alta | Ninguna | `home-backend/src/Application/Alerts/` | Baja |
| T2 | Definir `ListAlertsQuery` y `ListAlertsHandler` con el listado ordenado por fecha descendente. | Alta | T1 | `home-backend/src/Application/Alerts/ListAlerts/*` | Media |
| T3 | Definir `DismissAlertCommand` y `DismissAlertHandler` para marcar una alerta como descartada. | Alta | T1 | `home-backend/src/Application/Alerts/DismissAlert/*` | Media |
| T4 | Definir `DismissAllAlertsCommand` y `DismissAllAlertsHandler` para limpiar alertas de un hogar. | Alta | T1 | `home-backend/src/Application/Alerts/DismissAllAlerts/*` | Media |
| T5 | Definir `CreateAlertCommand` y `CreateAlertHandler` para crear y guardar alertas reutilizables. | Alta | T1 | `home-backend/src/Application/Alerts/CreateAlert/*` | Media |
| T6 | Integrar `CreateAlertHandler` dentro de `CreateTaskHandler` cuando la tarea tenga miembro asignado. | Alta | T1, T5 | `home-backend/src/Application/Tasks/CreateTask/CreateTaskHandler.php` | Media |
| T7 | Integrar `CreateAlertHandler` dentro de `CompleteTaskHandler` para crear alertas de tarea completada. | Alta | T1, T5 | `home-backend/src/Application/Tasks/CompleteTask/CompleteTaskHandler.php` | Media |
| T8 | Ajustar los mensajes de alerta para incluir el miembro asignado en el body y mantener tonos similares a la app actual. | Alta | T5-T7 | `home-backend/src/Application/Alerts/*`, `home-backend/src/Application/Tasks/*` | Media |
| T9 | Validar que el flujo no rompe la estructura actual de handlers, commands y repositorios. | Media | T2-T8 | `home-backend/src/Application/**` | Baja |
| T10 | Ejecutar las pruebas relevantes del backend para confirmar la integración de alertas. | Media | T2-T9 | `home-backend/tests/**` | Media |

## Pasos de implementación

### Paso 1: Estructura del módulo Application/Alerts

- Crear `home-backend/src/Application/Alerts/`.
- Crear subdirectorios por caso de uso: `ListAlerts`, `DismissAlert`, `DismissAllAlerts`, `CreateAlert`.
- Respetar el estilo de `Application/Tasks` con `Command` o `Query` + `Handler`.

### Paso 2: Listar alertas

- `ListAlertsQuery` recibirá `householdId`.
- `ListAlertsHandler` consultará el repositorio de alertas.
- El resultado debe ordenarse por `createdAt` descendente para que las más recientes aparezcan primero.

### Paso 3: Descartar alertas

- `DismissAlertCommand` recibirá el id de la alerta.
- `DismissAlertHandler` usará `dismiss(int $id): void` del repositorio.
- `DismissAllAlertsCommand` recibirá `householdId`.
- `DismissAllAlertsHandler` usará `dismissAll(string $householdId): void`.

### Paso 4: Crear alertas reutilizables

- `CreateAlertCommand` recogerá los datos mínimos necesarios: `householdId`, `memberId`, `title`, `body`, `icon`, `urgent` y `createdAt` si aplica.
- `CreateAlertHandler` deberá:
  - construir una nueva `Alert`
  - invocar `save()` del repositorio
  - devolver la alerta creada
- Este handler será el punto común para todas las alertas automáticas.

### Paso 5: Integración con tareas

#### Al crear una tarea asignada

- `CreateTaskHandler` comprobará si `assignedMemberId !== null`.
- En ese caso invocará `CreateAlertHandler` con:
  - `householdId`: el hogar de la tarea
  - `memberId`: el miembro asignado
  - `title`: texto breve como `Nueva tarea asignada`
  - `body`: texto con el miembro destinatario y el título de la tarea, por ejemplo: `Se te asignó la tarea "<título>".`
  - `icon`: símbolo o estilo visual consistente con la app actual, por ejemplo `check-circle` o equivalente
  - `urgent`: `false`
- La clave del requisito es que el body incluya explícitamente al miembro al que se le asignó la tarea.

#### Al completar una tarea

- `CompleteTaskHandler` buscará la tarea y la marcará como completada.
- Después de guardar la tarea, invocará `CreateAlertHandler` con:
  - `title`: `Tarea completada`
  - `body`: incluir el título de la tarea y el miembro asignado a la misma
  - `memberId`: el miembro asignado a la tarea, si existe
  - `icon`: símbolo neutro o de confirmación similar al estilo actual
  - `urgent`: `false`
- El cuerpo debe seguir el mismo tono de la app actual: claro, descriptivo y con el nombre del miembro asignado en la frase.

### Paso 6: Estilo del body y mensajes

- Se tomará como referencia la estética textual habitual de la aplicación actual: mensajes cortos, palabras clave visibles, tono claro y directo.
- Los textos deben ser consistentes entre creación y completado, con el patrón:
  - `Tarea completada: "<título>". Asignada a <miembro>.`
  - `Se te asignó la tarea "<título>".`
- El objetivo es que el texto se lea como una notificación nativa del sistema, sin llegar a ser verbose ni técnico.

## Criterios de aceptación

- Existe el módulo `home-backend/src/Application/Alerts/` con los casos de uso indicados.
- `ListAlerts` ordena por `createdAt` descendente.
- `DismissAlert` y `DismissAllAlerts` cumplen la API del dominio.
- `CreateAlert` está disponible para otros handlers.
- `CreateTaskHandler` genera una alerta solo si la tarea tiene `assignedMemberId` asignado.
- `CompleteTaskHandler` genera una alerta con `title` y `body` apropiados y con el miembro asignado en el texto.
- El contenido del `body` incluye el miembro al que se le asignó la tarea.
- El estilo de los mensajes se ajusta a la app actual y evita mensajes genéricos o demasiado técnicos.
- No se crean controladores ni endpoints en esta fase.

## Validación

1. Revisar que las clases y carpetas sigan el patrón de `Application/Tasks`.
2. Comprobar que los mensajes de alerta incluyen el miembro destinatario en el `body`.
3. Confirmar que `CreateAlertHandler` es reutilizable por `CreateTaskHandler` y `CompleteTaskHandler`.
4. Ejecutar las pruebas de application / domain y ajustar cualquier fallo de integración.

## Orden y dependencias

`T1 -> T2 -> T3 -> T4 -> T5 -> T6 -> T7 -> T8 -> T9 -> T10`

El orden refleja la secuencia natural: primero se prepara el módulo, luego cada caso de uso, luego la integración automática en tasks, y finalmente la validación con pruebas.

## Suposiciones y decisiones pendientes

- Se asume que la aplicación ya tiene acceso al nombre del miembro asignado a través del `Member` o del id asociado en la tarea; si no existe, se usará un texto seguro de fallback como `miembro asignado` para evitar nulls.
- Se asume que el patrón de mensajes se adoptará como texto natural, manteniendo el mismo tono que las notificaciones actuales de la app.
- Se asume que la creación automática de alertas no será una funcionalidad de notificación push, sino una alerta de dominio generada y persistida en el repositorio.
- Se asume que la alerta de `CompleteTask` debe referenciar el miembro asignado a la tarea, no al usuario que ejecutó la acción.

## Ejecución

- El trabajo se limita a la capa de aplicación y al dominio de alertas.
- El objetivo es que las alertas se creen y gestionen de forma reutilizable y consistente con el estilo de la aplicación, asegurando que las notificaciones internas reflejen el miembro właściamente asignado.
- La integración con tasks debe permanecer acotada a la lógica de negocio, sin tocar la API HTTP ni la interfaz del frontend.

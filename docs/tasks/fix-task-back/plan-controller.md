# Plan: Exponer metadatos de Task en el controlador HTTP

**Fecha**: 2026-10-01 | **Brief**: [brief-controller.md](brief-controller.md)

## Resumen

Actualizar `TaskController` para que lea, valide y propague `day`, `time`, `category`, `recurrence` y `priority` en las operaciones de creación y actualización, y para que los devuelva en la serialización de cada tarea. La modificación debe mantenerse acotada a la capa HTTP y respetar la política actual de errores del backend, donde los `InvalidArgumentException` se traducen a respuesta HTTP 400.

## Contexto técnico

- **Controlador actual**: `home-backend/src/Infrastructure/Http/TaskController.php`
- **Router actual**: `home-backend/src/Infrastructure/Http/Router.php`
- **Payload actual**: `create()` y `update()` reciben arrays de entrada y construyen commands con `title`, `householdId`, `userId`, `assignedMemberId` y `status`
- **Serialización actual**: `serializeTask()` devuelve `id`, `title`, `status`, `householdId`, `createdAt`, `completedAt` y `assignedMemberId`
- **Validación existente**: `Router` y `TaskController` usan `InvalidArgumentException` para rechazar ids inválidos y la infraestructura HTTP responde 400
- **Cobertura actual**: `home-backend/tests/Unit/Infrastructure/Http/TaskControllerTest.php` valida creación y listado sin metadatos

## Requisitos

- **REQ-001**: `TaskController::create()` debe leer `day`, `time`, `category`, `recurrence` y `priority` del payload y pasarlos a `CreateTaskCommand`.
- **REQ-002**: `TaskController::update()` debe hacer lo mismo con `day`, `time`, `category`, `recurrence` y `priority` para la actualización.
- **REQ-003**: `day` sólo puede aceptar `Mon`, `Tue`, `Wed`, `Thu`, `Fri`, `Sat` o `Sun`.
- **REQ-004**: `priority` sólo puede aceptar `low`, `med` o `high`.
- **REQ-005**: cuando `day` o `priority` no son válidos, el controller debe lanzar `InvalidArgumentException` para que la capa HTTP decida responder con 400.
- **REQ-006**: en la serialización de tareas debe añadirse `day`, `time`, `category`, `recurrence` y `priority` usando los getters de la entidad.
- **REQ-007**: valores ausentes o vacíos deben tratarse como opcionales para no introducir regresiones ni romper la semántica de `null`.
- **REQ-008**: la validación del controller debe limitase a `day` y `priority`, conforme al brief.

## Tareas

| ID | Tarea | Prioridad | Dependencias | Archivos afectados | Complejidad |
|---|---|---|---|---|---|
| T1 | Revisión del contrato de entrada del controller y extracción de los cinco metadatos desde `create()` y `update()`. | Alta | Ninguna | `home-backend/src/Infrastructure/Http/TaskController.php` | Media |
| T2 | Añadir validación de `day` y `priority` con los valores permitidos y reutilizar `InvalidArgumentException` para responder 400. | Alta | T1 | `home-backend/src/Infrastructure/Http/TaskController.php` | Media |
| T3 | Propagar los metadatos a `CreateTaskCommand` y `UpdateTaskCommand` manteniendo la firma actual de los campos existentes. | Alta | T1, T2 | `home-backend/src/Infrastructure/Http/TaskController.php` | Media |
| T4 | Extender `serializeTask()` para incluir los nuevos campos y mantener el resto del payload estable. | Alta | T1 | `home-backend/src/Infrastructure/Http/TaskController.php` | Baja |
| T5 | Añadir pruebas unitarias para creación, actualización y serialización con los metadatos. | Media | T1-T4 | `home-backend/tests/Unit/Infrastructure/Http/TaskControllerTest.php` | Media |
| T6 | Ejecutar PHPUnit de infraestructura para confirmar que la API HTTP no rompe el flujo actual. | Media | T1-T5 | `home-backend/tests/**` | Baja |

## Pasos de implementación

### Paso 1: Extracción del payload

- En `create(array $payload)`, leer `day`, `time`, `category`, `recurrence` y `priority` desde `$payload`.
- En `update(int $taskId, array $payload)`, hacer lo mismo para la actualización.
- Tratar `null`, ausencia y cadena vacía como “valor opcional ausente” para no introducir cambios de comportamiento innecesarios.
- Mantener la lectura actual para `title`, `assignedMemberId`, `status` y `householdId` sin tocar la lógica que ya funciona.

### Paso 2: Validación de dominios restringidos

- Añadir una validación auxiliar en `TaskController` para `day` y `priority` con conjuntos permitidos:
  - `day`: `Mon`, `Tue`, `Wed`, `Thu`, `Fri`, `Sat`, `Sun`
  - `priority`: `low`, `med`, `high`
- Si el campo llega con valor no permitido, lanzar `new \InvalidArgumentException(...)` con un mensaje claro.
- Dejar que el flujo actual del backend responda con 400 tal como se hace con otras entradas inválidas.

### Paso 3: Propagación a commands

- En `create()`, pasar a `new CreateTaskCommand(...)` los valores de `day`, `time`, `category`, `recurrence` y `priority` junto al resto de argumentos.
- En `update()`, hacer lo mismo con `new UpdateTaskCommand(...)` y conservar la firma de `status` y `assignedMemberId` sin romper la API actual.
- No introducir normalización ni casting extra; se recomienda mantener los strings tal cual llegan en la validación de dominio de la entidad.

### Paso 4: Serialización

- En `serializeTask()`, añadir las claves:
  - `day` => $task->day()
  - `time` => $task->time()
  - `category` => $task->category()
  - `recurrence` => $task->recurrence()
  - `priority` => $task->priority()
- Mantener el orden del array compatible con la estructura actual para no romper consumidores existentes.
- Confirmar que `list()` y `complete()` reutilizan `serializeTask()` y por tanto también devuelven los nuevos campos.

### Paso 5: Pruebas

- Ampliar `TaskControllerTest` con una prueba que cree una tarea con `day`, `time`, `category`, `recurrence` y `priority` y confirme que aparecen en la respuesta.
- Añadir una prueba para `day` inválido y otra para `priority` inválido, verificando que se lanza `InvalidArgumentException`.
- Mantener las pruebas existentes de creación y listado para asegurar compatibilidad.

## Criterios de aceptación

- `TaskController::create()` y `update()` leen y propagan los cinco metadatos.
- `day` tiene validación restrictiva y `priority` también.
- Los valores no permitidos lanzan `InvalidArgumentException` conforme al manejo actual del HTTP layer.
- `serializeTask()` devuelve `day`, `time`, `category`, `recurrence` y `priority` junto con los campos ya existentes.
- La creación y el listado de tareas siguen funcionando sin romper el contrato actual de respuesta.
- Las pruebas unitarias cubren el caso válido y los casos inválidos para `day` y `priority`.

## Validación

1. Ejecutar PHPUnit para la suite de infraestructura HTTP y tareas.
2. Confirmar que la creación con payload completo y la serialización actual siguen funcionando.
3. Verificar que `InvalidArgumentException` se dispara en valores inválidos de `day` y `priority`.
4. Registrar cualquier fallo ajeno al alcance del controlador sin ampliar la tarea a persistencia o frontend.

## Orden y dependencias

`T1 -> T2 -> T3 -> T4 -> T5 -> T6`

La secuencia es natural: primero extraer y validar los campos, luego pasarlos a commands, después serializarlos y finalmente verificar con tests.

## Suposiciones y decisiones pendientes

- Si el payload envía una cadena vacía en `day` o `priority`, se interpretará como un valor opcional ausente si la capa de aplicación ya lo hace; no debe convertirse en un error salvo que el flujo de entrada lo defina explícitamente como inválido.
- El brief exige validación solo para `day` y `priority`; `time`, `category` y `recurrence` no reciben restricciones en este paso.
- Este plan no toca migraciones ni persistencia, ya que se asume que la tabla ya contiene las columnas solicitadas.
- La serialización centralizada en `serializeTask()` permite que tanto `create()`, `list()` y `complete()` devuelvan los metadatos sin duplicación de lógica.

## Ejecución

- El cambio debe mantenerse mínimo y orientado a la capa HTTP.
- Debe priorizar compatibilidad con el contrato existente de `TaskController` y con la infraestructura que transforma `InvalidArgumentException` en 400.
- La tarea debe considerarse un enlace entre el dominio y la API: no es un rediseño del flujo, sino la última etapa para propagar los metadatos al cliente.

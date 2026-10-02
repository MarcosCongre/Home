# Plan: Propagar metadatos de Task desde commands y handlers

**Fecha**: 2026-10-01 | **Brief**: [brief-commands.md](brief-commands.md)

## Resumen

Actualizar los commands y handlers de tareas para que transporten los metadatos adicionales `day`, `time`, `category`, `recurrence` y `priority` hasta la entidad `Task`, sin perder el flujo actual de creación, actualización, asignación y estado. La tarea debe mantenerse acotada a la capa de aplicación y no tocar vista, persistencia ni esquema.

## Contexto técnico

- **CreateTaskCommand actual**: `home-backend/src/Application/Tasks/CreateTask/CreateTaskCommand.php`
- **UpdateTaskCommand actual**: `home-backend/src/Application/Tasks/UpdateTask/UpdateTaskCommand.php`
- **CreateTaskHandler actual**: `home-backend/src/Application/Tasks/CreateTask/CreateTaskHandler.php`
- **UpdateTaskHandler actual**: `home-backend/src/Application/Tasks/UpdateTask/UpdateTaskHandler.php`
- **Entidad de dominio actual**: `home-backend/src/Domain/Tasks/Task.php`
- **Flujo actual**:
  - `CreateTaskHandler` construye la tarea con `Task::create(...)` y luego hace `update()` solo para `assignedMemberId` si aplica.
  - `UpdateTaskHandler` llama directamente a `$task->update($command->title, $command->assignedMemberId, $command->status)`.

## Requisitos

- **REQ-001**: `CreateTaskCommand` debe aceptar los metadatos `?string $day`, `?string $time`, `?string $category`, `?string $recurrence` y `?string $priority`.
- **REQ-002**: `UpdateTaskCommand` debe aceptar los mismos metadatos para propagación en actualizaciones.
- **REQ-003**: `CreateTaskHandler` debe transmitir esos valores a la entidad al crear la tarea.
- **REQ-004**: `UpdateTaskHandler` debe transmitir esos valores al actualizar la entidad.
- **REQ-005**: el flujo existente de `title`, `assignedMemberId`, `status` y `completedAt` debe conservarse intacto.
- **REQ-006**: si la entidad define `priority` con valor por defecto `med`, los commands deben permitir `null` para representar “sin valor explícito” y dejar que la entidad resuelva el valor efectivo.
- **REQ-007**: no se aplican validaciones ni transformación de formatos en la capa de aplicación.

## Tareas

| ID | Tarea | Prioridad | Dependencias | Archivos afectados | Complejidad |
|---|---|---|---|---|---|
| T1 | Extender `CreateTaskCommand` con los campos opcionales de metadatos y mantener la firma restante igual. | Alta | Ninguna | `home-backend/src/Application/Tasks/CreateTask/CreateTaskCommand.php` | Baja |
| T2 | Extender `UpdateTaskCommand` con los mismos campos opcionales y mantener el contrato actual de `taskId`, `title`, `assignedMemberId` y `status`. | Alta | Ninguna | `home-backend/src/Application/Tasks/UpdateTask/UpdateTaskCommand.php` | Baja |
| T3 | Ajustar `CreateTaskHandler` para construir la entidad con los metadatos de la command y mantener la lógica actual de `assignedMemberId`. | Alta | T1 | `home-backend/src/Application/Tasks/CreateTask/CreateTaskHandler.php` | Media |
| T4 | Ajustar `UpdateTaskHandler` para transferir `day`, `time`, `category`, `recurrence` y `priority` dentro del `update()` de la entidad. | Alta | T2 | `home-backend/src/Application/Tasks/UpdateTask/UpdateTaskHandler.php` | Media |
| T5 | Revisar la compatibilidad con los tests unitarios actuales y añadir una prueba específica para la propagación de metadatos. | Media | T1-T4 | `home-backend/tests/Unit/Application/Tasks/CreateTaskHandlerTest.php`, `home-backend/tests/Unit/Application/Tasks/UpdateTaskHandlerTest.php` (si existe o se crea) | Media |
| T6 | Ejecutar la validación del caso de uso de tareas en PHPUnit para confirmar que creación y actualización siguen funcionando. | Media | T1-T5 | `home-backend/tests/**` | Baja |

## Pasos de implementación

### Paso 1: Commands

- En `CreateTaskCommand`, agregar las propiedades opcionales al final de la firma del constructor:
  - `public ?string $day = null`
  - `public ?string $time = null`
  - `public ?string $category = null`
  - `public ?string $recurrence = null`
  - `public ?string $priority = null`
- En `UpdateTaskCommand`, aplicar el mismo patrón para mantener la semántica del command consistente.
- No añadir validaciones ni casting; los values deben ser transportados tal cual.

### Paso 2: Propagación desde creación

- En `CreateTaskHandler::handle()`, construir `Task::create(...)` con los nuevos metadatos si la entidad ya los acepta en la firma de la factory.
- Si la entidad usa `update()` para asignar el miembro y otros campos, realizar esa actualización con ambos conjuntos de datos: `title`, `assignedMemberId`, y los nuevos metadatos.
- Mantener la lógica de `assignedMemberId` y `status` sin cambios de comportamiento.

### Paso 3: Propagación desde actualización

- En `UpdateTaskHandler::handle()`, llamar a `$task->update(...)` con todos los valores del command.
- Si la entidad define parámetros opcionales para los metadatos, pasarlos siempre en la misma posición o mediante argumentos nombrados para evitar roturas.
- Mantener `status` como último argumento opcional para no romper la API de actualización existente.

### Paso 4: Pruebas

- Añadir una prueba específica para verificar que:
  - `CreateTaskHandler` conserva `priority` por defecto cuando la command no aporta valor;
  - los campos `day`, `time`, `category`, `recurrence` y `priority` quedan propagados 
  - `UpdateTaskHandler` modifica esos campos cuando se envían con valores explícitos.
- Mantener la prueba de creación y estado pendiente del caso base existente para evitar regresiones.

### Paso 5: Validación

- Ejecutar PHPUnit para la suite de tareas de aplicación.
- Confirmar que no hay fallos introducidos en `CreateTaskHandler` ni `UpdateTaskHandler` y que la propagación de metadatos no afecta a la lógica de `assignedMemberId` o `status`.

## Criterios de aceptación

- `CreateTaskCommand` y `UpdateTaskCommand` incluyen los cinco metadatos opcionales.
- Los handlers transmiten los valores a la entidad de dominio en creación y actualización.
- La lógica existente de `title`, `assignedMemberId`, `status` y `completedAt` permanece intacta.
- La prioridad por defecto sigue delegada en la entidad cuando el command no envía un valor explícito.
- La capa de aplicación no valida ni reformatea los campos; solo los propaga.
- Las pruebas unitarias cubren la propagación de metadatos sin romper la creación actual.

## Validación

1. Ejecutar PHPUnit sobre los tests de aplicación de tareas.
2. Comprobar que `CreateTaskHandlerTest` y la suite de actualización siguen pasando.
3. Si aparecen errores ajenos al alcance, registrarlos sin ampliar el plan a persistencia o controller.

## Orden y dependencias

`T1 -> T3` y `T2 -> T4`, con `T5` y `T6` después de la propagación en ambos handlers.

La lógica está acotada: primero se extiende la API de los commands, luego se propaga la información desde los handlers y, finalmente, se valida la regresión con pruebas.

## Suposiciones y decisiones pendientes

- El valor `null` en los commands representa “no se envió prioridad explícita” y no fuerza limpieza del valor actual.
- La entidad resuelve el valor efectivo de `priority` con su default (`med`) cuando el command aporta `null`.
- La decisión de si `null` en actualización significa “conservar” o “limpiar” debe confirmarse con la firma final de `Task::update()`. Mientras tanto, el plan asume el comportamiento menos invasivo: `null` no sobreescribe el valor existente salvo que la entidad lo defina explícitamente.
- El alcance no incluye cambios de persistencia, endpoint ni serialización HTTP.

## Ejecución

- El plan debe ejecutarse sin tocar la base de datos ni la infraestructura.
- La modificación recomendable es mínima y directa: ampliar commands, ajustar handlers y asegurar la compatibilidad con la entidad de dominio en el siguiente paso.
- Este trabajo debe servir como preparación para la siguiente fase del flujo de Task, que implica la entidad y, posteriormente, la persistencia y la API si se decide ampliar el alcance.

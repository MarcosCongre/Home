# Plan: Ampliar la entidad Task con metadatos

**Fecha**: 2026-10-01 | **Brief**: [brief-entity.md](brief-entity.md)

## Resumen

Actualizar la entidad de dominio `App\Domain\Tasks\Task` para incorporar `day`, `time`, `category`, `recurrence` y `priority` sin romper la construcción actual ni la API de actualización ya existente. La modificación debe mantenerse aislada en el dominio y debe conservar los argumentos nombrados actuales, junto con la semántica de `null` para los campos opcionales y `med` como prioridad por defecto.

## Contexto técnico

- **Entidad actual**: `home-backend/src/Domain/Tasks/Task.php`
- **Constructor actual**: `id`, `title`, `householdId`, `createdAt`, `status`, `completedAt`, `assignedMemberId`
- **Factory actual**: `Task::create(...)` con firma establecida y argumentos nombrados
- **Método de actualización actual**: `update(string $title, ?int $assignedMemberId, ?TaskStatus $status = null)`
- **Cobertura existente**: `home-backend/tests/Unit/Domain/Tasks/TaskTest.php` valida creación y completado, pero no cubre metadatos ni actualización con nuevos campos
- **Restricción de alcance**: no se toca repositorio, migración, controller ni endpoints; solo se modifica el dominio y sus pruebas unitarias

## Requisitos

- **REQ-001**: `Task` debe admitir `day`, `time`, `category` y `recurrence` como propiedades opcionales.
- **REQ-002**: `priority` debe tener valor predeterminado `med` y debe poder actualizarse si se recibe un valor explícito.
- **REQ-003**: los nuevos parámetros deben añadirse al final del constructor para conservar compatibilidad con argumentos nombrados existentes.
- **REQ-004**: `update()` debe aceptar y asignar los cinco metadatos sin romper llamadas actuales.
- **REQ-005**: deben existir getters `day()`, `time()`, `category()`, `recurrence()` y `priority()`.
- **REQ-006**: `Task::create()` debe seguir funcionando sin cambios requeridos para el flujo existente, o aceptar valores adicionales si se decide reutilizar la factory en un futuro punto de entrada.
- **REQ-007**: por ahora no se introducen validaciones de formato ni catálogo de valores permitidos; la entidad solo conserva los datos tal cual llegan.

## Tareas

| ID | Tarea | Prioridad | Dependencias | Archivos afectados | Complejidad |
|---|---|---|---|---|---|
| T1 | Revisar y ajustar la firma del constructor de `Task` para añadir los cinco metadatos al final sin romper argumentos nombrados actuales. Definir propiedades con valores por defecto apropiados (`null` para campos opcionales y `med` para prioridad). | Alta | Ninguna | `home-backend/src/Domain/Tasks/Task.php` | Media |
| T2 | Ajustar `Task::create()` para que siga construyendo tareas de forma compatible y, si se decide, permita aceptar los nuevos valores sin obligar a todos los consumidores a cambiar. | Alta | T1 | `home-backend/src/Domain/Tasks/Task.php` | Baja |
| T3 | Extender `update()` para aceptar y asignar `day`, `time`, `category`, `recurrence` y `priority`, manteniendo el comportamiento actual del estado y `completedAt` cuando se actualiza `status`. | Alta | T1 | `home-backend/src/Domain/Tasks/Task.php` | Media |
| T4 | Añadir getters para los cinco metadatos y compartir la misma convención de acceso que las propiedades existentes. | Alta | T1, T3 | `home-backend/src/Domain/Tasks/Task.php` | Baja |
| T5 | Ampliar la prueba unitaria de dominio para cubrir creación con valores predeterminados y actualización con metadatos. | Media | T1-T4 | `home-backend/tests/Unit/Domain/Tasks/TaskTest.php` | Media |
| T6 | Ejecutar la validación mínima del dominio (`phpunit` sobre la suite unitaria de `Task`) para confirmar compatibilidad y registrar si existen fallos ajenos al alcance. | Media | T1-T5 | `home-backend/tests/Unit/Domain/Tasks/TaskTest.php` | Baja |

## Pasos de implementación

### Paso 1: Modificación del dominio

- Actualizar la clase `Task` en `home-backend/src/Domain/Tasks/Task.php`.
- Añadir las propiedades de metadatos en el orden sugerido por el brief: `?string $day = null`, `?string $time = null`, `?string $category = null`, `?string $recurrence = null`, `string $priority = 'med'`.
- Mantener los parámetros actuales antes de los nuevos para no romper la firma de llamadas con nombre ya utilizadas.
- No introducir validaciones de valor ni conversiones de formato; solo conservar las cadenas entrantes.

### Paso 2: Compatibilidad de creación y actualización

- Reutilizar la lógica de `Task::create()` sin romper la creación existente.
- Diseñar `update()` para aceptar los nuevos metadatos con valores opcionales, sin cambiar la forma de manejar `title`, `assignedMemberId` y `status` ya existente.
- Asegurar que `status` y `completedAt` siguen comportándose igual que en la implementación actual cuando el estado cambia.

### Paso 3: Exposición por getters

- Añadir los siguientes getters:
  - `day(): ?string`
  - `time(): ?string`
  - `category(): ?string`
  - `recurrence(): ?string`
  - `priority(): string`
- Mantener la convención actual de la entidad: los getters devuelven un valor listo para consumo del resto del dominio y la capa HTTP.

### Paso 4: Validación del dominio

- Extender `TaskTest` con un caso para comprobar que:
  - la prioridad por defecto es `med`;
  - los campos opcionales empiezan en `null` cuando no se suministran;
  - `update()` acepta y guarda los nuevos valores;
  - la creación conservada sigue funcionando para el caso existente.
- Ejecutar la prueba unitaria específica del dominio o la suite de PHPUnit relacionada con `Task`.

## Criterios de aceptación

- La entidad `Task` incluye `day`, `time`, `category`, `recurrence` y `priority` con el tratamiento acordado por el brief.
- Los argumentos nombrados existentes en `Task::create()` y el constructor siguen funcionando sin romper compatibilidad.
- El constructor agrega los nuevos campos al final para no afectar la invocación actual.
- `update()` acepta los nuevos valores y los asigna correctamente.
- Los getters devuelven los valores esperados.
- La prioridad por defecto es `med` cuando no se proporciona explícitamente.
- Los campos `day`, `time`, `category` y `recurrence` siguen siendo opcionales (`null`).
- No se introducen validaciones ni catalogaciones de dominio fuera del alcance solicitado.

## Validación

1. Ejecutar la prueba unitaria de dominio de `Task` con PHPUnit.
2. Confirmar que la suite existe y que no se rompe la lógica de creación/completado de tareas.
3. Registrar si alguna prueba falla por motivos ajenos al dominio y no ampliar el alcance.

## Orden y dependencias

`T1 -> T2 -> T3 -> T4 -> T5 -> T6`

La secuencia sigue el flujo natural: primero definir el estado de la entidad, después compatibilizar su creación y actualización, luego exponerlo y finalmente verificarlo con pruebas.

## Suposiciones y limitaciones

- El backend ya tiene las columnas necesarias en la base de datos, por lo que esta tarea no incluye cambios de persitencia ni migración.
- La entidad conserva los metadatos como cadenas sin validaciones adicionales.
- El plan no incluye handlers, controllers o endpoints; esa ampliación se gestionará en una siguiente fase si se requiere exponer estos campos al exterior.
- La validación de comportamiento se limita a la entidad y a sus pruebas unitarias, no a integración HTTP.

## Ejecución

- Esta tarea depende del brief de entidad y está enfocada exclusivamente en el modelo de dominio.
- El trabajo previsto no debe expandirse a repositorios, queries, serialización ni API mientras no se confirme explícitamente un alcance adicional.
- La aproximación recomendada es la más conservadora posible: añadir los estados al dominio y mantener la compatibilidad casi total con la construcción actual de `Task`.

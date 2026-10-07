# Plan: Persistencia y API HTTP de Alerts

**Fecha**: 2026-10-04 | **Brief**: [brief-3.md](brief-3.md)

## Resumen

Completar la infraestructura de Alerts: persistir las alertas mediante PDO y una migración SQL, exponer consulta y descarte por HTTP, serializar el miembro asociado como `user`, y registrar las dependencias en los puntos de composición que realmente utiliza el proyecto. Al borrar un miembro, las tareas y alertas históricas deben conservarse con su referencia de miembro en `NULL`. La hora de alerta enviada al cliente se formateará como `hh:mm AM/PM`.

## Contexto técnico

- **Persistencia de referencia**: `home-backend/src/Infrastructure/Persistence/PdoTaskRepository.php`.
- **Migración vigente**: `home-backend/database/migrations/001_initial_schema.sql` define `tasks.assigned_member_id` nullable con `ON DELETE SET NULL`; Alerts debe seguir la misma semántica.
- **HTTP de referencia**: `home-backend/src/Infrastructure/Http/TaskController.php` y `Router.php`.
- **Composición productiva**: `home-backend/public/index.php` crea el PDO, los repositorios y el `Router` directamente.
- **Wrapper de aplicación**: `home-backend/src/Infrastructure/Bootstrap/App.php` construye un Router para las pruebas de integración; extenderlo manteniendo compatibilidad con invocaciones actuales.
- **Miembros**: `MemberRepositoryInterface::findById()` permite resolver el nombre asociado a `Alert::memberId()`; si es `null` o ya no existe, `user` será `null`.
- **Formato horario**: `TaskModal` usa `<input type="time">`, cuyo valor transportado por el navegador es `HH:mm` en formato de 24 horas. El campo `time` de Alerts proviene de `createdAt` y se serializará explícitamente en formato de 12 horas `hh:mm AM/PM` (por ejemplo, `08:30 AM`). No se cambia el contrato de hora de tareas en este trabajo.

## Decisiones confirmadas

- El cableado se adaptará a los puntos de composición reales existentes: `public/index.php` para producción y `Bootstrap\App` para el wrapper/pruebas, evitando mover la composición a una arquitectura nueva.
- La FK de `alerts.member_id` será nullable y usará `ON DELETE SET NULL`. La migración actual ya tiene ese comportamiento para `tasks.assigned_member_id`; al eliminar un miembro, ambas referencias quedarán nulas y las tareas/alertas seguirán disponibles.
- El JSON de Alerts devolverá `time` derivado de `createdAt` con formato `h:i A` de PHP, equivalente a `hh:mm AM/PM` (por ejemplo, `08:30 AM`).

## Requisitos

- **REQ-001**: implementar `PdoAlertRepository` para guardar y reconstruir los campos de `Alert`, usando `AlertRepositoryInterface`.
- **REQ-002**: soportar búsqueda por hogar, guardado, descarte individual y descarte de todas las alertas de un hogar.
- **REQ-003**: añadir una migración SQL para `alerts` con clave primaria autoincremental, `household_id`, `member_id` nullable, `title`, `body`, `icon`, `urgent`, `status` y `created_at`.
- **REQ-004**: declarar la FK de `alerts.member_id` hacia `members.id` con `ON DELETE SET NULL`, de modo que las alertas históricas no se borren junto con el miembro.
- **REQ-005**: conservar también el comportamiento existente de `tasks.assigned_member_id ON DELETE SET NULL`; no eliminar tareas ni alertas al borrar un miembro.
- **REQ-006**: implementar `AlertController` con `list(string $householdId)`, `dismiss(int $id)` y `dismissAll(string $householdId)` delegando a los handlers de aplicación.
- **REQ-007**: serializar cada alerta con `id`, `title`, `body`, `icon`, `urgent`, `status`, `time` y `user`.
- **REQ-008**: obtener `time` a partir de `createdAt` en formato `h:i A` (`hh:mm AM/PM`).
- **REQ-009**: resolver `user` como nombre del miembro asociado; devolver `null` cuando `memberId` sea `null` o el miembro no se encuentre.
- **REQ-010**: exponer `GET /alerts?householdId=...`, `PATCH /alerts/{id}/dismiss` y `POST /alerts/dismiss-all`, leyendo `householdId` del JSON de la última ruta.
- **REQ-011**: inyectar `AlertRepositoryInterface` opcionalmente en `Router` y leer query params siguiendo el patrón de `GET /tasks`.
- **REQ-012**: registrar `PdoAlertRepository` en el arranque productivo y hacer disponible la misma dependencia desde `Bootstrap\App` sin romper constructores/casos de uso actuales.
- **REQ-013**: conectar la dependencia de Alerts requerida por los handlers de tareas previamente definidos, sin cambiar aquí las reglas de creación automática de alertas.

## Tareas

| ID | Tarea | Prioridad | Dependencias | Archivos afectados | Complejidad |
|---|---|---|---|---|---|
| T1 | Crear migración SQL de `alerts`, índices necesarios y FK nullable con `ON DELETE SET NULL`. | Alta | Ninguna | `home-backend/database/migrations/002_create_alerts.sql` | Media |
| T2 | Implementar persistencia PDO de alertas y operaciones de descarte. | Alta | T1 | `home-backend/src/Infrastructure/Persistence/PdoAlertRepository.php` | Media |
| T3 | Crear `AlertController` que delegue a los casos de uso y serialice alertas, nombre de miembro y hora. | Alta | T2 | `home-backend/src/Infrastructure/Http/AlertController.php` | Media |
| T4 | Añadir rutas de listado, descarte individual y descarte masivo al Router. | Alta | T3 | `home-backend/src/Infrastructure/Http/Router.php` | Media |
| T5 | Cablear Alerts en `public/index.php` y extender `Bootstrap\App` conservando compatibilidad. | Alta | T2, T4 | `home-backend/public/index.php`, `home-backend/src/Infrastructure/Bootstrap/App.php` | Media |
| T6 | Pasar el repositorio Alerts al flujo HTTP de tareas donde los handlers existentes lo requieran para `CreateAlertHandler`. | Media | T2, T5 | `home-backend/src/Infrastructure/Http/Router.php`, `TaskController.php` | Media |
| T7 | Añadir pruebas de persistencia para crear, leer, descartar y verificar `member_id = NULL` tras borrar el miembro. | Alta | T1-T2 | `home-backend/tests/Integration/Persistence/` | Media |
| T8 | Añadir pruebas HTTP para las tres rutas, serialización de `user` y formato `hh:mm AM/PM`. | Alta | T3-T6 | `home-backend/tests/Unit/Infrastructure/Http/`, `home-backend/tests/Integration/Http/` | Media |
| T9 | Ejecutar validación focalizada de migración/persistencia, controlador y Router. | Media | T7-T8 | `home-backend/tests/**` | Baja |

## Pasos de implementación

### Paso 1: Migración de esquema

- Crear la siguiente migración secuencial al esquema vigente: `home-backend/database/migrations/002_create_alerts.sql`.
- Crear la tabla `alerts` con tipos compatibles con MySQL/InnoDB y el estilo de `001_initial_schema.sql`:
  - `id INT NOT NULL AUTO_INCREMENT PRIMARY KEY`
  - `household_id VARCHAR(128) NOT NULL`
  - `member_id INT NULL`
  - `title VARCHAR(255) NOT NULL`
  - `body TEXT NOT NULL`
  - `icon VARCHAR(64) NOT NULL`
  - `urgent TINYINT(1) NOT NULL DEFAULT 0`
  - `status VARCHAR(32) NOT NULL`
  - `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`
- Añadir índices por `household_id` y `member_id` para soportar los listados por hogar y la FK.
- Añadir `FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL`.
- No modificar `001_initial_schema.sql` ni cambiar la FK existente de tareas; ambas asociaciones deben quedar nulas al borrar al miembro.

### Paso 2: Repositorio PDO

- Implementar `AlertRepositoryInterface` en `PdoAlertRepository` con consultas preparadas y parámetros tipados.
- `findByHousehold()` debe devolver entidades `Alert` del hogar, ordenadas por `created_at DESC` para que la API muestre primero las más recientes.
- `save()` debe persistir los campos del dominio y asignar el id generado cuando corresponda, retornando la alerta guardada según el contrato.
- `dismiss()` debe cambiar el estado a `dismissed`; `dismissAll()` debe actualizar únicamente las alertas del hogar especificado.
- Mantener `member_id` nullable tanto al escribir como al hidratar; la eliminación de un miembro no debe cascader a borrado de alertas.
- Si el proyecto requiere compatibilidad con SQLite para pruebas del repositorio, crear allí el esquema equivalente incluyendo la semántica de FK y habilitar/verificar `PRAGMA foreign_keys` en la fixture o conexión de prueba, sin alterar el esquema MySQL de producción.

### Paso 3: Controlador y contrato JSON

- Construir el controlador con `AlertRepositoryInterface` y el `MemberRepositoryInterface` ya usado por el Router.
- Delegar listado, descarte individual y descarte masivo a `ListAlertsHandler`, `DismissAlertHandler` y `DismissAllAlertsHandler`.
- Serializar los campos del contrato sin exponer estructuras PDO ni entidades directamente.
- Formatear `createdAt` usando `format('h:i A')` en la clave JSON `time`.
- Resolver el nombre de `user` a través de `MemberRepositoryInterface::findById()`; entregar `null` si el id es nulo o el repositorio no devuelve un miembro.

### Paso 4: Rutas HTTP

- Extender el constructor de `Router` con la dependencia de Alert opcional, manteniendo operativas las rutas existentes cuando no se provea.
- `GET /alerts`: leer `householdId` del request o `QUERY_STRING` con `parse_str`, como en `GET /tasks`; delegar al controlador.
- `PATCH /alerts/{id}/dismiss`: validar el id positivo con el helper existente y devolver una respuesta de confirmación consistente con las rutas actuales.
- `POST /alerts/dismiss-all`: decodificar el body JSON y obtener `householdId`.
- Cuando el repositorio de Alerts no esté configurado, no se debe interceptar ni alterar las rutas existentes de tareas y miembros.

### Paso 5: Composición de dependencias

- En `public/index.php`, instanciar una sola vez `PdoMemberRepository`, `PdoTaskRepository` y `PdoAlertRepository` con el mismo PDO, y pasarlos al Router.
- Extender `Bootstrap\App` para aceptar el repositorio de Alerts opcionalmente y construir el Router con él; mantener las pruebas y llamadas existentes que solo suministran tareas.
- Pasar la dependencia de Alerts a `TaskController` y luego a los handlers automáticos de tarea solo en la medida necesaria para usar el `CreateAlertHandler` definido en la fase de Application. No reescribir en este plan cuándo o qué alertas se generan.

### Paso 6: Validación y pruebas

- Probar persistencia/hidratación, orden descendente, guardado con y sin `memberId`, descarte individual y masivo.
- En prueba de integración MySQL o entorno compatible, eliminar un miembro asociado y confirmar que tanto `tasks.assigned_member_id` como `alerts.member_id` pasan a `NULL` sin borrar sus filas.
- Probar que la respuesta HTTP mantiene `user` con nombre antes de eliminar el miembro y `null` después.
- Confirmar que `time` se serializa en formato `hh:mm AM/PM` a partir del `createdAt` conocido.
- Probar las rutas con y sin `QUERY_STRING` según el patrón del Router, además de ejecutar las suites existentes de HTTP y persistencia.

## Criterios de aceptación

- Existe una migración de Alerts con tabla, tipos, índices y FK nullable configurada con `ON DELETE SET NULL`.
- El borrado de un miembro conserva tareas y alertas, pero deja `assigned_member_id` y `member_id` en `NULL`.
- `PdoAlertRepository` implementa lectura, escritura, descarte individual y masivo, rehidratando correctamente el dominio.
- Las rutas de Alerts funcionan y las rutas preexistentes se mantienen compatibles.
- La respuesta de Alerts tiene los campos acordados; `user` es el nombre asociado o `null`.
- `time` en la respuesta HTTP usa formato de 12 horas con AM/PM, generado desde `createdAt`.
- El punto de arranque productivo y el wrapper `Bootstrap\App` pueden proveer el repositorio sin romper invocaciones actuales.
- La dependencia necesaria para los handlers automáticos existentes queda conectada, sin cambiar la regla de negocio de generación de alertas.

## Validación

1. Ejecutar PHPUnit en las pruebas nuevas y existentes de `Integration/Persistence`, `Integration/Http` y `Unit/Infrastructure/Http`.
2. Ejecutar la migración en una base MySQL de prueba con el esquema inicial y comprobar la FK `ON DELETE SET NULL`.
3. Verificar los tres endpoints, el nombre/null de `user`, el orden de alertas y `time` en formato `hh:mm AM/PM`.
4. Confirmar que el flujo HTTP existente de Tasks y Members sigue pasando sus pruebas.

## Orden y dependencias

`T1 -> T2 -> T3 -> T4 -> T5 -> T6 -> T7 -> T8 -> T9`

Primero se declara el esquema y su semántica de borrado; luego persistencia y controlador; después rutas y composición; finalmente se comprueban la compatibilidad y el comportamiento completo.

## Suposiciones y límites

- El número `002_create_alerts.sql` se asume disponible porque la estructura compartida contiene únicamente `001_initial_schema.sql`; antes de implementar se debe verificar que no se haya añadido otra migración.
- La migración se aplica con el mecanismo operativo actual del proyecto, sin introducir un framework de migraciones nuevo.
- Se da por existente el dominio y los casos de uso de Alerts descritos en `plan-1.md` y `plan-2.md`; si sus nombres o firmas difieren, se adaptará la integración a la implementación real sin duplicar lógica.
- `time` se refiere al campo de la serialización HTTP de alertas derivado de `createdAt`. El valor `time` de una tarea se conserva como dato del formulario/API (`HH:mm`) y no se transforma en esta fase.
- Si el entorno de pruebas no dispone de MySQL, las pruebas SQLite validarán el repositorio; la acción referencial `ON DELETE SET NULL` deberá además verificarse contra MySQL o mediante una prueba compatible con InnoDB, ya que producción usa MySQL.

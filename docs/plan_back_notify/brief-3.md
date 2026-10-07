# Brief: Persistencia y API HTTP de Alerts

## Solicitud original
Completar la integración de Alerts en infraestructura: persistencia PDO y migración SQL, controlador HTTP, rutas de consulta y descarte, y registro del repositorio en el cableado de la aplicación.

## Objetivo
Exponer las alertas del hogar a través de la API existente, persistirlas en la base de datos y devolver al cliente la información de alerta junto con el nombre del miembro asociado cuando exista.

## Enfoque propuesto
Seguir los patrones de `PdoTaskRepository`, `TaskController` y `Router`: mapear entre filas SQL y el dominio Alerts, delegar acciones a los casos de uso y devolver estructuras serializables a JSON. Añadir la tabla `alerts` con una referencia opcional a `members` y conectar el repositorio en la composición de dependencias.

## Alcance
### Incluido
- `PdoAlertRepository.php` implementando `AlertRepositoryInterface`, con persistencia y lectura de los datos de alerta y operaciones para descartar una alerta o todas las de un hogar.
- Migración SQL para `alerts`, con `id` autoincremental, `household_id`, `member_id` nullable como FK a `members`, `title`, `body`, `icon`, `urgent` como `TINYINT`, `status` como `VARCHAR` y `created_at`.
- `AlertController.php` con `list(string $householdId)`, `dismiss(int $id)` y `dismissAll(string $householdId)`.
- Serialización HTTP con los campos `id`, `title`, `body`, `icon`, `urgent`, `status`, `time` (a partir de `createdAt` formateado) y `user` (nombre del miembro o `null`).
- Rutas `GET /alerts?householdId=...`, `PATCH /alerts/{id}/dismiss` y `POST /alerts/dismiss-all`, leyendo `householdId` del body JSON en la última ruta.
- Inyección opcional de `AlertRepositoryInterface` en Router y extracción de query parameters con `parse_str`, siguiendo el dispatch actual de `GET /tasks`.
- Cableado de `PdoAlertRepository` junto al repositorio de Tasks para que las rutas queden disponibles en la aplicación.

### Excluido
- Cambios de interfaz frontend, nuevas reglas de negocio de generación de alertas y modificaciones fuera de los componentes necesarios para persistencia, API y composición de dependencias.

## Supuestos
- El controlador usará el dominio y los casos de uso de Alerts definidos en los briefs anteriores.
- La información `user` se resolverá a partir de `memberId` usando el repositorio de miembros disponible; será `null` cuando no haya miembro relacionado o no se encuentre.
- Se mantendrá el formato de fecha existente en el proyecto, adaptándolo al campo JSON `time` requerido por el cliente.

## Preguntas abiertas
- La solicitud menciona `Infrastructure/Bootstrap/App.php` como lugar para instanciar repositorios, pero el servidor de producción construye actualmente Router y los repositorios en `home-backend/public/index.php`; `Bootstrap/App.php` solo recibe un repositorio de Tasks y se usa como wrapper. La implementación debe conectar Alerts en el punto real de arranque y decidir si también extiende el wrapper.
- No se especifica la acción referencial al borrar un miembro (`ON DELETE SET NULL`, `CASCADE` u otra), ni índices adicionales para la tabla `alerts`.
- La cadena exacta de formato del campo `time` no está definida; debe ajustarse al contrato que espera el cliente.

## Siguiente paso
Usar este brief como entrada para `orch-dev`, que elaborará el plan técnico, confirmará las decisiones abiertas y coordinará la implementación con los módulos de dominio y aplicación descritos en los briefs anteriores.
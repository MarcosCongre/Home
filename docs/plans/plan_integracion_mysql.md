# Plan de integración completa: frontend, backend y MySQL

## Objetivo

Dejar la aplicación Home Organization funcionando de extremo a extremo con React/Vite consumiendo una API PHP persistida en MySQL, tanto en entorno local como contra la base de datos alojada en DonWeb. El flujo final debe permitir:

- cargar y listar usuarios por hogar;
- crear, editar y eliminar usuarios;
- cargar y listar tareas por hogar;
- crear, editar y eliminar tareas;
- asignar y reasignar tareas a usuarios;
- completar tareas;
- conservar los cambios al reiniciar el backend;
- verificar conectividad, integridad de datos y comportamiento bajo carga.

## Estado verificado

- El frontend tiene clientes HTTP para listar/crear/editar/eliminar miembros en `src/infrastructure/http/memberApi.ts`.
- El frontend solo tiene cliente de listar/completar tareas en `src/infrastructure/http/taskApi.ts`; faltan crear, editar y eliminar.
- Los hooks `useMembers` y `useTasks` silencian errores usando datos demo, por lo que actualmente una API caída puede parecer una aplicación funcional.
- El backend tiene casos de uso y rutas de miembros, pero `InMemoryMemberRepository` no persiste datos.
- El backend tiene `PdoTaskRepository`, que crea una tabla SQLite directamente desde el constructor.
- `docker-compose.yml` configura SQLite y `Dockerfile` instala únicamente `pdo_sqlite`.
- `Router.php` y `App.php` contienen lógica de entrada paralela; debe existir un único bootstrap utilizado por las pruebas y por el servidor.
- No se observa todavía migración de esquema, contrato de errores HTTP uniforme ni configuración MySQL completa.

## Orden de ejecución

### 1. Fijar el contrato funcional y el modelo de datos

- **Prioridad:** Alta
- **Dependencias:** Ninguna
- **Complejidad:** M
- **Archivos/módulos:** `home-backend/src/Domain`, `home-backend/src/Application`, `src/domain`, `src/infrastructure/http`

Definir una fuente única de verdad para los campos y estados:

- `members`: `id`, `household_id`, `name`, `avatar`, `color`, `created_at`, `updated_at`.
- `tasks`: `id`, `household_id`, `title`, `status`, `assigned_member_id` nullable, `created_at`, `updated_at`, `completed_at` nullable.
- relación `tasks.assigned_member_id -> members.id`, con una política explícita al eliminar un miembro: rechazar si tiene tareas asignadas o dejar la tarea sin asignar. La opción recomendada para esta aplicación es dejarla sin asignar.
- IDs como UUID o cadenas generadas en backend, nunca confiando en IDs enviados por el navegador.
- Validaciones: hogar obligatorio, nombre/título no vacío, estado válido, miembro asignado perteneciente al mismo hogar.

**Criterio de aceptación:** los DTO del frontend y las respuestas JSON del backend describen los mismos nombres, tipos, estados y reglas de asignación.

### 2. Unificar el arranque HTTP y el manejo de respuestas

- **Prioridad:** Alta
- **Dependencias:** 1
- **Complejidad:** M
- **Archivos/módulos:** `home-backend/src/Infrastructure/Bootstrap/App.php`, `home-backend/src/Infrastructure/Http/Router.php`, `home-backend/src/Infrastructure/Http/*`, entrypoint PHP del backend

Elegir `Router` como punto de entrada único o adaptar `App` para delegar en él. Añadir:

- métodos HTTP completos para tareas y miembros;
- códigos `200/201/204/400/404/409/422/500` coherentes;
- respuestas JSON uniformes para éxito y error;
- cabeceras CORS para el origen local del frontend y el dominio publicado;
- manejo de `OPTIONS` para preflight;
- lectura de `php://input` sin depender de claves artificiales en `$_SERVER` fuera de las pruebas.

**Criterio de aceptación:** cada endpoint responde de forma idéntica en PHPUnit, servidor PHP local y Docker.

### 3. Sustituir repositorios en memoria/SQLite por persistencia MySQL

- **Prioridad:** Alta
- **Dependencias:** 1 y 2
- **Complejidad:** L
- **Archivos/módulos:** `home-backend/src/Infrastructure/Persistence`, `home-backend/src/Infrastructure/Config/Environment.php`, `home-backend/docker-compose.yml`, `home-backend/Dockerfile`, `home-backend/composer.json`

Implementar repositorios PDO para miembros y tareas usando consultas preparadas. No crear tablas desde constructores: incorporar migraciones versionadas, por ejemplo `home-backend/database/migrations/001_initial_schema.sql`.

La configuración debe admitir variables separadas o un DSN completo:

- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`;
- `DB_CHARSET=utf8mb4`;
- `APP_ENV`, `APP_DEBUG`, `CORS_ALLOWED_ORIGINS`.

Actualizar Docker para instalar `pdo_mysql` y añadir un servicio MySQL local con volumen persistente y healthcheck. Mantener SQLite solo como opción explícita de pruebas si aporta valor, no como backend accidental.

**Criterio de aceptación:** crear un usuario y una tarea, reiniciar el contenedor PHP y recuperar ambos registros desde MySQL sin datos demo.

### 4. Completar casos de uso y endpoints CRUD

- **Prioridad:** Alta
- **Dependencias:** 1, 2 y 3
- **Complejidad:** L
- **Archivos/módulos:** `home-backend/src/Application/Members`, `home-backend/src/Application/Tasks`, `home-backend/src/Infrastructure/Http/MemberController.php`, `home-backend/src/Infrastructure/Http/TaskController.php`, `home-backend/src/Infrastructure/Http/Router.php`

Exponer como mínimo:

| Método | Ruta | Uso |
|---|---|---|
| `GET` | `/members?householdId=...` | listar usuarios |
| `POST` | `/members` | crear usuario |
| `PATCH` | `/members/{id}` | editar usuario |
| `DELETE` | `/members/{id}` | eliminar usuario y desasignar tareas según política |
| `GET` | `/tasks?householdId=...` | listar tareas |
| `POST` | `/tasks` | crear tarea |
| `PATCH` | `/tasks/{id}` | editar título, estado o asignación |
| `DELETE` | `/tasks/{id}` | eliminar tarea |
| `PATCH` | `/tasks/{id}/complete` | completar tarea |

La respuesta de tarea debe incluir `assignedMemberId` y, si facilita la UI, un objeto `assignedMember` normalizado. La autorización por hogar debe aplicarse en cada operación, no solo en los listados.

**Criterio de aceptación:** una tarea solo puede asignarse a un usuario del mismo `householdId`; una asignación inválida devuelve `422` o `409` sin modificar datos.

### 5. Conectar completamente el frontend y retirar fallbacks silenciosos

- **Prioridad:** Alta
- **Dependencias:** 4
- **Complejidad:** L
- **Archivos/módulos:** `src/infrastructure/http/taskApi.ts`, `src/infrastructure/http/memberApi.ts`, `src/application/tasks/useTasks.ts`, `src/application/members/useMembers.ts`, `src/presentation/screens/homeScreens.tsx`, `src/infrastructure/config/environment.ts`

Añadir métodos de API y estados de carga/error para crear, editar, eliminar y asignar tareas. Reemplazar el comportamiento actual de “si falla, usar demo” por:

- datos demo únicamente en un modo explícito `VITE_DEMO_MODE=true`;
- estado visible de error y acción de reintento en modo conectado;
- actualización optimista solo cuando exista rollback fiable;
- recarga o actualización del registro devuelto por API tras cada mutación;
- configuración `VITE_API_BASE_URL` y `VITE_HOUSEHOLD_ID` documentada para local y producción.

**Criterio de aceptación:** la UI no muestra datos demo cuando la API está configurada; cada alta, edición, asignación y baja se refleja después de recargar el navegador.

### 6. Pruebas automatizadas de backend y contrato

- **Prioridad:** Alta
- **Dependencias:** 3 y 4
- **Complejidad:** L
- **Archivos/módulos:** `home-backend/tests/Unit`, `home-backend/tests/Integration`, nuevos tests de repositorio MySQL y API

Cubrir:

- reglas de dominio y validaciones;
- CRUD de miembros y tareas;
- asignación válida e inválida por hogar;
- eliminación de miembro con tareas asignadas;
- persistencia real contra MySQL de pruebas;
- códigos HTTP, JSON y CORS;
- reinicio del proceso sin pérdida de datos.

Preparar una base de datos de pruebas separada (`home_test`) y ejecutar migraciones antes de los tests. No usar la base de DonWeb para pruebas destructivas.

**Comandos previstos:**

```text
cd home-backend
composer install
php vendor/bin/phpunit
```

Añadir un comando específico documentado para integración MySQL, por ejemplo `composer test:integration`, condicionado a las variables de entorno de test.

### 7. Pruebas locales de conexión y carga de datos

- **Prioridad:** Alta
- **Dependencias:** 3, 4 y 6
- **Complejidad:** M
- **Archivos/módulos:** `home-backend/docker-compose.yml`, `home-backend/README.md`, `docs/dev-commands.md`, scripts de pruebas

Secuencia local:

1. levantar MySQL y PHP con Docker Compose;
2. ejecutar migraciones;
3. comprobar healthcheck de MySQL;
4. probar `GET /members` y `GET /tasks` con un hogar conocido;
5. crear usuarios;
6. crear tareas y asignarlas;
7. editar, completar y eliminar registros;
8. reiniciar servicios y repetir lecturas;
9. ejecutar pruebas de carga con datos controlados.

Para carga, comenzar con 100 usuarios y 1.000 tareas del mismo hogar, medir latencia p50/p95, errores y consumo de memoria. Después probar concurrencia de 10-20 clientes y comprobar que no hay duplicados ni pérdidas. Usar una herramienta reproducible como k6, Artillery o un script PHP/Node documentado.

**Criterio de aceptación inicial:** 0 errores funcionales en el flujo CRUD y tasa de error menor al 1% en la prueba de carga acordada.

### 8. Configurar y validar DonWeb

- **Prioridad:** Alta
- **Dependencias:** 3, 4, 6 y 7
- **Complejidad:** M
- **Archivos/módulos:** variables de entorno del servidor DonWeb, documentación de despliegue, configuración CORS y frontend

Solicitar/confirmar antes de desplegar:

- host o IP de MySQL;
- puerto, normalmente `3306`, si DonWeb usa otro;
- nombre de base, usuario y contraseña;
- si MySQL acepta conexiones desde el servidor PHP y desde qué IPs;
- certificado/SSL requerido;
- URL pública de la API y URL pública del frontend;
- versión de PHP y extensiones disponibles (`pdo_mysql`, `mbstring`, `json`, `openssl`);
- método disponible para ejecutar migraciones;
- límites de conexiones, memoria y tiempo de ejecución.

Crear las variables directamente en el panel o servidor de DonWeb. No subir `.env`, contraseñas ni dumps con credenciales. Ejecutar migraciones en la base remota, probar una operación de lectura y una escritura controlada, y verificar CORS desde el dominio real.

**Criterio de aceptación:** frontend publicado puede listar y mutar datos de DonWeb, con persistencia comprobada y sin secretos en Git.

### 9. Observabilidad y endurecimiento final

- **Prioridad:** Media
- **Dependencias:** 7 y 8
- **Complejidad:** M
- **Archivos/módulos:** backend HTTP/bootstrap, documentación y configuración de despliegue

Añadir logs sin secretos, identificador de petición, mensajes de error seguros, límites de payload, timeouts PDO, índices para `household_id` y `assigned_member_id`, y backups/verificación de restauración según las capacidades de DonWeb.

## Matriz de verificación final

- [ ] El backend arranca con MySQL local.
- [ ] La migración crea tablas e índices sin intervención manual adicional.
- [ ] `GET /members` y `GET /tasks` leen desde MySQL.
- [ ] Se pueden crear, editar y eliminar usuarios.
- [ ] Se pueden crear, editar, asignar, completar y eliminar tareas.
- [ ] No se permite asignar una tarea a un usuario de otro hogar.
- [ ] Los datos sobreviven al reinicio de PHP y MySQL.
- [ ] El frontend muestra errores de API y permite reintentar.
- [ ] PHPUnit pasa con repositorio en memoria y MySQL de integración.
- [ ] La prueba de carga tiene resultados registrados y reproducibles.
- [ ] DonWeb responde desde el frontend publicado.
- [ ] No hay credenciales en archivos versionados.

## Bloqueos y supuestos

- No se proporcionaron credenciales ni parámetros de conexión de DonWeb; deben configurarse como secretos fuera del repositorio.
- No se confirmó si DonWeb permite desplegar Docker. El plan asume que el backend puede ejecutarse como PHP/FPM o servidor PHP equivalente; Docker Compose se reserva para local.
- La política recomendada al eliminar un miembro es desasignar sus tareas, pero debe confirmarse como regla de negocio.
- Se asume un único `householdId` operativo en la UI actual; si habrá múltiples hogares o autenticación real, debe añadirse una fase de identidad/autorización antes de producción.

# T1 - Entorno, inventario y respaldo

Fecha de ejecución: 2026-09-23 (hora local observada)

## Checklist de entorno

| Componente | Resultado | Evidencia |
|---|---|---|
| PHP | BLOQUEADO | PHP 8.2.33 CLI; la plataforma objetivo es PHP 8.3.x |
| Composer | OK | Composer 2.8.12 |
| Node.js | OK | v22.15.0 |
| npm | OK | 10.9.2 |
| Docker | BLOQUEADO | `docker` no está instalado ni disponible en PATH |
| MariaDB local | OK | MariaDB 10.4.32 en `127.0.0.1:3306` |
| Frontend local | OK | HTTP 200 en `http://127.0.0.1:8443/` |
| Proceso en puerto 8000 | OBSERVACIÓN | HTTP 200, pero devuelve el shell HTML del proyecto; no se confirmó que sea la API PHP |

Puertos observados: `3306` (MariaDB), `8000` (PHP), `8443` (Node/Vite). No se observó un listener en `5173`.

## Configuración encontrada

- `home-backend/docker-compose.yml` define `DB_DATABASE=home` para `app`.
- El mismo compose inicializa MySQL con `MYSQL_DATABASE=home`.
- `home-backend/.env.example` usa `DB_DATABASE=home`.
- `home-backend/public/index.php` usa `home` como valor predeterminado, pero la conexión efectiva depende de variables de entorno del proceso PHP.
- La configuración de CORS declarada permite `http://localhost:5173` y `http://localhost:8443`.
- Docker no puede utilizarse en este equipo durante esta validación; se está usando la instancia MariaDB local.

## Inventario inicial no sensible

La conexión se realizó a `home` con el cliente local y sin modificar registros:

| Elemento | Resultado |
|---|---:|
| Base de datos `home` | 1 |
| Miembros | 5 |
| Tareas | 23 |
| Hogares en miembros | `hh_001` |
| Hogares en tareas | `hh_001` |
| Tareas `pending` | 23 |
| Tareas `completed` | 0 |

También se observó una diferencia entre el esquema vivo y la migración versionada: las tablas vivas usan IDs `INT`, mientras `home-backend/database/migrations/001_initial_schema.sql` declara IDs `VARCHAR(64)`.

## Acomodación implementada para IDs `INT`

La base `home` debe tratarse como la fuente de verdad y los identificadores de `members`, `tasks` y `assigned_member_id` deben representarse como enteros en todo el backend. `household_id` no debe cambiar: continúa siendo un identificador textual como `hh_001`.

La adaptación implementada es:

1. Cambiar los tipos de `id` y `assignedMemberId` en `Domain/Members/Member.php` y `Domain/Tasks/Task.php` de `string` a `int`/`?int`, incluidos constructores, factorías, getters y métodos de actualización.
2. Cambiar los comandos, handlers, interfaces de repositorio y controladores que reciben `memberId`, `taskId` o `assignedMemberId` para trabajar con `int`; los identificadores deben validarse como enteros positivos antes de invocar el dominio. Un ID ausente, decimal, negativo o alfanumérico debe producir un error HTTP controlado.
3. En `PdoTaskRepository.php` y `PdoMemberRepository.php`, convertir los valores leídos de PDO con `(int)` y enlazar los parámetros de ID con `PDO::PARAM_INT`. Los valores nulos de `assigned_member_id` deben conservarse como `null`.
4. Cambiar la tabla SQLite de prueba de `PdoTaskRepository` de `id TEXT` y `assigned_member_id TEXT` a `INTEGER`, para que las pruebas reproduzcan el contrato de MySQL. La migración `001_initial_schema.sql` debe actualizarse de forma equivalente: `id INT`, `assigned_member_id INT NULL` y la clave foránea con el mismo tipo.
5. Ajustar la serialización HTTP y el frontend para que `id` y `assignedMemberId` lleguen como números JSON. En los mappers de React se debe usar `Number(...)` solo después de validar el valor; no se debe convertir `householdId`.
6. Añadir pruebas de contrato y persistencia que comprueben lectura de los IDs actuales, creación con ID entero, relación tarea-miembro, rechazo de IDs inválidos y ausencia de mutaciones parciales.

El cambio se aplicó de forma coordinada en backend, migración, pruebas y frontend. Las entidades nuevas omiten el ID y reciben el valor `AUTO_INCREMENT` de MySQL; los repositorios en memoria simulan el mismo comportamiento. Los IDs devueltos por la API salen como enteros y `householdId` continúa siendo texto.

## Respaldo

- Archivo: `evidence/home-before-validation-20260923-0035.sql`
- Base respaldada: `home`
- Tamaño: 12,476 bytes
- SHA-256: `1C16F156B8CD0F9857A241D265A70E3C10FB8A1674D703CA4731B8025A706CCE`
- Método: `mysqldump --single-transaction --routines --events --triggers`
- Estado: generado y verificable antes de operaciones mutables.

## Resultado T1

T1 queda completada como diagnóstico y protección de datos. `home` es la fuente de verdad confirmada para esta validación y la adaptación de IDs `INT` quedó implementada. También debe confirmarse cómo se inyectan las variables al proceso PHP antes de ejecutar pruebas contra MySQL.

No se ejecutó ninguna operación mutable sobre la base `home`. La suite PHPUnit de T2 continúa bloqueada porque el CLI disponible es PHP 8.2.33 sin `openssl`; se requiere PHP 8.3.x con `openssl`, según [t2-suite-backend.md](t2-suite-backend.md).
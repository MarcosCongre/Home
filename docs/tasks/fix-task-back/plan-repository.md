# Plan: Persistir metadatos de Task en PdoTaskRepository

**Fecha**: 2026-10-01 | **Brief**: [brief-repository.md](brief-repository.md)

## Resumen

Actualizar `PdoTaskRepository` para que persista `day`, `time`, `category`, `recurrence` y `priority` en la base de datos SQLite interna y al reconstruir la entidad desde las filas. La tarea debe mantenerse acotada al repositorio y al esquema inline del mismo, sin tocar commands, handlers, controller ni migración principal ya aplicada.

## Contexto técnico

- **Repositorio actual**: `home-backend/src/Infrastructure/Persistence/PdoTaskRepository.php`
- **Esquema SQLite actual**: `id`, `title`, `status`, `household_id`, `assigned_member_id`, `created_at`, `completed_at`
- **Flujo actual**: `save()` decide si hace `UPDATE` o `INSERT`; `hydrateTask()` reconstruye la entidad desde la fila con el constructor actual de `Task`
- **Entidad objetivo**: `Task` debe exponer getters de los metadatos y aceptar esos campos en su constructor según el brief de dominio
- **Cobertura actual**: `home-backend/tests/Integration/Persistence/PdoTaskRepositoryTest.php` valida guardado y lectura base, sin metadatos

## Requisitos

- **REQ-001**: el `CREATE TABLE IF NOT EXISTS tasks` debe incluir las columnas `day`, `time`, `category`, `recurrence` y `priority` con tipado y default apropiados.
- **REQ-002**: `save()` debe incluir los nuevos campos en el `UPDATE` de la sentencia SQL.
- **REQ-003**: `save()` debe incluir las nuevas columnas y placeholders en ambos `INSERT` (con `id` explícito y sin `id`).
- **REQ-004**: los valores opcionales deben enlazarse como `PDO::PARAM_NULL` cuando sean `null`, y `priority` como texto.
- **REQ-005**: `hydrateTask()` debe recuperar estos metadatos desde la fila y reconstruir la entidad con los valores correctos, tratando `null` como nulo y cadenas vacías como vacías o `null` según la semántica planteada.
- **REQ-006**: la prioridad debe persistirse como valor por defecto `med` si no se especifica y no debe romper la compatibilidad con la entidad.
- **REQ-007**: no se reviste ni se vuelve a migrar una base de datos ajena a la SQLite interna; el enfoque es local al repositorio.

## Tareas

| ID | Tarea | Prioridad | Dependencias | Archivos afectados | Complejidad |
|---|---|---|---|---|---|
| T1 | Extender el esquema inline SQLite para añadir las columnas de `day`, `time`, `category`, `recurrence` y `priority` con tipos y defaults acordes al brief. | Alta | Ninguna | `home-backend/src/Infrastructure/Persistence/PdoTaskRepository.php` | Media |
| T2 | Ajustar `save()` para `UPDATE` con los cinco nuevos campos y enlazar nullables de forma correcta. | Alta | T1 | `home-backend/src/Infrastructure/Persistence/PdoTaskRepository.php` | Media |
| T3 | Ajustar los `INSERT` del repositorio para guardar los campos nuevos en ambos caminos (con y sin `id`). | Alta | T1 | `home-backend/src/Infrastructure/Persistence/PdoTaskRepository.php` | Media |
| T4 | Rehidratar la entidad desde la fila con `day`, `time`, `category`, `recurrence` y `priority`, respetando el manejo de `null` y `string`. | Alta | T1 | `home-backend/src/Infrastructure/Persistence/PdoTaskRepository.php` | Media |
| T5 | Añadir pruebas de integración para verificar la persistencia y la lectura de los metadatos. | Media | T1-T4 | `home-backend/tests/Integration/Persistence/PdoTaskRepositoryTest.php` | Media |
| T6 | Ejecutar la prueba de persistencia del repositorio para confirmar que la compatibilidad y la lectura de filas siguen funcionando. | Media | T1-T5 | `home-backend/tests/Integration/Persistence/PdoTaskRepositoryTest.php` | Baja |

## Pasos de implementación

### Paso 1: Esquema SQLite

- Modificar la creación inline de la tabla `tasks` en `PdoTaskRepository::__construct()`.
- Añadir:
  - `day TEXT NULL`
  - `time TEXT NULL`
  - `category TEXT NULL`
  - `recurrence TEXT NULL`
  - `priority TEXT NOT NULL DEFAULT 'med'`
- No crear una migración nueva ni alterar el esquema principal ya aplicado en la base de datos real; esta es solo la definición de la tabla de SQLite local utilizada por la aplicación.

### Paso 2: UPDATE en `save()`

- En la sentencia `UPDATE tasks SET ...`, agregar las cinco columnas.
- Enlazar cada valor con el tipo correcto:
  - `day`, `time`, `category`, `recurrence` => `PDO::PARAM_NULL` si son `null`, `PDO::PARAM_STR` si tienen valor
  - `priority` => `PDO::PARAM_STR` con valor por defecto `med` cuando venga vacío o `null`
- Mantener el resto de campos sin cambios y respetar el flujo actual de `status`, `assignedMemberId`, `createdAt` y `completedAt`.

### Paso 3: INSERT en `save()`

- Ampliar ambos `INSERT` del repositorio para incluir las columnas nuevas.
- Asegurar que el orden de `VALUES` y los placeholders coinciden con la lista de columnas.
- Usar el mismo patrón de enlazado que en el `UPDATE` para no introducir errores de parámetro o tipo.

### Paso 4: Hidratar `Task`

- En `hydrateTask()`, recuperar las columnas `day`, `time`, `category`, `recurrence` y `priority` desde `$row`.
- Convertir los valores a `string` solo cuando existan, y dejar `null` cuando la bd devuelva `null`.
- Construir `Task` con los valores derribados mediante el constructor final de la entidad, respetando la posición de los parámetros ya acordada en el brief de dominio.
- Mantener el tratamiento de `completedAt` y `assignedMemberId` igual al diseño actual.

### Paso 5: Validación de persistencia

- Extender `PdoTaskRepositoryTest` para crear una tarea con metadatos y verificar que:
  - `findById()` devuelve los valores esperados
  - `findByHouseholdId()` también reaprovecha esos valores
  - el valor por defecto de `priority` es `med` cuando no se especifica
  - los campos opcionales se conservan como `null` cuando no se suministran

## Criterios de aceptación

- La tabla `tasks` del SQLite inline incluye `day`, `time`, `category`, `recurrence` and `priority`.
- `save()` persiste los campos y `findById()` / `findByHouseholdId()` los rehidrata correctamente.
- Los campos opcionales quedan como `null` al persistir si no tienen valor.
- `priority` se guarda con valor `med` por defecto cuando no se indica.
- La entidad reconstruida mantiene los getters del brief de entidad sin romper la API existente.
- No se diseña ninguna migración ni cambio de esquema fuera del repositorio local.

## Validación

1. Ejecutar la prueba de integración de `PdoTaskRepository`.
2. Confirmar que la lectura y escritura de metadatos funcionan en SQLite con id explícito y sin id.
3. Registrar cualquier fallo ajeno al repositorio y no ampliar el alcance a controller o commands.

## Orden y dependencias

`T1 -> T2 -> T3 -> T4 -> T5 -> T6`

El orden sigue el flujo natural: primer schema, luego persistencia, luego hidratação y finalmente validación con tests.

## Suposiciones y decisiones pendientes

- Se asume que la entidad ya implementa los getters y constructor con estos nuevos campos; este plan no revisa la entidad ni la capa de dominio más allá del contrato de persistencia.
- Se asume que `priority` puede llegar como `null` si la entidad lo soporta, pero la base de datos debe guardar `med` por defecto para la fila si no llega valor explícito.
- Si un archivo SQLite ya existe y no tiene nuevas columnas, el `CREATE TABLE IF NOT EXISTS` no las añadirá; la corrección de ese caso requeriría un esquema de migración distinto y queda fuera de este brief.
- No se modifica la migración principal de la base de datos aplicada previamente.

## Ejecución

- Este trabajo debe estar orientado exclusivamente a `PdoTaskRepository` y a su SQLite inline.
- El objetivo es garantizar que la persistencia del metadata no falle al guardar ni al rehidratar, manteniendo la estructura actual del repositorio y sin introducir cambios ajenos al flujo de tareas.
- La tarea es un paso intermedio crítico: la entidad ya debe admitir los campos y la capa HTTP ya debe enviarlos; el repositorio debe asegurar que el dato no se pierda entre la capa de aplicación y la base de datos.

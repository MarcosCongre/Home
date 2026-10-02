# Brief: Persistir metadatos de Task en PdoTaskRepository

## Solicitud original
Actualizar `PdoTaskRepository` para guardar, enlazar y reconstruir `day`, `time`, `category`, `recurrence` y `priority`, además de definir esas columnas en la creación inline de la tabla SQLite.

## Objetivo
Mantener los cinco nuevos campos de la entidad al insertar, actualizar y volver a cargar tareas desde la base de datos.

## Enfoque propuesto
Extender las sentencias SQL de actualización y de ambas variantes de inserción con las nuevas columnas y parámetros. Enlazar los valores opcionales como `NULL` cuando corresponda, hidratar la entidad desde las filas consultadas con conversión a string y manejo de nulos, y ampliar el esquema SQLite inline con tipos y valores predeterminados acordes al modelo.

## Alcance
### Incluido
- Añadir `day`, `time`, `category`, `recurrence` y `priority` al `SET` de `UPDATE`.
- Añadir las cinco columnas y sus placeholders a los `INSERT` con ID explícito y sin ID.
- Enlazar los campos opcionales como `PDO::PARAM_NULL` cuando sean `null`; enlazar `priority` como `PDO::PARAM_STR`.
- Hidratar `Task` con los cinco campos desde `$row`, mediante casting a string y comprobaciones de nulidad.
- Añadir al `CREATE TABLE` SQLite inline `day TEXT NULL`, `time TEXT NULL`, `category TEXT NULL`, `recurrence TEXT NULL` y `priority TEXT NOT NULL DEFAULT 'med'`.

### Fuera de alcance
- Cambios en commands, handlers, controller o serialización HTTP.
- Actualización de esquemas de bases de datos ajenos al `CREATE TABLE` inline de SQLite.

## Supuestos
- La entidad `Task` ya expondrá los getters requeridos y aceptará los campos al construirse, conforme al brief de entidad.
- Los cuatro metadatos opcionales mantienen `null` en base de datos cuando no tienen valor; la prioridad siempre tiene un valor de texto y, si no se indica, usa `med`.
- La migración de la base de datos de la aplicación ya fue aplicada con `day VARCHAR(3) NULL`, `time VARCHAR(8) NULL`, `category VARCHAR(64) NULL`, `recurrence VARCHAR(32) NULL` y `priority VARCHAR(8) NOT NULL DEFAULT 'med'`. El repositorio debe usar esos nombres de columna; no hace falta crear otra migración para esa base.
- La definición SQLite inline es un esquema de creación local independiente de la migración ya aplicada a la base de datos principal.

## Preguntas abiertas
- Si la aplicación utiliza un archivo SQLite ya creado antes de este cambio, `CREATE TABLE IF NOT EXISTS` no añadirá columnas a esa tabla existente; ese archivo requeriría una actualización de esquema por separado. Esto no afecta a la base cuya migración ya se confirmó aplicada.

## Siguiente paso
Este brief queda como entrada para elaborar el plan técnico de implementación del repositorio, sin volver a migrar la base de datos que ya está actualizada.
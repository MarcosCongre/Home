# Plan: Verificar persistencia de los metadatos de Task

**Fecha**: 2026-10-01 | **Brief**: [brief-verificacion.md](brief-verificacion.md)

## Resumen

Validar de extremo a extremo que los metadatos `day`, `time`, `category`, `recurrence` y `priority` sobreviven al flujo HTTP de creación y lectura de tareas. La verificación se debe ejecutar contra el backend real servido con PHP y no introduce cambios de implementación ni una nueva migración; solo confirma que la base ya actualizada responde con los nuevos campos en JSON.

## Contexto técnico

- **Servidor actual**: `home-backend/public/index.php` y el `Router` de `src/Infrastructure/Http/Router.php`
- **Endpoint de tareas**: `POST /tasks` y `GET /tasks?householdId=...`
- **Base de datos real**: la migración ya aplicada define las columnas `day`, `time`, `category`, `recurrence` y `priority`
- **Objetivo funcional**: crear una tarea con metadatos y confirmar que el listado posterior los devuelve exactamente en el JSON
- **Cobertura actual**: existen pruebas unitarias e integradas del flujo de tareas, pero la verificación solicitada es una prueba manual de extremo a extremo con el servidor PHP real

## Requisitos

- **REQ-001**: arrancar el backend PHP en el puerto esperado para atender `POST /tasks` y `GET /tasks`.
- **REQ-002**: crear una tarea con el payload de prueba: `title`, `householdId`, `day`, `time`, `category`, `recurrence` y `priority`.
- **REQ-003**: comprobar que la respuesta de creación incluye el ID y que la tarea queda persistida con los metadatos correctos.
- **REQ-004**: consultar `GET /tasks?householdId=hh_001` y confirmar que los campos devueltos coinciden con los enviados.
- **REQ-005**: la verificación debe excluir cualquier cambio de esquema o migración nueva, porque esa parte ya está aplicada.
- **REQ-006**: si la comprobación falla, se documentará como un problema funcional y no se ampliará el alcance del plan a implementación adicional sin más evidencia.

## Tareas

| ID | Tarea | Prioridad | Dependencias | Archivos afectados | Complejidad |
|---|---|---|---|---|---|
| T1 | Reiniciar o asegurar que el servidor PHP del backend está levantado y escuchando en el puerto 8000. | Alta | Ninguna | `home-backend/public/index.php`, entorno local | Baja |
| T2 | Ejecutar una creación de prueba con `POST /tasks` usando el payload con metadatos. | Alta | T1 | Backend local | Media |
| T3 | Capturar la respuesta y validar el identificador devuelto y el estado inicial. | Alta | T2 | Backend local | Baja |
| T4 | Ejecutar `GET /tasks?householdId=hh_001` y localizar la tarea creada por id o por payload. | Alta | T2 | Backend local | Baja |
| T5 | Confirmar que el JSON devuelto contiene `day`, `time`, `category`, `recurrence` y `priority` con los valores exactos. | Alta | T3, T4 | Backend local | Media |
| T6 | Registrar el resultado de la verificación y cerrar el procedimiento si todos los campos coinciden. | Media | T1-T5 | Documento de evidencia / registro local | Baja |

## Pasos de implementación

### Paso 1: Arranque del backend

- Ejecutar el servidor PHP desde `home-backend` con el comando recomendado:
  - `php -S 127.0.0.1:8000 -t public`
- Confirmar que el proceso queda activo y que el puerto 8000 responde.
- No reintroducir migraciones ni cambios de base de datos; el objetivo es probar la base ya actualizada.

### Paso 2: Petición POST de creación

- Enviar a `http://localhost:8000/tasks` una petición con `Content-Type: application/json` y el siguiente payload:

```json
{
  "title": "Prueba",
  "householdId": "hh_001",
  "day": "Wed",
  "time": "18:30",
  "category": "cleaning",
  "recurrence": "weekly",
  "priority": "high"
}
```

- Verificar que el servidor responde con la tarea creada y un identificador válido.
- Confirmar que el `title` y el `householdId` siguen siendo correctos.

### Paso 3: Consulta GET para la misma tarea

- Llamar a `GET http://localhost:8000/tasks?householdId=hh_001`.
- Localizar la tarea creada usando el `id` devuelto en la respuesta POST o comparando `title` y `householdId`.
- Revisar el JSON completo del elemento encontrado.

### Paso 4: Validación del payload persistido

- Comprobar que aparecen exactamente:
  - `day: "Wed"`
  - `time: "18:30"`
  - `category: "cleaning"`
  - `recurrence: "weekly"`
  - `priority: "high"`
- Confirmar que el resto del JSON permanece consistente con la tarea creada.

### Paso 5: Registro de evidencia

- Guardar el resultado de la verificación en un documento o registro local de evidencia.
- Si la comprobación pasa, cerrar la validación con un resultado positivo y una captura del JSON devuelto.
- Si falla, documentar qué valor no coincide y cambiar el alcance para la corrección de código, no para la migración.

## Criterios de aceptación

- El backend PHP responde en `localhost:8000`.
- `POST /tasks` acepta el payload con metadatos y devuelve la tarea creada.
- `GET /tasks?householdId=hh_001` devuelve la tarea con los cinco campos y valores esperados.
- Los metadatos no se pierden en el ciclo de persistencia y lectura.
- La verificación no exige re-ejecutar migraciones ni tocar esquema.

## Validación

1. Ejecutar el servidor PHP en modo local con el backend de `home-backend`.
2. Enviar la petición de creación con los metadatos.
3. Ejecutar la consulta de listado por `householdId`.
4. Confirmar que el JSON final incluye `day`, `time`, `category`, `recurrence` y `priority` con los valores esperados.
5. Registrar evidencia del resultado final.

## Orden y dependencias

`T1 -> T2 -> T3 -> T4 -> T5 -> T6`

La determinación depende del servidor real y del flujo HTTP real, no de mocks ni pruebas unitarias. La validación no tiene precedencia de código más allá de tener la base ya actualizada y la funcionalidad completa implementada.

## Suposiciones y decisiones pendientes

- La base de datos ya está migrada con las columnas apropiadas y no requiere ninguna operación adicional.
- El entorno local del proyecto tiene acceso a PHP y el servidor puede levantarse sin depender de contenedores ni cambios de configuración.
- El objetivo de la verificación es confirmar persistencia real, no simularla con código unitario.
- Si la petición devuelve un 400 o si el JSON no incluye los campos, el problema debe tratarse como fallo funcional del flujo implementado y no como una prueba inválida.

## Ejecución

- La verificación comienza cuando el backend está corriendo en modo local.
- El procedimiento debe ejecutarse exactamente como se indica en el brief: crear con `POST /tasks` y consultar con `GET /tasks?householdId=hh_001`.
- Este plan sirve como comprobación final del ciclo completo de datos para la tarea en backend y no debe ampliarse a cambios de infraestructura.

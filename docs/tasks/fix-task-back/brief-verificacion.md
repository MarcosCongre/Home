# Brief: Verificar persistencia de los metadatos de Task

## Solicitud original
Con la migración de la tabla `tasks` ya aplicada, reiniciar el servidor PHP, crear una tarea de prueba mediante `POST /tasks` con los nuevos metadatos y comprobar que `GET /tasks?householdId=hh_001` los devuelve en el JSON.

## Objetivo
Confirmar mediante una prueba HTTP de extremo a extremo que los cinco metadatos sobreviven al ciclo de creación, persistencia y lectura de tareas.

## Enfoque propuesto
No volver a aplicar la migración: ya está ejecutada y define las columnas `day`, `time`, `category`, `recurrence` y `priority`. Reiniciar el servidor con `php -S 127.0.0.1:8000 -t public`, enviar la solicitud POST especificada y luego consultar las tareas del mismo hogar; verificar que la tarea creada devuelve los valores esperados.

## Alcance
### Incluido
- Enviar `POST http://localhost:8000/tasks` con `Content-Type: application/json` y este payload:

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

- Consultar después `GET http://localhost:8000/tasks?householdId=hh_001`.
- Confirmar que la tarea devuelta incluye `day: "Wed"`, `time: "18:30"`, `category: "cleaning"`, `recurrence: "weekly"` y `priority: "high"`.

### Fuera de alcance
- Cambios de implementación o ejecución de otra migración sobre la base ya actualizada.
- Automatizar esta comprobación como prueba de integración.

## Supuestos
- La migración ya aplicada creó las columnas con estos tipos: `day VARCHAR(3) NULL`, `time VARCHAR(8) NULL`, `category VARCHAR(64) NULL`, `recurrence VARCHAR(32) NULL` y `priority VARCHAR(8) NOT NULL DEFAULT 'med'`.
- El servidor está disponible en el puerto `8000` y el endpoint `/tasks` responde a POST y GET.
- La verificación identifica la tarea creada por sus valores o por el identificador devuelto en la respuesta POST, sin depender del orden de la lista.

## Siguiente paso
Este brief queda como entrada para ejecutar o automatizar la verificación tras completar los cambios de entidad, aplicación, controller y repositorio.
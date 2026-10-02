# Verificación de metadatos de Task en backend real

Fecha: 2026-10-01

## Resultado

La verificación de extremo a extremo del flujo HTTP del backend se realizó correctamente con el servidor PHP local levantado en `127.0.0.1:8000`.

## Petición POST /tasks

Payload enviado:

```json
{
  "title": "Prueba",
  "householdId": "hh_001",
  "userId": "u1",
  "day": "Wed",
  "time": "18:30",
  "category": "cleaning",
  "recurrence": "weekly",
  "priority": "high"
}
```

Respuesta recibida:

```json
{
  "id": 48,
  "title": "Prueba",
  "status": "pending",
  "householdId": "hh_001",
  "createdAt": "2026-10-02T02:35:01+00:00",
  "completedAt": null,
  "assignedMemberId": null,
  "day": "Wed",
  "time": "18:30",
  "category": "cleaning",
  "recurrence": "weekly",
  "priority": "high"
}
```

## Petición GET /tasks?householdId=hh_001

Respuesta recibida para la tarea con id 48:

```json
{
  "id": 48,
  "title": "Prueba",
  "status": "pending",
  "householdId": "hh_001",
  "createdAt": "2026-10-02T02:35:01+00:00",
  "completedAt": null,
  "assignedMemberId": null,
  "day": "Wed",
  "time": "18:30",
  "category": "cleaning",
  "recurrence": "weekly",
  "priority": "high"
}
```

## Conclusión

La persistencia y la lectura de los metadatos `day`, `time`, `category`, `recurrence` y `priority` funcionan correctamente en el flujo HTTP real del backend.

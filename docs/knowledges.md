# Knowledges — Modelo y componentes

## Propósito

Documentar la estructura real del proyecto para que nuevos desarrolladores comprendan tanto la capa de interfaz como la capa de dominio del backend.

## Modelo de datos del frontend

Los tipos principales de la app están definidos en `src/App.tsx`:

```ts
type User = {
  id: string
  name: string
  avatar: string
  color: string
}

type Task = {
  id: string
  title: string
  assignee: string
  category: string
  recurrence: string
  done: boolean
  day?: string
  time?: string
  priority: 'low' | 'med' | 'high'
  history: string[]
}
```

### Datos iniciales

- `INIT_USERS`: lista fija de miembros del hogar.
- `INIT_TASKS`: conjunto de tareas diarias/semanales con categoría, prioridad y fecha de repetición.
- `NOTIFS`: notificaciones urgentes y normales para la pantalla de alerts.

### Ajustes de dominio visual

- `DAYS`: `['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']`
- `CATEGORIES`: categorías de tarea disponibles.
- `RECURRENCES`: valores de recurrencia para edición de tarea.
- `categoryColor`: mapa de categoría a color.

## Componentes clave del frontend

### Dashboard

Responsable de:

- mostrar métricas del hogar;
- calcular tareas completadas/pendientes;
- filtrar por categoría;
- alternar el estado `done` de cada tarea;
- abrir el detalle de una tarea.

### CalendarView

- muestra tareas agrupadas por día semanales;
- permite filtrar por usuario;
- ofrece navegación rápida entre días.

### Notifications

- renderiza alertas por urgencia;
- permite marcar todas como leídas;
- usa un arreglo estático para simular eventos del hogar.

### TaskDetail

- permite editar `title`, `assignee`, `recurrence` y `priority`;
- guarda el cambio en el estado `tasks` local;
- conserva el historial `history` para cada tarea.

### Members

- muestra estadísticas del usuario;
- permite añadir, editar y borrar miembros;
- reasigna tareas a un miembro inexistente con `assignee: ''` si se elimina.

## Modelo del backend

La capa de dominio principal es `Task`:

```php
class Task {
    private string $id;
    private TaskTitle $title;
    private TaskStatus $status;
    private string $householdId;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $completedAt;
}
```

### Reglas mínimas de negocio

- una tarea debe tener un título válido;
- solo puede completarse si no está ya completada;
- la colección está asociada a un `householdId`;
- la persistencia y la API pueden ser intercambiables sin tocar la lógica de dominio.

## Convenciones de estilo y estructura

- Tailwind CSS v4 con utilidades en JSX.
- uso de colores cálidos para la identidad visual del producto.
- `src/index.css` centraliza la tipografía y tokens visuales.
- el backend sigue arquitectura por capas y un repositorio base para acceder al dato.

## Referencias reales

- [src/App.tsx](../src/App.tsx)
- [src/index.css](../src/index.css)
- [home-backend/src/Domain/Tasks/Task.php](../home-backend/src/Domain/Tasks/Task.php)
- [home-backend/src/Application/Tasks/CreateTask/CreateTaskHandler.php](../home-backend/src/Application/Tasks/CreateTask/CreateTaskHandler.php)
- [home-backend/src/Infrastructure/Persistence/PdoTaskRepository.php](../home-backend/src/Infrastructure/Persistence/PdoTaskRepository.php)

# Architecture — Visión general

## Resumen

El proyecto se compone de dos capas principales que trabajan en paralelo:

- un frontend React para la experiencia de usuario, con un diseño de app doméstica para móvil;
- un backend PHP orientado a dominio con casos de uso para creación, listado y cierre de tareas.

La estructura actual está pensada para ser clara, legible y extensible, sin introducir complejidad innecesaria en la demo inicial.

## Capa frontend

### Stack

- React 19
- Vite 8
- TypeScript 5.7
- Tailwind CSS v4
- Figma Make plugins en `vite.config.ts`

### Responsabilidades

- renderizar el dashboard de tareas;
- gestionar filtros por categoría, usuario y día;
- ofrecer la navegación entre Home, Calendar, Alerts y Members;
- mantener el estado de usuarios y tareas localmente con `useState`; 
- simular la lógica de negocio con datos pseudo-realistas.

### Estructura funcional

```text
src/
├── main.tsx           # bootstrap de React
├── App.tsx            # app completa con todas las pantallas
├── index.css          # import de Tailwind y tokens de diseño
└── vite-env.d.ts      # tipos Vite
```

El archivo `src/App.tsx` concentra los componentes principales: `Dashboard`, `CalendarView`, `Notifications`, `TaskDetail`, `Members`, `MemberModal`, `ConfirmDelete` y la navegación por tabs.

## Capa backend

### Stack

- PHP 8.2+
- Composer
- PHPUnit
- SQLite para persistencia local (PDO) y repositorio en memoria para pruebas

### Arquitectura interna

```text
home-backend/src
├── Domain/
│   ├── Tasks/
│   │   ├── Task.php
│   │   ├── TaskRepositoryInterface.php
│   │   ├── TaskStatus.php
│   │   └── TaskTitle.php
│   └── ...
├── Application/
│   └── Tasks/
│       ├── CreateTask/
│       ├── CompleteTask/
│       └── ListTasks/
├── Infrastructure/
│   ├── Bootstrap/
│   ├── Http/
│   ├── Persistence/
│   └── Security/
└── tests/
```

### Patrón actual

La lógica del negocio vive en el dominio. Los casos de uso residen en Application y las integraciones con HTTP y persistencia quedan en Infrastructure. Esto permite que `Task` sea una entidad independiente con estado y reglas básicas, mientras el repositorio y el controlador solo la adaptan al entorno técnico.

## Flujos de negocio

### Frontend

1. El usuario entra al dashboard y ve tareas cargadas por semana.
2. Marca tareas como hechas y actualiza el progreso.
3. Navega al calendario para ver tareas por día.
4. Consulta alertas y las puede cerrar.
5. Gestiona miembros y asignaciones.

### Backend

1. `POST /tasks` crea una tarea nueva.
2. `GET /tasks?householdId=...` lista tareas por hogar.
3. `PATCH /tasks/{id}/complete` marca la tarea como completada.

Los handlers usan repositorios para guardar y recuperar `Task`, y la capa HTTP convierte payload JSON a objetos del dominio.

## Observaciones de diseño

- El frontend está pensado para una experiencia visual compacta; no hay estado global ni librerías adicionales.
- El backend ya demuestra una separación útil entre dominio y periferia.
- El proyecto es perfectamente extensible hacia un backend real y una UI conectada a API.

## Referencias

- [vite.config.ts](../vite.config.ts)
- [src/App.tsx](../src/App.tsx)
- [src/index.css](../src/index.css)
- [home-backend/src/Infrastructure/Bootstrap/App.php](../home-backend/src/Infrastructure/Bootstrap/App.php)
- [home-backend/src/Infrastructure/Http/TaskController.php](../home-backend/src/Infrastructure/Http/TaskController.php)
- [home-backend/src/Domain/Tasks/Task.php](../home-backend/src/Domain/Tasks/Task.php)


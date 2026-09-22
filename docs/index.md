# Proyecto: Home organization app

## Resumen

Este repositorio combina dos capas funcionales:

- una interfaz móvil-style en React + Vite + Tailwind para gestionar tareas del hogar;
- un backend en PHP con una estructura de dominio/aplicación/infraestructura para servir la lógica de tareas a través de una API simple.

La parte frontend está orientada como prototipo de aplicación doméstica con dashboard, calendario, alertas y gestión de miembros. La parte backend ya incorpora una base de dominio clara con `Task`, repositorios, handlers y controladores HTTP.

## Objetivo del repositorio

- modelar la organización doméstica como flujo de tareas por persona y por categoría;
- permitir visualizar progresos del hogar y del usuario;
- mantener una estructura tecnológica sencilla para demo y evolución futura;
- dejar una base de backend con separación de responsabilidades para integración real con la UI.

## Alcance actual

### Frontend

- SPA de una sola pantalla con tabs: Home, Calendar, Alerts y Members.
- Estado local con React `useState` para tareas, usuarios y notificaciones.
- Datos iniciales cargados directamente en `src/App.tsx` para simular la app sin backend externo.
- Diseño centrado en una tablet/phone mock con paleta cálida y componentes compactos.

### Backend

- directorio `home-backend/` con enfoque hexagonal;
- dominio: `Task`, `TaskTitle`, `TaskStatus`, `TaskRepositoryInterface`;
- aplicación: `CreateTaskHandler`, `CompleteTaskHandler`, `ListTasksHandler`;
- infraestructura: `App`, `Router`, `TaskController`, `PdoTaskRepository` e `InMemoryTaskRepository`.

## Estructura principal

```text
.
├── src/                     # frontend React + Vite
├── home-backend/            # backend PHP
├── docs/                    # artefactos de arquitectura y uso
├── public/ or index.html    # shell Vite
├── package.json             # scripts del frontend
├── vite.config.ts           # configuración Vite + Tailwind + Figma Make
├── README.md                # documentación rápida
└── pnpm-lock.yaml           # lockfile de paquetes
```

## Documentación disponible

- [docs/architecture.md](architecture.md) — visión general de la arquitectura del proyecto.
- [docs/knowledges.md](knowledges.md) — modelo de dominio y componentes clave.
- [docs/settings.md](settings.md) — entorno y configuración.
- [docs/usage.md](usage.md) — flujos de desarrollo y ejecución.
- [docs/dev-commands.md](dev-commands.md) — scripts y comandos prácticos.
- [docs/plan_front/plan.md](plan_front/plan.md) — plan del frontend.
- [docs/plan_back/plan.md](plan_back/plan.md) — plan del backend.

## Flujos principales

1. El usuario consulta el dashboard con tareas pendientes y completadas.
2. Puede filtrar por categoría y marcar tareas como hechas.
3. Desde calendario accede a tareas por día y por miembro.
4. Desde alerts manejan avisos urgentes y no urgentes.
5. Desde members administra integrantes del hogar y sus estadísticas.
6. El backend ofrece un nivel de API capaz de crear, listar y cerrar tareas por identificador.

## Comandos rápidos

```bash
# frontend
npm install
npm run dev
npm run build

# backend
cd home-backend
composer install
composer test
php -S 0.0.0.0:8000 -t .
```

## Referencias clave

- [src/App.tsx](../src/App.tsx)
- [src/index.css](../src/index.css)
- [vite.config.ts](../vite.config.ts)
- [home-backend/src/Infrastructure/Bootstrap/App.php](../home-backend/src/Infrastructure/Bootstrap/App.php)
- [home-backend/src/Infrastructure/Http/TaskController.php](../home-backend/src/Infrastructure/Http/TaskController.php)

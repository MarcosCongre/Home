# Plan frontend — Home organization app

## Objetivo

Construir una aplicación doméstica visual, clara y compacta que permita:

- ver tareas del hogar;
- completar y filtrar tareas;
- organizar tareas por día y por miembro;
- revisar notificaciones y gestión de usuarios.

## Estado actual

El frontend ya existe y cubre la mayor parte del flujo principal:

- dashboard con métricas y filtros;
- vista de calendario por días;
- alerts con mensajes urgentes y no urgentes;
- detalle de tarea editable;
- gestión de miembros con modales de crear/editar/eliminar;
- navegación por tabs con diseño móvil.

## Fase 1 — Base de la interfaz

- [x] establecer estructura de páginas y estado local;
- [x] preparar diseño visual con Tailwind y tokens de color;
- [x] definir pantalla principal del dashboard;
- [x] crear palette de colores y tipografía de marca.

## Fase 2 — Lógica de tareas

- [x] crear tareas iniciales con categorías y reiteración;
- [x] permitir completar/incompletar tareas;
- [x] filtrar tareas por categoría;
- [x] mostrar historial de completado por tarea.

## Fase 3 — Organización por calendario y usuarios

- [x] agrupar tareas por día de la semana;
- [x] filtrar tareas por persona;
- [x] visualizar progreso de cada miembro;
- [x] permitir añadir y editar usuarios.

## Fase 4 — UX y alertas

- [x] presentar notificaciones con prioridad;
- [x] allow clear all dismiss;
- [x] mostrar contadores de alertas pendientes;
- [x] diseño compacto para mobile mock.

## Fase 5 — Integración con backend

- [x] conectar frontend con API PHP real;
- [x] cargar tareas desde endpoint `/tasks`;
- [ ] enviar creación y actualización a backend;
- [ ] mapear estados y pertenencias de usuarios con entidad real.

> Estado actual: la capa de tareas ya se comunica con la API real mediante `listTasks` y `completeTask`, pero la gestión de miembros y la sincronización de creación/edición aún están en estado local y requieren continuidad con el backend.

## Fase 6 — Refactor de arquitectura y escalabilidad

- [ ] separar la pantalla principal en feature modules (`tasks`, `members`, `dashboard`);
- [ ] extraer componentes reutilizables a `src/presentation/` y reorganizar props por feature;
- [ ] centralizar la lógica de negocio en hooks y services por dominio;
- [ ] definir una capa de contratos para creación/edición de tareas y usuarios;
- [ ] preparar validación de tipo y smoke checks antes de continuar con persistencia real.

> Estado de continuidad: la base visual y de negocio ya está resuelta; ahora el siguiente bloque es refinar la arquitectura para evitar que el App siga creciendo, manteniendo la UI actual estable mientras se escalan las features.

## Recomendaciones futuras

- extraer componentes en `src/components/`;
- centralizar datos y hooks en `src/features/` o `src/hooks/`;
- crear servicio API para `fetch`/`axios`;
- añadir persistencia real en localStorage o backend persistente.

## Criterio de cierre

La fase frontend estará completa cuando la experiencia visual y la lógica de negocio puedan ejecutarse sin depender de datos mock estáticos, y el flujo de tareas se conecte de manera fiable a la API del backend.

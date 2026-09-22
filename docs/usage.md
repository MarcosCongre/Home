# Usage — Cómo trabajar con el proyecto

## Instalación del frontend

Desde la raíz del proyecto:

```bash
npm install
# o
pnpm install
```

## Ejecutar la app frontend

```bash
npm run dev
```

La app se sirve por defecto en `http://localhost:8443` o en la dirección asignada por Vite dependiendo del entorno.

## Construcción de producción

```bash
npm run build
npm run preview
```

Esto genera la carpeta `dist/` y permite comprobar el bundle final antes de despliegue.

## Formateo

```bash
npm run format
```

## Ejecutar el backend PHP

Desde la carpeta `home-backend/`:

```bash
composer install
php -S 0.0.0.0:8000 -t .
```

También puedes levantar el servicio con Docker:

```bash
docker compose -f home-backend/docker-compose.yml up --build
```

## Flujo recomendado

1. arrancar frontend para validar UI;
2. usar el backend para comprobar endpoints de tareas;
3. actualizar datos del flujo real con `init` en front-end o API real;
4. ejecutar `npm run build` antes de despachar cambios.

## Casos de uso reales contemplados

- crear una tarea desde la API;
- listar tareas por hogar;
- completar una tarea;
- validar la UI con tareas completadas, pendientes y miembros distintos.

## Depuración rápida

- si hay un problema de Puerto 8443, revisa variables `PORT`;
- si el backend falla, revisa `home-backend/docker-compose.yml` y `composer.json`;
- si no responde la API, verifica los métodos HTTP y el `PATH_INFO` de la solicitud.

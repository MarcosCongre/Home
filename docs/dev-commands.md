# Dev Commands — Scripts y comandos del proyecto

## Frontend

El archivo [package.json](../package.json) define estos scripts:

- `npm run dev` — inicia Vite en modo desarrollo con hot reload.
- `npm run build` — genera la build de producción en `dist/`.
- `npm run preview` — sirve la build para inspección local.
- `npm run format` — ejecuta `oxfmt` para formatear archivos.

### Ejemplo

```bash
npm run dev
npm run build
npm run preview
```

## Backend

En `home-backend/` el proyecto usa Composer:

```bash
composer install
composer test
```

También puede ejecutarse con PHP interno:

```bash
php -S 0.0.0.0:8000 -t .
```

## Docker

```bash
docker compose -f home-backend/docker-compose.yml up --build
```

## Workflow recomendado

1. validar frontend con `npm run dev`;
2. compilar con `npm run build`;
3. validar API del backend con `composer test`;
4. usar Docker para reproducción del entorno real cuando haga falta.

## Observación

La app no incluye lint ni type-check en scripts todavía, por lo que la validación real de calidad depende más de build y revisión manual del comportamiento.

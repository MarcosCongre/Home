# Settings — Variables de entorno y configuración

## Frontend

### Variables relevantes

- `PORT`: puerto del servidor Vite. Por defecto es `8443`.
- `FIGMA_PUBLIC_URL`: base pública usada para despliegues bajo URL específica.
- `FIGMA_DEV_SERVER_HOST`: host del servidor para previews remotos.

### Archivos centrales

- [vite.config.ts](../vite.config.ts) — define plugins, alias `@`, host y puerto.
- [src/index.css](../src/index.css) — tokens visuales y fuente principal.
- [package.json](../package.json) — scripts para desarrollo y build.

### Consideraciones

- Vite se configura con `strictPort: true`, por lo que si el puerto `8443` está ocupado, la app no cambia de puerto automáticamente.
- Los plugins de Figma Make permiten adaptar la app para previews y despliegues especiales.

## Backend

El backend PHP no requiere un `.env` formal en esta base actual, pero sí tiene un patrón de configuración implícito:

- `APP_ENV`: entorno de ejecución (`development`, `production`).
- `APP_DEBUG`: habilita depuración.
- `DB_DSN`: cadena de conexión, por ejemplo `sqlite:/tmp/home-backend.sqlite`.
- `JWT_SECRET`: secreto para autenticación si se habilita más adelante.

Estos valores aparecen en [home-backend/docker-compose.yml](../home-backend/docker-compose.yml).

## Ejemplos de uso

### Bash

```bash
export PORT=8443
export FIGMA_PUBLIC_URL=https://my-preview.example.com
npm run dev
```

### PowerShell

```powershell
$env:PORT=8443
$env:FIGMA_PUBLIC_URL='https://my-preview.example.com'
npm run dev
```

### Backend local con Docker

```bash
docker compose -f home-backend/docker-compose.yml up --build
```

## Recomendaciones

- mantener un `.env.example` para el backend cuando se conecte a un entorno real;
- centralizar variables globales y no hardcodear URLs o secretos;
- revisar configuración de `vite.config.ts` antes de desplegar a un host externo.

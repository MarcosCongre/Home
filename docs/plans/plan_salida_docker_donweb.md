# Plan de retirada de Docker y despliegue en DonWeb

## Objetivo

Eliminar Docker del proyecto y dejar un procedimiento reproducible para alojar la aplicación en DonWeb usando:

- frontend React/Vite compilado como archivos estaticos;
- backend PHP 8.3 ejecutado por el servidor web del hosting;
- base de datos MySQL de DonWeb;
- configuracion de produccion separada de la configuracion local;
- secretos y credenciales fuera del repositorio.

El objetivo no es cambiar el contrato funcional de la API ni la estructura de los datos salvo lo necesario para adaptar el despliegue.

## Diagnostico confirmado

- `home-backend/Dockerfile` instala PHP 8.3 CLI y arranca el servidor interno de PHP en el puerto `8000`.
- `home-backend/docker-compose.yml` crea dos servicios locales: `app` y `mysql`.
- El backend usa por defecto `DB_HOST=mysql`, credenciales `home` y puerto `3306`, valores validos para Compose pero no para DonWeb.
- El frontend usa `/api` por defecto y Vite solo resuelve esa ruta mediante el proxy de desarrollo hacia `localhost:8000`.
- `VITE_API_BASE_URL` ya permite configurar una URL de API durante `npm run build`.
- La migracion `home-backend/database/migrations/001_initial_schema.sql` puede ejecutarse sobre MySQL, pero debe importarse de forma controlada en la base de DonWeb.
- La validacion PHP 8.3 y PHPUnit ya esta documentada; la validacion Docker quedo pendiente porque Docker no esta disponible en el entorno actual.

## Plan de tareas

### T0 - Preparar y validar el entorno local sin Docker

- **Prioridad:** Alta
- **Dependencias:** Ninguna
- **Complejidad:** M
- **Archivos/modulos:** `home-backend/composer.json`, `home-backend/composer.lock`, `home-backend/database/migrations/001_initial_schema.sql`, `home-backend/public/`, `src/`, `package.json`, entorno local

Completar esta fase antes de iniciar el despliegue en DonWeb. Preparar en el equipo local:

- PHP 8.3 con `openssl`, `pdo_mysql` y las extensiones requeridas;
- Composer;
- MySQL o MariaDB local;
- Node.js y npm.

Crear o verificar la base de datos local e importar `home-backend/database/migrations/001_initial_schema.sql`. Configurar las variables del backend para usar `127.0.0.1` y las credenciales locales, sin reutilizar las credenciales de Docker.

Desde `home-backend`, instalar dependencias y ejecutar la suite:

```text
composer install
composer test
php -S 127.0.0.1:8000 -t public
```

En otra terminal, desde la raiz del proyecto, instalar y arrancar el frontend:

```text
npm install
npm run dev
```

**Criterio de salida:** PHP y Composer funcionan sin Docker, PHPUnit pasa, el backend responde en `http://127.0.0.1:8000` y el frontend arranca en el puerto de Vite.

### T0.1 - Validar la aplicación completa y crear backup local

- **Prioridad:** Alta
- **Dependencias:** T0
- **Complejidad:** M
- **Archivos/modulos:** `src/`, `home-backend/src/`, `home-backend/tests/`, `home-backend/database/migrations/`, backup de MySQL/MariaDB

Con el backend y el frontend ejecutándose sin Docker, validar el flujo completo:

- crear, listar, actualizar, completar y eliminar tareas;
- crear, listar, actualizar y eliminar miembros;
- confirmar que los cambios persisten en MySQL/MariaDB;
- comprobar respuestas de validación y errores;
- verificar las solicitudes `OPTIONS` y las cabeceras CORS;
- ejecutar `npm run build` y comprobar que la compilación termina correctamente.

Cuando las pruebas locales sean satisfactorias, crear un backup recuperable de la base local antes de continuar con DonWeb. Registrar fecha, versión del esquema, cantidad de registros y ubicación del backup fuera del repositorio.

**Criterio de salida:** la aplicación funciona localmente sin Docker, `npm run build` pasa y existe un backup recuperable de la base local.

### T1 - Confirmar las capacidades del plan DonWeb

- **Prioridad:** Alta
- **Dependencias:** T0.1
- **Complejidad:** S
- **Archivos/modulos:** documentacion del servicio DonWeb y datos de la cuenta

Confirmar antes de tocar el despliegue:

- version de PHP disponible, con PHP 8.3 como objetivo;
- servidor web y soporte de reglas `.htaccess`;
- posibilidad de apuntar un dominio o subdominio a una carpeta concreta;
- disponibilidad de MySQL y acceso por phpMyAdmin o equivalente;
- posibilidad de usar SSH y Composer en el servidor;
- ruta publica del document root;
- limites de memoria, subida de archivos, tiempo de ejecucion y extensiones `pdo_mysql`, `mbstring`, `json` y `openssl`.

**Criterio de salida:** existe una ficha de capacidades de DonWeb y se elige una ruta de instalacion: Composer por SSH o `vendor/` preparado fuera del servidor.

### T2 - Definir la topologia publica

- **Prioridad:** Alta
- **Dependencias:** T1, T0.1
- **Complejidad:** S
- **Archivos/modulos:** `vite.config.ts`, `src/infrastructure/config/environment.ts`, `home-backend/public/index.php`

Elegir una de estas topologias:

1. frontend y API en el mismo dominio, con la API publicada bajo `/api`;
2. frontend en el dominio principal y backend en un subdominio, por ejemplo `api.example.com`.

Documentar la URL final y configurar `VITE_API_BASE_URL` para produccion. No depender del proxy de Vite fuera de desarrollo. Si se usa el mismo dominio, preparar el enrutamiento para que `/api/*` llegue a `home-backend/public/index.php`; si se usa subdominio, apuntar su document root directamente a `home-backend/public` cuando DonWeb lo permita.

**Criterio de salida:** la URL que usara el navegador para `GET /members` y `GET /tasks` esta definida y no contiene `localhost`.

### T3 - Externalizar y endurecer la configuracion del backend

- **Prioridad:** Alta
- **Dependencias:** T1, T2, T0.1
- **Complejidad:** M
- **Archivos/modulos:** `home-backend/public/index.php`, `home-backend/.env.example`, configuracion del hosting

Preparar una configuracion de produccion con:

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- host, puerto, nombre, usuario y contrasena reales de MySQL;
- `DB_CHARSET=utf8mb4`;
- `CORS_ALLOWED_ORIGINS` limitado al dominio real del frontend;
- `JWT_SECRET` o `APP_SECRET` generado con un valor fuerte si el flujo de autenticacion lo requiere.

Revisar que los valores por defecto `mysql`, `home` y `home` no puedan llevarse accidentalmente a produccion. Mantener `.env.example` sin secretos y documentar como inyecta DonWeb las variables: panel, archivo de configuracion protegido o entorno del proceso. Si el hosting no expone variables de entorno, implementar una carga de configuracion fuera del document root y excluirla de Git.

**Criterio de salida:** el backend puede conectarse a la base de DonWeb sin credenciales hardcodeadas y los errores internos no se muestran con `APP_DEBUG=false`.

### T4 - Preparar el front controller para el servidor web

- **Prioridad:** Alta
- **Dependencias:** T1, T2, T3, T0.1
- **Complejidad:** M
- **Archivos/modulos:** `home-backend/public/index.php`, nuevo `.htaccess` si el servidor es Apache, document root de DonWeb

Validar el comportamiento de `PATH_INFO`, las solicitudes `OPTIONS` y los metodos `GET`, `POST`, `PATCH` y `DELETE` bajo el servidor real. Añadir reglas de reescritura solo si son necesarias para dirigir las rutas de la API al front controller, evitando que los archivos estaticos del frontend sean interceptados.

No publicar el directorio completo del backend si permite exponer `composer.json`, tests, migraciones o archivos internos. El document root debe ser `home-backend/public` o una carpeta publica equivalente.

**Criterio de salida:** una peticion HTTP real a cada ruta publica llega a `public/index.php`, responde JSON y no expone archivos privados.

### T5 - Preparar dependencias PHP sin Docker

- **Prioridad:** Alta
- **Dependencias:** T1, T3, T0.1
- **Complejidad:** M
- **Archivos/modulos:** `home-backend/composer.json`, `home-backend/composer.lock`, `home-backend/vendor/`

Instalar dependencias con PHP 8.3 y Composer usando el lock:

```text
cd home-backend
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
composer check-platform-reqs
```

Si DonWeb ofrece SSH y Composer, ejecutar la instalacion en el servidor. Si no lo ofrece, generar `vendor/` con la misma version de PHP y Composer en un entorno controlado, transferirlo junto al backend y verificar que las extensiones del servidor satisfacen los requisitos. No versionar credenciales ni sustituir el lock por una actualizacion general de dependencias.

**Criterio de salida:** `vendor/autoload.php` esta disponible en produccion y las dependencias se cargan sin errores de plataforma.

### T6 - Crear la base MySQL de DonWeb y migrar datos

- **Prioridad:** Alta
- **Dependencias:** T1, T3, T0.1
- **Complejidad:** M
- **Archivos/modulos:** `home-backend/database/migrations/001_initial_schema.sql`, backup de la base local, phpMyAdmin/SSH de DonWeb

Crear una base y un usuario dedicados en DonWeb con permisos minimos. Hacer un backup de la base local antes de cualquier migracion. Importar el esquema y, si corresponde, los datos mediante phpMyAdmin o `mysql` por SSH.

Comprobar:

- tablas `members` y `tasks`;
- indices y clave foranea;
- charset y collation `utf8mb4`;
- ids `AUTO_INCREMENT` y datos existentes;
- conectividad desde PHP usando las credenciales de DonWeb.

**Criterio de salida:** la base de produccion tiene el esquema esperado y existe un backup recuperable antes de publicar.

### T7 - Retirar Docker y actualizar la documentacion activa

- **Prioridad:** Media
- **Dependencias:** T0.1, T2, T3, T4, T5, T6, T8, T9
- **Complejidad:** S
- **Archivos/modulos:** `home-backend/Dockerfile`, `home-backend/docker-compose.yml`, `docs/dev-commands.md`, `docs/settings.md`, `docs/usage.md`, `README.md`, planes activos

Eliminar `home-backend/Dockerfile` y `home-backend/docker-compose.yml` unicamente despues de que T0.1 confirme el funcionamiento local sin Docker y T9 valide la aplicacion en DonWeb. Hasta entonces, conservar Docker como referencia de respaldo y no borrar la configuracion existente. Quitar los comandos Docker de la documentacion operativa y sustituirlos por:

- instalacion local con PHP 8.3 y Composer;
- construccion del frontend con `npm run build`;
- importacion de la base MySQL;
- transferencia del backend y `dist/`;
- configuracion de variables en DonWeb;
- smoke test de la API y del frontend.

Actualizar las referencias a Docker en documentos historicos solo cuando no se altere la evidencia de una ejecucion anterior. En esos casos, marcar la referencia como historica y enlazar el procedimiento vigente.

**Criterio de salida:** ningun documento operativo presenta Docker como requisito para desarrollar o publicar la aplicacion.

### T8 - Publicar el frontend y el backend

- **Prioridad:** Alta
- **Dependencias:** T0.1, T2, T3, T4, T5, T6
- **Complejidad:** M
- **Archivos/modulos:** `dist/`, `home-backend/public/`, `home-backend/vendor/`, panel/FTP/SSH de DonWeb

Construir el frontend con variables de produccion:

```text
VITE_API_BASE_URL=https://api.example.com npm run build
```

Subir el contenido de `dist/` al document root del frontend. Publicar el backend en el document root de la API, o aplicar la topologia elegida en T2. Mantener fuera del acceso publico los tests, la migracion, `composer.json`, configuraciones privadas y cualquier backup.

**Criterio de salida:** el frontend carga por HTTPS y las llamadas del navegador llegan a la API de DonWeb sin errores de CORS ni referencias a `localhost`.

### T9 - Ejecutar validacion de produccion y dejar rollback

- **Prioridad:** Alta
- **Dependencias:** T0.1, T6, T8
- **Complejidad:** M
- **Archivos/modulos:** `docs/tasks/validacion-pre-produccion/`, nuevo registro de despliegue

Ejecutar una comprobacion funcional sobre HTTPS:

- carga inicial del frontend;
- `GET /members` y `GET /tasks`;
- crear tarea y miembro;
- completar, actualizar y eliminar una tarea;
- comprobacion de errores de validacion;
- persistencia tras una nueva peticion;
- respuesta `OPTIONS` y cabeceras CORS;
- ausencia de mensajes de excepcion cuando `APP_DEBUG=false`.

Registrar versiones, fecha, URL, resultado, errores y evidencia. Conservar el backup de la base y la version anterior de los archivos para poder volver atras si falla la validacion.

**Criterio de salida:** el flujo principal funciona en DonWeb y existe un procedimiento concreto para restaurar archivos y base de datos.

## Orden recomendado

1. T0 - preparar PHP, Composer, MySQL/MariaDB y Node.js sin Docker.
2. T0.1 - probar la aplicación completa, ejecutar `npm run build` y crear el backup local.
3. T1 - confirmar capacidades de DonWeb.
4. T2 - decidir dominios, document roots y URL de API.
5. T3 - preparar variables y secretos de produccion.
6. T4 - validar el front controller y las reescrituras.
7. T5 - resolver dependencias PHP sin Docker.
8. T6 - crear/importar MySQL y verificar conectividad.
9. T8 - publicar una primera version controlada.
10. T9 - ejecutar smoke test y documentar rollback.
11. T7 - eliminar Docker y retirar sus instrucciones solo despues de validar localmente y en DonWeb.

## Criterios de aceptacion globales

- El proyecto se puede instalar y ejecutar sin Docker.
- PHP 8.3, Composer, MySQL/MariaDB local, Node.js y npm estan verificados antes del despliegue.
- La aplicacion completa funciona localmente sin Docker y `npm run build` termina correctamente.
- Existe un backup recuperable de la base local antes de migrar datos a DonWeb.
- No quedan comandos Docker en la documentacion operativa.
- El frontend de produccion no usa `localhost` ni el proxy de Vite.
- El backend funciona con PHP 8.3, `pdo_mysql` y Composer/`vendor` validado.
- MySQL de DonWeb usa un usuario dedicado y no las credenciales del Compose local.
- `APP_DEBUG=false` en produccion y `CORS_ALLOWED_ORIGINS` esta restringido.
- El document root no expone tests, migraciones, configuracion privada ni backups.
- Se verifican los endpoints principales sobre HTTPS.
- Existe backup y rollback antes de retirar la configuracion anterior.

## Riesgos y decisiones

- **Plan de hosting limitado:** si DonWeb no ofrece PHP 8.3, `pdo_mysql` o Composer, hay que cambiar de plan o preparar `vendor/` externamente; no conviene bajar la version de PHP sin revisar Composer y PHPUnit.
- **Ruteo compartido:** publicar frontend y API bajo el mismo dominio simplifica CORS, pero requiere reglas de reescritura cuidadosas.
- **CORS incorrecto:** una URL de frontend distinta de la documentada bloqueara las mutaciones aunque la API este viva.
- **Credenciales expuestas:** no subir `.env` real, backups SQL ni secretos al repositorio ni al document root publico.
- **Migracion destructiva:** no ejecutar `DROP` ni reimportar sobre una base con datos sin backup y confirmacion de la estrategia de migracion.
- **Retirada prematura de Docker:** no eliminar `Dockerfile` ni `docker-compose.yml` hasta completar T0.1 y T9; el backup local debe existir y ser recuperable.
- **Archivos historicos:** los planes anteriores pueden mencionar Docker porque describen decisiones ya tomadas; esas menciones no deben confundirse con el procedimiento vigente.

## Preguntas abiertas

- ¿El plan de DonWeb es hosting compartido, VPS o servidor administrado?
- ¿Se usara un dominio unico (`/api`) o un subdominio para el backend?
- ¿DonWeb permite seleccionar PHP 8.3 y habilitar `pdo_mysql` desde el panel?
- ¿Hay SSH y Composer disponibles, o se debe transferir `vendor/`?
- ¿La base de produccion empieza vacia o hay datos locales que deben migrarse?
- ¿DonWeb proporciona HTTPS automatico para el dominio y el subdominio de la API?

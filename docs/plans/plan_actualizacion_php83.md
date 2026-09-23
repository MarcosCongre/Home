# Plan de actualización del backend a PHP 8.3

## Objetivo

Alinear el backend `home-backend` con PHP 8.3 para que Composer, PHPUnit, el entorno local y Docker utilicen una plataforma compatible y la suite automatizada pueda ejecutarse con resultados verificables.

## Diagnóstico confirmado

- `home-backend/composer.json` declara `php: ^8.2`.
- `home-backend/Dockerfile` usa `php:8.2-cli`.
- El PHP CLI local es 8.2.33 y no carga `openssl`.
- El `vendor/` actual contiene dependencias que requieren PHP 8.3 o superior, por lo que PHPUnit no llega a iniciar con el CLI disponible.
- La ejecución T2 quedó bloqueada antes de descubrir tests; no existe todavía una línea base funcional de la suite en el entorno corregido.
- Existe `home-backend/composer.lock`; debe conservarse como fuente de resolución reproducible y solo regenerarse cuando Composer lo requiera.

## Resultado esperado

- PHP local y Docker ejecutan PHP 8.3.x.
- `openssl` está disponible para Composer y las extensiones requeridas por la aplicación están verificadas.
- Composer instala exactamente las dependencias compatibles con el lock.
- `composer test` descubre y ejecuta PHPUnit desde `home-backend`.
- La documentación deja de presentar PHP 8.2 como plataforma objetivo.

## Plan de tareas

### T1 — Inventariar requisitos de plataforma

- **Prioridad:** Alta
- **Dependencias:** Ninguna
- **Complejidad:** S
- **Archivos/módulos:** `home-backend/composer.json`, `home-backend/composer.lock`, `home-backend/phpunit.xml`, `home-backend/src/`, `home-backend/tests/`

Confirmar mediante Composer y el código qué extensiones necesita realmente el backend y sus pruebas: como mínimo `openssl`, `pdo_mysql` y las extensiones que aparezcan en los requisitos de Composer o en el acceso a PDO. Registrar también la versión efectiva y el archivo `php.ini` usado por el CLI.

**Criterio de salida:** existe una lista concreta de requisitos de PHP 8.3 y extensiones, sin depender de suposiciones del entorno anterior.

### T2 — Preparar el PHP 8.3 local

- **Prioridad:** Alta
- **Dependencias:** T1
- **Complejidad:** M
- **Archivos/módulos:** entorno Windows, PHP CLI y `php.ini`

Instalar o seleccionar PHP 8.3.x para el CLI y configurar el `php.ini` correspondiente. Habilitar `openssl` y las extensiones identificadas en T1. Verificar que `php -v`, `php --ini`, `php -m` y `php -i` muestran el runtime esperado.

No modificar el código del backend para compensar un PHP CLI incorrecto. La ruta del ejecutable y la configuración deben quedar documentadas para poder repetir la validación.

**Criterio de salida:** `php -v` informa PHP 8.3.x y `php -m` incluye `openssl` y las extensiones requeridas.

### T3 — Actualizar el contrato de plataforma de Composer

- **Prioridad:** Alta
- **Dependencias:** T1, T2
- **Complejidad:** S
- **Archivos/módulos:** `home-backend/composer.json`, posiblemente `home-backend/composer.lock`

Cambiar la restricción de plataforma de `^8.2` a `^8.3`. Ejecutar `composer validate` y revisar el lock antes de decidir si hace falta regenerarlo. Preferir `composer install` con el lock existente; usar `composer update` únicamente si el lock no puede instalarse bajo PHP 8.3 o si hay una razón explícita para actualizar dependencias.

**Criterio de salida:** Composer valida el manifiesto y `composer check-platform-reqs` confirma una plataforma satisfecha. No se introducen actualizaciones de paquetes no relacionadas.

### T4 — Alinear la imagen Docker

- **Prioridad:** Alta
- **Dependencias:** T1
- **Complejidad:** S
- **Archivos/módulos:** `home-backend/Dockerfile`, `home-backend/docker-compose.yml`

Actualizar la imagen base de `php:8.2-cli` a `php:8.3-cli`. Mantener la instalación de `pdo_mysql` y ajustar las extensiones solo si T1 demuestra que son necesarias. Confirmar que Composer dentro de la imagen usa PHP 8.3 y que el contenedor puede instalar dependencias sin desactivar comprobaciones de plataforma.

Revisar además que la configuración de base de datos del servicio `app` sea coherente con las credenciales del servicio MySQL antes de usar Docker como validación de integración.

**Criterio de salida:** `docker compose -f home-backend/docker-compose.yml build` termina correctamente y el contenedor reporta PHP 8.3.x con `openssl` y las extensiones necesarias.

### T5 — Reinstalar dependencias desde un entorno limpio

- **Prioridad:** Alta
- **Dependencias:** T2, T3
- **Complejidad:** M
- **Archivos/módulos:** `home-backend/vendor/` generado, `home-backend/composer.lock`

En `home-backend`, eliminar o apartar únicamente el `vendor/` generado y ejecutar `composer install` con PHP 8.3 y `openssl` activos. No editar archivos generados manualmente. Comparar el resultado con el lock y revisar que no queden errores de requisitos de plataforma.

**Criterio de salida:** la instalación es reproducible y PHPUnit puede cargar `vendor/autoload.php`.

### T6 — Ejecutar la suite backend y registrar la línea base

- **Prioridad:** Alta
- **Dependencias:** T5
- **Complejidad:** S
- **Archivos/módulos:** `home-backend/tests/`, `home-backend/phpunit.xml`, `docs/tasks/validacion-pre-produccion/t2-suite-backend.md`

Desde `home-backend`, ejecutar:

```powershell
composer test
php vendor/bin/phpunit --testdox
```

Registrar cantidad de tests, assertions, errores, fallos, warnings y cualquier fallo funcional. Actualizar T2 para sustituir la clasificación de bloqueo por el resultado real, o conservar el bloqueo solo si aparece un impedimento nuevo y reproducible.

**Criterio de salida:** PHPUnit completa el descubrimiento y la ejecución; el documento T2 contiene evidencia suficiente para determinar si la suite pasa.

### T7 — Validar el flujo en Docker y la API

- **Prioridad:** Media
- **Dependencias:** T4, T6
- **Complejidad:** M
- **Archivos/módulos:** `home-backend/docker-compose.yml`, `home-backend/public/index.php`, `home-backend/src/Infrastructure/Http/`, `home-backend/tests/Integration/`

Levantar el entorno con Docker Compose, esperar el healthcheck de MySQL y ejecutar las pruebas de integración existentes. Verificar al menos los endpoints documentados para crear, listar y completar tareas, sin alterar datos persistentes de desarrollo sin necesidad.

**Criterio de salida:** el contenedor arranca con PHP 8.3, la conexión a la base funciona y las pruebas de integración pasan o dejan fallos funcionales claramente clasificados.

### T8 — Actualizar documentación y procedimiento de repetición

- **Prioridad:** Media
- **Dependencias:** T2, T3, T4, T6
- **Complejidad:** S
- **Archivos/módulos:** `docs/architecture.md`, `docs/dev-commands.md`, `docs/tasks/validacion-pre-produccion/t1-entorno-inventario.md`, `docs/tasks/validacion-pre-produccion/t2-suite-backend.md`, `docs/plan_back/plan.md`

Actualizar las referencias que presenten PHP 8.2 como objetivo del backend. Documentar PHP 8.3, `openssl`, el directorio correcto para ejecutar Composer y el comando de validación. Mantener la evidencia histórica del bloqueo de T2, pero añadir el resultado de la repetición posterior.

**Criterio de salida:** una persona nueva puede preparar el entorno y ejecutar la suite sin inferir rutas o versiones.

## Orden recomendado

1. T1 — inventario de requisitos.
2. T2 — PHP 8.3 y extensiones en Windows.
3. T3 — Composer y contrato de plataforma.
4. T4 — Docker.
5. T5 — instalación limpia de dependencias.
6. T6 — suite PHPUnit y actualización de evidencia.
7. T7 — validación integrada en Docker.
8. T8 — documentación final.

## Criterios de aceptación globales

- `php -v` muestra PHP 8.3.x tanto localmente como dentro del contenedor.
- `openssl` aparece en `php -m` y Composer no muestra el bloqueo TLS.
- `composer validate`, `composer install` y `composer check-platform-reqs` terminan sin errores.
- `composer test` finaliza después de descubrir los casos PHPUnit y deja contadores verificables.
- Docker Compose construye y arranca el backend sin cambiar el contrato de la API.
- No se actualizan dependencias de aplicación de forma incidental ni se modifican migraciones salvo que una prueba demuestre una necesidad independiente.
- La documentación refleja PHP 8.3 como versión mínima objetivo.

## Riesgos y decisiones

- **Dependencias resueltas con PHP distinto:** el `vendor/` actual no debe tomarse como evidencia de una instalación reproducible; se debe reinstalar bajo PHP 8.3.
- **Extensiones diferentes entre Windows y Docker:** ambas plataformas deben verificarse por separado; que Docker funcione no demuestra que el CLI local tenga `openssl`.
- **Regeneración del lock:** actualizar `composer.lock` puede cambiar versiones transitivas; solo debe hacerse si la instalación con el lock actual no es compatible.
- **Compatibilidad del código:** PHP 8.3 puede descubrir warnings o diferencias de runtime; cualquier fallo funcional debe separarse del problema de plataforma y cubrirse con una prueba concreta.

## Preguntas abiertas y supuestos

- Se asume que el objetivo es PHP 8.3 como mínimo, no una migración a PHP 8.3 con actualización general de PHPUnit u otras librerías.
- Falta confirmar si el entorno final será Windows local, Docker o ambos; el plan cubre ambos porque T2 depende del CLI local y el repositorio ya incluye Docker.
- Debe confirmarse durante T1 si las pruebas usan SQLite además de MySQL, ya que el Dockerfile instala `libsqlite3-dev` pero actualmente solo compila `pdo_mysql`.
- No se propone cambiar la API ni la arquitectura del backend; el alcance es la plataforma de ejecución y su validación.

## Estado de implementación

- [x] T1 — requisitos y bloqueo de entorno documentados.
- [x] T2 — PHP 8.3 local: PHP 8.3.35 configurado con `openssl`, `mbstring`, `pdo_mysql`, `pdo_sqlite` y `sqlite3`.
- [x] T3 — contrato de Composer actualizado a `^8.3` y validado con PHP 8.3.35.
- [x] T4 — imagen Docker actualizada a `php:8.3-cli`; su build requiere Docker disponible.
- [x] T5 — dependencias instaladas/verificadas con PHP 8.3.35.
- [x] T6 — suite PHPUnit completada: 20 tests, 62 assertions, 0 fallos.
- [ ] T7 — validación Docker/API: pendiente; Docker no está disponible en el entorno actual.
- [x] T8 — documentación y evidencia actualizadas para PHP 8.3.

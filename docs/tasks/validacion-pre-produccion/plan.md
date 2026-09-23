# Plan: Validacion pre-produccion con base de datos local

## Resumen

Validar de forma reproducible el sistema completo usando la base de datos local ya poblada: infraestructura local, persistencia MySQL, API PHP, integracion con React y flujos funcionales de tareas y miembros. El resultado debe ser un informe de pruebas con evidencia suficiente para aprobar el paso a produccion o bloquearlo con defectos y riesgos claramente identificados.

## Hallazgos que condicionan el plan

- El backend dispone de pruebas PHPUnit unitarias y de integracion en `home-backend/tests/`.
- El frontend tiene `npm run build`, pero no dispone actualmente de un script de lint, typecheck o pruebas de navegador.
- La documentacion indica que la integracion real cubre principalmente la carga y completado de tareas; creacion, edicion y miembros pueden conservar comportamiento local.
- `home-backend/docker-compose.yml` y `home-backend/.env.example` declaran `home` como base de datos. Esta configuración debe mantenerse alineada con la instancia local antes de ejecutar pruebas contra MySQL.
- La validacion debe usar datos de prueba identificables y operaciones reversibles para no perder la base poblada.

## Objetivo de salida

Emitir uno de estos veredictos:

- **Aprobado:** todas las puertas criticas pasan y no quedan defectos criticos o altos sin decision explicita.
- **Aprobado con observaciones:** los flujos criticos pasan, pero quedan riesgos medios o cobertura conocida pendiente.
- **Bloqueado:** falla una puerta critica, hay inconsistencia de datos, la API no es utilizable o un flujo principal no puede completarse.

## Plan de trabajo

### T1. Preparar el entorno y proteger los datos

- **Prioridad:** Alta
- **Dependencias:** Ninguna
- **Complejidad:** S
- **Afecta:** `home-backend/docker-compose.yml`, `home-backend/.env.example`, `docs/dev-commands.md`
- **Acciones:**
  - Confirmar versiones disponibles de PHP, Composer, Node/npm, Docker y MySQL.
  - Confirmar que la base local contiene los datos esperados y registrar un inventario inicial sin exponer secretos.
  - Crear un respaldo o snapshot antes de ejecutar operaciones mutables.
  - Confirmar que `home` prevalece como base de datos en runtime y que no existen variables externas que la sobrescriban.
  - Confirmar origenes CORS, puertos y variables de entorno usadas por frontend y backend.
- **Salida:** checklist de entorno, inventario inicial y respaldo verificable.

### T2. Ejecutar la suite automatizada del backend

- **Prioridad:** Alta
- **Dependencias:** T1
- **Complejidad:** S
- **Afecta:** `home-backend/composer.json`, `home-backend/phpunit.xml`, `home-backend/tests/`
- **Acciones:**
  - Ejecutar `composer test` desde `home-backend/`.
  - Registrar cantidad de pruebas, errores, fallos y tiempo de ejecucion.
  - Separar fallos preexistentes de fallos causados por la configuracion MySQL local.
  - Confirmar que las pruebas de persistencia y HTTP usan el adaptador esperado y no solo memoria.
- **Criterio:** la suite pasa completamente o cada fallo queda clasificado con responsable y decision.

### T3. Validar esquema, datos y persistencia MySQL

- **Prioridad:** Alta
- **Dependencias:** T1, T2
- **Complejidad:** M
- **Afecta:** `home-backend/database/migrations/001_initial_schema.sql`, `home-backend/src/Infrastructure/Persistence/`, `home-backend/tests/Integration/Persistence/`
- **Acciones:**
  - Confirmar tablas, columnas, claves, restricciones y tipos de estado frente al modelo de dominio.
  - Verificar conteos e identificadores de tareas, miembros y hogares de la base inicial.
  - Probar lectura de registros existentes y persistencia de una tarea de prueba.
  - Probar actualizacion, completado y eliminacion, comprobando el estado directamente en MySQL.
  - Restaurar o eliminar los registros creados durante la prueba y verificar que el inventario inicial se conserva.
- **Criterio:** los cambios confirmados por la API coinciden con los cambios observados en la base y no se alteran datos fuera del escenario.

### T4. Validar el contrato HTTP de tareas y miembros

- **Prioridad:** Alta
- **Dependencias:** T2, T3
- **Complejidad:** M
- **Afecta:** `home-backend/src/Infrastructure/Http/Router.php`, `home-backend/src/Infrastructure/Http/TaskController.php`, `home-backend/src/Infrastructure/Http/MemberController.php`, `home-backend/tests/Integration/Http/`
- **Acciones:**
  - Comprobar listar, crear, actualizar, completar y eliminar tareas.
  - Comprobar operaciones de miembros que esten expuestas por el router.
  - Verificar payloads, estados HTTP, nombres de campos, fechas y estados serializados.
  - Probar identificadores inexistentes, titulos vacios, estados invalidos y relaciones invalidas.
  - Confirmar respuestas CORS y manejo consistente de errores.
- **Criterio:** los endpoints entregan contratos estables y los casos invalidos no dejan mutaciones parciales.

### T5. Validar integracion frontend-API

- **Prioridad:** Alta
- **Dependencias:** T4
- **Complejidad:** M
- **Afecta:** `src/application/tasks/useTasks.ts`, `src/infrastructure/http/apiClient.ts`, `src/infrastructure/http/taskApi.ts`, `src/infrastructure/http/memberApi.ts`, `src/infrastructure/http/taskMapper.ts`, `src/App.tsx`
- **Acciones:**
  - Iniciar backend y frontend con la configuracion local acordada.
  - Confirmar que las tareas existentes se cargan desde la API y se representan correctamente.
  - Probar completar y revertir una tarea, incluyendo el comportamiento ante error de red.
  - Verificar creacion y edicion desde la UI; determinar si la operacion persiste en API o permanece local y registrar la diferencia.
  - Verificar miembros, filtros, calendario, dashboard y detalle con los datos reales.
  - Confirmar que estados `pending`/`completed`, miembros asignados y fechas no se pierden durante el mapeo.
- **Criterio:** los flujos declarados como integrados persisten y se reflejan tras recargar; cualquier flujo local queda marcado como bloqueo o deuda aceptada segun el alcance de produccion.

### T6. Validar build y smoke test de la aplicacion

- **Prioridad:** Alta
- **Dependencias:** T5
- **Complejidad:** S
- **Afecta:** `package.json`, `vite.config.ts`, `src/`
- **Acciones:**
  - Ejecutar `npm run build`.
  - Servir la aplicacion y comprobar arranque, carga inicial, navegacion entre tabs y ausencia de errores bloqueantes en consola.
  - Ejecutar una comprobacion en viewport movil y escritorio.
  - Registrar warnings relevantes de build y decidir si son aceptables antes de produccion.
- **Criterio:** la build termina correctamente y el smoke test permite acceder a todos los flujos principales sin errores bloqueantes.

### T7. Ejecutar pruebas negativas, regresion y repetibilidad

- **Prioridad:** Media
- **Dependencias:** T3, T4, T5, T6
- **Complejidad:** M
- **Afecta:** backend, frontend y datos de prueba
- **Acciones:**
  - Repetir los flujos principales despues de varias operaciones para detectar estado stale o duplicados.
  - Interrumpir solicitudes y comprobar rollback o mensajes de error.
  - Probar refresco de pagina, doble envio y navegacion durante una solicitud.
  - Comprobar que el escenario puede ejecutarse de nuevo sin depender de residuos de una ejecucion anterior.
- **Criterio:** no hay perdida silenciosa de datos, duplicacion inesperada ni estados imposibles.

### T8. Preparar decision de produccion

- **Prioridad:** Alta
- **Dependencias:** T1-T7
- **Complejidad:** S
- **Afecta:** `docs/tasks/validacion-pre-produccion/`
- **Acciones:**
  - Consolidar resultados por caso: pasado, fallido, bloqueado o no aplicable.
  - Clasificar defectos por severidad y asignar acciones pendientes.
  - Comparar configuracion local con la de produccion, sin trasladar secretos al repositorio.
  - Documentar rollback, respaldo, migraciones y responsable de aprobacion.
  - Emitir el veredicto final usando los criterios de salida definidos arriba.
- **Salida:** informe de validacion, lista de defectos y decision de release.

## Matriz minima de escenarios

| ID | Escenario | Resultado esperado | Tipo |
|---|---|---|---|
| V01 | Arranque de MySQL, backend y frontend | Servicios disponibles y conectados | Smoke |
| V02 | Listar tareas existentes | Datos locales completos y correctamente mapeados | Integracion |
| V03 | Crear tarea de prueba | Respuesta valida y registro persistido | API/BD |
| V04 | Actualizar titulo o miembro | Cambio visible en API, UI y BD | E2E |
| V05 | Completar tarea | Estado y fecha de completado consistentes | E2E |
| V06 | Eliminar tarea de prueba | Registro eliminado y UI actualizada | API/BD |
| V07 | Miembros y filtros | Datos y relaciones correctas | Funcional |
| V08 | Payload invalido o ID inexistente | Error controlado sin mutacion parcial | Negativa |
| V09 | Error de red durante completado | UI revierte o informa el fallo | Resiliencia |
| V10 | Recarga despues de cambios | Estado persistido, sin datos mock inesperados | Regresion |
| V11 | Build de produccion | `npm run build` exitoso | Build |
| V12 | Suite backend | `composer test` exitoso | Automatizada |

## Criterios de aprobacion

- No existen fallos criticos en V01, V02, V05, V10, V11 o V12.
- Toda operacion mutable usada durante las pruebas tiene respaldo, identificacion y limpieza o restauracion.
- Los contratos de API y el mapeo frontend coinciden en identificadores, estados y relaciones.
- Los defectos de seguridad, configuracion de base de datos o perdida de datos bloquean la salida hasta ser resueltos o aceptados formalmente.
- Las limitaciones conocidas del frontend quedan documentadas y aprobadas si no se exige persistencia completa en produccion.

## Riesgos y decisiones pendientes

- Confirmar que la base `home` es la fuente de verdad también en el entorno de despliegue.
- Confirmar si miembros, creacion y edicion de tareas deben persistir en produccion; el plan no debe declarar aprobado un flujo que aun sea local si es requisito productivo.
- Definir umbrales de rendimiento, carga y seguridad; no quedan cubiertos por `composer test` ni `npm run build`.
- Definir quien aprueba el informe y quien ejecuta rollback si falla una prueba productiva.

## Orden de ejecucion

`T1 -> T2 -> T3 -> T4 -> T5 -> T6 -> T7 -> T8`

T3 y T4 pueden ejecutarse en paralelo despues de T2 si la configuracion de persistencia ya esta confirmada. T6 puede ejecutarse en paralelo con T3 y T4 cuando el frontend no dependa de cambios de backend durante la validacion.
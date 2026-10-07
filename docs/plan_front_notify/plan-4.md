# Plan: Tipo y cliente HTTP frontend para Alerts

**Fecha**: 2026-10-04 | **Brief**: [brief-4.md](brief-4.md)

## Resumen

Definir el contrato de dominio para las alertas en frontend y crear un cliente HTTP que liste, descarte y descarte todo con el patrón ya usado por `taskApi` y el cliente compartido `request()`. El trabajo se limita a la capa frontend: modelo tipado, acceso a API y ajuste mínimo de fixtures demo para mantener la compatibilidad con el contrato de la UI y del backend.

## Contexto técnico

- **Aplicación**: SPA React 19 + TypeScript 5.7 + Vite 8.
- **Capas afectadas**: `src/domain`, `src/infrastructure/http`, `src/infrastructure/config`, `src/infrastructure/demo`.
- **Patrón existente**: `src/infrastructure/http/taskApi.ts` compra/normaliza la respuesta con `request<T>()`, reutiliza `environment.apiBaseUrl` y `environment.householdId` y transforma DTOs a modelos de dominio cuando hace falta.
- **Cambio requerido**: crear `src/domain/alerts/alert.ts` con el tipo `Alert`; crear `src/infrastructure/http/alertApi.ts` con `listAlerts()`, `dismissAlert(id)`, `dismissAllAlerts()`, siguiendo la estructura del cliente existente.
- **Restricción de alineación**: el fixture demo actual de `NOTIFS` usa `id` como string (`n1`, `n2`, etc.) y no incluye `status`, por lo que hay que decidir si se normaliza en la demo o se adapta en una capa intermedia para ser compatible con la API.
- **Validación disponible**: `package.json` define scripts de build, y el proyecto ya usa `pnpm exec tsc --noEmit` como comprobación de tipos para frontend.

## Comprobación de restricciones

- El alcance sigue el brief aprobado y no incluye cambios en backend, persistencia ni UI de alertas.
- No se añaden dependencias ni paradigmas nuevos; la solución debe reutilizar `request()` y la configuración de entorno actual.
- La compatibilidad del `Alert` con los fixtures demo es una decisión de adaptación, no una ampliación de scope.

## Requisitos de la revisión

- **REQ-001**: existir un tipo `Alert` con los campos `id`, `title`, `body`, `icon`, `time`, `urgent`, `user`, `status` y valores correctos para `status`.
- **REQ-002**: `src/infrastructure/http/alertApi.ts` usar `environment.apiBaseUrl` y `environment.householdId` como base para construir las rutas.
- **REQ-003**: `listAlerts()` debe realizar `GET /alerts?householdId=...` y devolver `Promise<Alert[]>`.
- **REQ-004**: `dismissAlert(id)` debe ejecutar `PATCH /alerts/{id}/dismiss` y devolver `Promise<void>`.
- **REQ-005**: `dismissAllAlerts()` debe ejecutar `POST /alerts/dismiss-all` con `{ householdId }` en JSON y devolver `Promise<void>`.
- **REQ-006**: el modelo debe mantenerse alineado con los campos que la UI ya consume desde `NOTIFS`, con la mínima adaptación necesaria para mantener el contrato frontend.
- **REQ-007**: no se introducen cambios de pantalla ni lógica de interacción de alertas; solo se ajusta el fixture demo si es estrictamente necesario.

## Pasos de implementación

### Paso 1: Definir el modelo de dominio

- **Requisitos**: REQ-001, REQ-006
- **Descripción**: crear `src/domain/alerts/alert.ts` con el tipo `Alert` según el contrato del brief. Mantener `status: 'unread' | 'dismissed'`, `user: string | null`, `urgent: boolean` y `time: string` como valores orientados a UI. Si el modelo se usa con fixtures demo, decidir un normalizador mínimo o un adaptador en la capa de demo para no contaminar la lógica de negocio.

### Paso 2: Construir el cliente HTTP de Alerts

- **Requisitos**: REQ-002 a REQ-005
- **Descripción**: crear `src/infrastructure/http/alertApi.ts` con las funciones `listAlerts()`, `dismissAlert(id)` y `dismissAllAlerts()`. Reutilizar `request()` de `apiClient.ts`, construir URLs con `environment.apiBaseUrl` y añadir `householdId` como parámetro/query o body según corresponda. Respetar el estilo de `taskApi.ts` y no introducir lógica extra fuera del transporte.

### Paso 3: Alinear datos demo con el contrato

- **Requisitos**: REQ-006, REQ-007
- **Descripción**: revisar `src/infrastructure/demo/demoData.ts` y decidir la estrategia mínima de compatibilidad: (a) convertir los IDs del fixture a `number`, (b) añadir `status` con valores por defecto y (c) conservar `user` como iniciales o nombrar la propiedad desde el backend. Si no se usa el fixture de alertas aún, dejar la adaptación documentada en el mismo archivo o en la capa de tipo para evitar combinar responsabilidades.

### Paso 4: Validación de contrato y compilación

- **Requisitos**: REQ-001 a REQ-007
- **Descripción**: ejecutar `pnpm exec tsc --noEmit` para verificar que la nueva capa no rompe la firma del frontend. Si se detectan errores ajenos a la tarea, dejarlos documentados sin ampliar el alcance. Si el fixture demo necesita cambios de tipos, ajustar solo lo necesario para que compile y siga siendo compatible con la UI.

## Desglose ejecutable

### Fase 1: Modelo y transporte

- [ ] T001 [Plan:1] Crear `src/domain/alerts/alert.ts` con el tipo `Alert` y sus valores literales.
- [ ] T002 [Plan:2] Crear `src/infrastructure/http/alertApi.ts` con `listAlerts`, `dismissAlert` y `dismissAllAlerts` usando `request()` y `environment`.

### Fase 2: Compatibilidad de fixtures

- [ ] T003 [Plan:3] Revisar `src/infrastructure/demo/demoData.ts` y decidir el ajuste mínimo de `NOTIFS` para mapear a `Alert` sin romper la UI actual.

### Fase 3: Verificación

- [ ] T004 [Plan:4] Ejecutar `pnpm exec tsc --noEmit` y resolver solo los errores atribuibles al primer alcance de la tarea.

## Criterios de aceptación

- Existe `src/domain/alerts/alert.ts` y exporta el tipo `Alert` con `id: number`, `title: string`, `body: string`, `icon: string`, `time: string`, `urgent: boolean`, `user: string | null` y `status: 'unread' | 'dismissed'`.
- `src/infrastructure/http/alertApi.ts` exporta `listAlerts`, `dismissAlert` y `dismissAllAlerts` con la misma estructura y estilo de `taskApi.ts`.
- Las llamadas usan `environment.apiBaseUrl` y `environment.householdId` para formar las rutas.
- `dismissAlert` y `dismissAllAlerts` usan `request<void>` y manejan respuestas 204 sin revisar el cuerpo.
- Los fixtures demo se han alineado con el contrato o se han adaptado de manera explícita para que sigan siendo compatibles con la UI.
- El proyecto compila en TypeScript sin introducir regresiones en otras capas.

## Estrategia de validación

- `pnpm exec tsc --noEmit`: comprobar que los tipos del dominio y la API se integran con la estructura actual del frontend.
- Revisión de compatibilidad de `NOTIFS`: confirmar que los ids y status no rompen la lógica de presentación del historial/estado de notificaciones.
- Verificación del patrón de llamadas: `GET /alerts?householdId=...`, `PATCH /alerts/{id}/dismiss`, `POST /alerts/dismiss-all` con `{ householdId }`.

## Mapeo de requisitos

| REQ ID | Descripción | Pasos | Evidencia esperada |
|---|---|---|---|
| REQ-001 | Contrato `Alert` | 1 | `src/domain/alerts/alert.ts` |
| REQ-002 | Uso de `environment.apiBaseUrl` y `householdId` | 2 | `src/infrastructure/http/alertApi.ts` |
| REQ-003 | `listAlerts()` con `GET /alerts` | 2 | cliente HTTP |
| REQ-004 | `dismissAlert()` con `PATCH /alerts/{id}/dismiss` | 2 | cliente HTTP |
| REQ-005 | `dismissAllAlerts()` con `POST /alerts/dismiss-all` | 2 | cliente HTTP |
| REQ-006 | Alineación con `NOTIFS` | 3 | `src/infrastructure/demo/demoData.ts` |
| REQ-007 | Alcance sin UI/backend | 3, 4 | plan y validación |

## Orden y dependencias

`T001 -> T002 -> T003 -> T004`.

La definición del modelo debe preceder al cliente HTTP para evitar construir llamadas con un contrato inexistente. La alineación de fixtures debe hacerse antes de la validación final para comprobar la compatibilidad del flujo real con la UI.

## Limitaciones conocidas

- El fixture actual de `NOTIFS` usa `id` string y omite `status`; eso no es un fallo del backend sino un desajuste del demo. Hay que resolverlo en frontend con un ajuste mínimo de tipo o normalización.
- La decisión sobre `user` (`'J'`, `'L'`, `'M'` vs nombre completo) queda pendiente hasta validar cómo la API real llene ese campo; el plan no amplía el alcance ni cambia la UI para arreglarlo.
- La tarea no contempla cambios de pantalla ni endpoints de backend; el trabajo se limita a la capa de tipos y transporte.

## Ejecución prevista

- Crear el modelo `Alert` y el cliente HTTP.
- Ajustar demo fixtures si es necesario para mantener consistencia con el nuevo contrato.
- Ejecutar `pnpm exec tsc --noEmit` para validar el alcance sin ampliar la solución más allá del brief.

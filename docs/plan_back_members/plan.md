<!-- # Plan de tarea nueva — Backend de miembros y pertenencias -->

## Objetivo

Implementar la opción 1 del análisis: crear una entidad real de miembros/usuarios en el backend para que el frontend deje de depender de datos demo y pueda sincronizar miembros, pertenencias y estados con una fuente de verdad persistente.

## Alcance

Este plan simula una nueva tarea de trabajo independiente del plan principal del frontend. Su finalidad es definir la implementación necesaria para introducir una entidad de usuarios/members dentro de la arquitectura del backend PHP.

## Estado actual

- El backend ya modela tareas y casos de uso de tareas.
- Existe `Task`, `TaskRepositoryInterface` y endpoints de creación/completado/listado.
- La entidad real de miembros ya quedó implementada: `Member`, `MemberRepositoryInterface`, handlers CRUD, controller y repositorio en memoria.
- El frontend ya no depende exclusivamente de `INIT_USERS` para la carga inicial; usa `useMembers` con fallback y llamada HTTP a `/members`.
- La comprobación de integración con tareas sigue siendo el punto de validación final: el comportamiento actual es compatible con `userId`/`assignee`, pero aún no hay una relación persistente de miembro en la entidad de tarea del backend.

## Puntos ya avanzados

- [x] Dominio `Member` con `id`, `name`, `avatar`, `color` y `householdId`.
- [x] Repositorio de miembros con filtrado por hogar.
- [x] Casos de uso CRUD de miembros en Application.
- [x] Controller HTTP y routing de `/members`.
- [x] Integración frontend con `memberApi` y `useMembers`.
- [x] Validación de compilación del frontend con `npm run build` (éxito).
- [ ] Validación de tests PHP 8.2.33 del backend miembro (requerido por el entorno actual).

## Objetivo funcional

- La app debe poder registrar, listar, actualizar y eliminar miembros del hogar.
- Cada miembro debe estar asociado a un `householdId` o contexto del hogar.
- Las tareas deben poder relacionarse con un miembro por `userId` o `memberId` de forma consistente.
- El frontend podrá dejar de usar los datos demo como fuente principal.

## Arquitectura propuesta

```text
home-backend/
├── src/
│   ├── Domain/
│   │   ├── Members/
│   │   │   ├── Member.php
│   │   │   ├── MemberId.php
│   │   │   ├── MemberName.php
│   │   │   ├── MemberColor.php
│   │   │   ├── MemberRepositoryInterface.php
│   │   │   └── MemberStatus.php
│   │   └── Households/
│   │       └── HouseholdId.php
│   ├── Application/
│   │   ├── Members/
│   │   │   ├── CreateMember/
│   │   │   │   ├── CreateMemberCommand.php
│   │   │   │   ├── CreateMemberHandler.php
│   │   │   │   └── CreateMemberOutput.php
│   │   │   ├── ListMembers/
│   │   │   │   ├── ListMembersQuery.php
│   │   │   │   └── ListMembersHandler.php
│   │   │   ├── UpdateMember/
│   │   │   │   ├── UpdateMemberCommand.php
│   │   │   │   └── UpdateMemberHandler.php
│   │   │   └── DeleteMember/
│   │   │       ├── DeleteMemberCommand.php
│   │   │       └── DeleteMemberHandler.php
│   │   └── DTOs/
│   │       └── MemberDto.php
│   └── Infrastructure/
│       ├── Http/
│       │   ├── MemberController.php
│       │   └── Router.php
│       └── Persistence/
│           ├── InMemoryMemberRepository.php
│           └── PdoMemberRepository.php
```

## Fases planificadas

### Fase 1 — Dominio de miembros

- [x] definir la entidad `Member` con id, name, avatar, color y householdId
- [x] crear value objects para nombre y color si se requiere
- [x] definir `MemberRepositoryInterface`
- [x] añadir invariantes: nombre obligatorio, color validado, relación con hogar válida

### Fase 2 — Casos de uso de aplicación

- [x] crear `CreateMemberCommand` y `CreateMemberHandler`
- [x] crear `ListMembersQuery` y `ListMembersHandler`
- [x] crear `UpdateMemberCommand` y `UpdateMemberHandler`
- [x] crear `DeleteMemberCommand` y `DeleteMemberHandler`
- [x] asegurar que las reglas del negocio viven en Application/Domain y no en HTTP

### Fase 3 — Infraestructura HTTP y persistencia

- [x] crear `MemberController` con endpoints:
  - `GET /members?householdId=...`
  - `POST /members`
  - `PATCH /members/{id}`
  - `DELETE /members/{id}`
- [x] crear repositorio persistente compatible con la infraestructura actual
- [x] mapear DTOs entre backend y dominio
- [x] incluir validaciones básicas de entrada

### Fase 4 — Integración con tareas

- [x] documentar la relación entre `Task` y `Member` por `userId` o `memberId` desde el contrato actual del frontend
- [x] garantizar que una tarea pueda asignarse a un miembro real en la UI y en el flujo de API
- [ ] mantener el contrato de persistencia de tareas con relación de miembro en el backend en un modelo más estricto si se desea una entidad completa `Task -> Member`
- [ ] revisar y cerrar la compatibilidad end-to-end con el backend real para asignación y completado de tareas

### Fase 5 — Testing y validación

- [x] comprobación de compilación frontend del flujo de miembros
- [ ] pruebas unitarias del dominio de miembros ejecutadas con PHP 8.2.33
- [ ] pruebas de aplicación para casos de creación y actualización ejecutadas en PHP 8.2.33
- [ ] pruebas de integración HTTP para member controller ejecutadas en PHP 8.2.33
- [ ] validación de compatibilidad con el flujo actual de tareas a nivel backend real

### Estado de verificación actual

- Validado: `npm run build` en el frontend devuelve éxito.
- Pendiente: ejecutar PHPUnit del backend en entorno con PHP 8.2.33, que es la versión requerida por este proyecto y por el entorno disponible.
- Observación funcional: la relación actual es compatible con `userId` en tareas y `householdId` en miembros, pero no hay un objeto `memberId` persistido dentro del modelo de `Task` del backend; esto es compatible con la arquitectura actual pero no es un vínculo de dominio completo.

## Criterios de aceptación

La tarea se considera cerrada cuando:

- existe una entidad `Member` en el dominio;
- hay repositorio y casos de uso para CRUD;
- las rutas HTTP de miembros están operativas;
- el frontend ya no necesita depender de `INIT_USERS` como fuente principal;
- la relación tareas-miembros queda consistente con el modelado real.

## Riesgos y observaciones

- El backend actual no tiene aún el concepto de hogar de forma robusta; por tanto, hay que decidir si se usa `householdId` como identificador simple o si se introduce un agregado `Household` posterior.
- Para no romper la implementación actual, la relación `Task -> Member` debe mantenerse compatible con el campo `userId` existente temporalmente.
- Esta tarea es un bloque anterior al refactor del frontend, porque la capa UI no puede conectarse a una entidad real mientras esa entidad no exista en backend.

## Resultado esperado

Se obtiene una base funcional para que el frontend pueda migrar desde mock data hasta una entidad real de usuarios, dejando la aplicación más escalable, más coherente y alineada con la arquitectura Domain/Application/Infrastructure ya usada por el backend.

<!-- # Plan de tarea nueva — Backend de miembros y pertenencias -->

## Objetivo

Implementar la opción 1 del análisis: crear una entidad real de miembros/usuarios en el backend para que el frontend deje de depender de datos demo y pueda sincronizar miembros, pertenencias y estados con una fuente de verdad persistente.

## Alcance

Este plan simula una nueva tarea de trabajo independiente del plan principal del frontend. Su finalidad es definir la implementación necesaria para introducir una entidad de usuarios/members dentro de la arquitectura del backend PHP.

## Estado actual

- El backend actual ya modela tareas y casos de uso de tareas.
- Existe `Task`, `TaskRepositoryInterface` y endpoints de creación/completado/listado.
- El frontend usa `useMembers` y datos mock de `INIT_USERS`, por lo que la capa de usuarios no tiene origen backend real.
- Por tanto, la entidad real de usuarios no existe todavía ni hay repositorio, controlador ni tests asociados.

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

- [ ] definir la entidad `Member` con id, name, avatar, color y householdId
- [ ] crear value objects para nombre y color si se requiere
- [ ] definir `MemberRepositoryInterface`
- [ ] añadir invariantes: nombre obligatorio, color validado, relación con hogar válida

### Fase 2 — Casos de uso de aplicación

- [ ] crear `CreateMemberCommand` y `CreateMemberHandler`
- [ ] crear `ListMembersQuery` y `ListMembersHandler`
- [ ] crear `UpdateMemberCommand` y `UpdateMemberHandler`
- [ ] crear `DeleteMemberCommand` y `DeleteMemberHandler`
- [ ] asegurar que las reglas del negocio viven en Application/Domain y no en HTTP

### Fase 3 — Infraestructura HTTP y persistencia

- [ ] crear `MemberController` con endpoints:
  - `GET /members?householdId=...`
  - `POST /members`
  - `PATCH /members/{id}`
  - `DELETE /members/{id}`
- [ ] crear repositorio persistente compatible con la infraestructura actual
- [ ] mapear DTOs entre backend y dominio
- [ ] incluir validaciones básicas de entrada

### Fase 4 — Integración con tareas

- [ ] documentar la relación entre `Task` y `Member` por `userId` o `memberId`
- [ ] garantizar que una tarea pueda asignarse a un miembro real
- [ ] mantener el contrato del frontend con el backend consistente

### Fase 5 — Testing y validación

- [ ] pruebas unitarias del dominio de miembros
- [ ] pruebas de aplicación para casos de creación y actualización
- [ ] pruebas de integración HTTP para member controller
- [ ] validación de compatibilidad con el flujo actual de tareas

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

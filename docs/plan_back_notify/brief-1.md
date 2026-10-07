# Brief: Módulo de dominio Alerts

## Solicitud original
Crear en `home-backend/src/Domain/Alerts/` un nuevo módulo de dominio que siga el estilo de `home-backend/src/Domain/Tasks/`, incluyendo `strict_types` y el namespace `App\Domain\Alerts`. También se solicita crear este brief dentro de `docs/plan_back_notify/`.

## Objetivo
Definir la entidad de alertas y el contrato de persistencia para que las alertas del hogar puedan consultarse, guardarse y marcarse como descartadas.

## Enfoque propuesto
Añadir una entidad `Alert` con los datos solicitados y una interfaz `AlertRepositoryInterface` con las operaciones de consulta, guardado y descarte. Mantener el estilo del dominio Tasks, respetando los tipos y la nulabilidad especificados.

## Alcance
### Incluido
- `Alert.php` con `id` (`int`), `householdId` (`string`), `memberId` (`?int`), `title` (`string`), `body` (`string`), `icon` (`string`), `urgent` (`bool`), `status` (`'unread' | 'dismissed'`) y `createdAt` (`DateTimeImmutable`).
- `AlertRepositoryInterface.php` con `findByHousehold(string $householdId): array`, `save(Alert $alert): Alert`, `dismiss(int $id): void` y `dismissAll(string $householdId): void`.
- Namespace `App\Domain\Alerts` y `declare(strict_types=1);`.

### Excluido
- Implementaciones de persistencia, controladores, endpoints, notificaciones o cambios en el módulo Tasks.

## Supuestos
- La ubicación prevista para este documento es `docs/plan_back_notify/`, carpeta existente y vacía.
- Este documento registra el requerimiento; la creación de los archivos PHP se realizará en la fase de implementación.

## Preguntas abiertas
- Ninguna para definir el alcance solicitado.

## Siguiente paso
Usar este brief como entrada para `orch-dev`, que elaborará el plan técnico antes de implementar el módulo.
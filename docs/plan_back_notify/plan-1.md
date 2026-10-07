# Plan: Módulo de dominio Alerts

**Fecha**: 2026-10-02 | **Brief**: [brief-1.md](brief-1.md)

## Resumen

Crear el módulo de dominio `App\Domain\Alerts` siguiendo el estilo de `App\Domain\Tasks`, con una entidad `Alert` y un contrato `AlertRepositoryInterface` que permita consultar, guardar y descartar alertas por hogar. La implementación debe mantenerse acotada a la capa de dominio y a la definición de persistencia, sin extender el alcance a controladores, endpoints ni notificaciones.

## Contexto técnico

- **Referencia de dominio**: `home-backend/src/Domain/Tasks/Task.php` y `home-backend/src/Domain/Tasks/TaskRepositoryInterface.php`
- **Estilo requerido**: `declare(strict_types=1);`, namespace `App\Domain\Alerts` y `DateTimeImmutable` para timestamps.
- **Entidad objetivo**: `Alert` con el conjunto de propiedades del brief y validación de tipo
- **Contrato objetivo**: `AlertRepositoryInterface` con operaciones para listado por hogar, persistencia, descarte individual y descarte masivo
- **Cobertura existente**: el backend ya sigue el patrón de dominio por entidad + interfaz de repositorio, pero no existe un módulo `Alerts`

## Requisitos

- **REQ-001**: crear el directorio `home-backend/src/Domain/Alerts/` y declarar el namespace `App\Domain\Alerts` en todos los archivos del módulo.
- **REQ-002**: la entidad `Alert` debe incluir `id` (`int`), `householdId` (`string`), `memberId` (`?int`), `title` (`string`), `body` (`string`), `icon` (`string`), `urgent` (`bool`), `status` (`'unread' | 'dismissed'`) y `createdAt` (`DateTimeImmutable`).
- **REQ-003**: el estado de la alerta debe representarse como un tipo de valor seguro y explícito, manteniendo la semántica de `unread` y `dismissed`.
- **REQ-004**: la interfaz debe exponer `findByHousehold(string $householdId): array`, `save(Alert $alert): Alert`, `dismiss(int $id): void` y `dismissAll(string $householdId): void`.
- **REQ-005**: la firma del repositorio debe ser consistente con el estilo existente del dominio Tasks y con la convención de inyección por interfaz.
- **REQ-006**: la entidad debe ser inmutable en lo que se pueda, siguiendo el patrón de los agregados del backend, y debe preservar `createdAt` como timestamp fijo.
- **REQ-007**: el módulo no debe incluir lógica de infraestructura ni controladores; debe quedar limitado al dominio y a la definición del contrato de persistencia.

## Tareas

| ID | Tarea | Prioridad | Dependencias | Archivos afectados | Complejidad |
|---|---|---|---|---|---|
| T1 | Crear la estructura del módulo `Domain/Alerts` y preparar el namespace y `strict_types`. | Alta | Ninguna | `home-backend/src/Domain/Alerts/` | Baja |
| T2 | Definir la entidad `Alert` con las propiedades requeridas y validaciones de tipo. | Alta | T1 | `home-backend/src/Domain/Alerts/Alert.php` | Media |
| T3 | Definir `AlertRepositoryInterface` con la API pública del repositorio. | Alta | T1 | `home-backend/src/Domain/Alerts/AlertRepositoryInterface.php` | Media |
| T4 | Revisar la alineación con el estilo de `Task` y `TaskRepositoryInterface` para evitar divergencias de contrato. | Media | T2-T3 | `home-backend/src/Domain/Tasks/*`, `home-backend/src/Domain/Alerts/*` | Baja |
| T5 | Validar que no se introducen piezas de infraestructura o HTTP fuera del alcance. | Media | T1-T4 | `home-backend/src/Domain/Alerts/*` | Baja |
| T6 | Ejecutar la validación del dominio y confirmar que la API propuesta es coherente con el backend actual. | Media | T1-T5 | `home-backend/tests` y `home-backend/src/Domain/Alerts/*` | Baja |

## Pasos de implementación

### Paso 1: Estructura del módulo

- Crear la carpeta `home-backend/src/Domain/Alerts/`.
- Asegurar que cada archivo tenga `declare(strict_types=1);`.
- Declarar `namespace App\Domain\Alerts;` en cada clase e interfaz.

### Paso 2: Entidad `Alert`

- Definir la clase `Alert` con los campos solicitados en el brief.
- Usar `DateTimeImmutable` para `createdAt`.
- Mantener tipos estrictos y un constructor claro con valores requeridos y opcionales bien definidos.
- Representar `status` con el conjunto restringido `unread` o `dismissed`.
- Incluir getters de acceso para cada propiedad, siguiendo el estilo del dominio Tasks.

### Paso 3: Contrato de repositorio

- Crear `AlertRepositoryInterface` con la API pública del dominio.
- Definir `findByHousehold(string $householdId): array` para recuperar alertas del hogar.
- Definir `save(Alert $alert): Alert` para persistir la entidad y devolverla con el identificador/estado final.
- Definir `dismiss(int $id): void` para marcar una alerta como descartada por id.
- Definir `dismissAll(string $householdId): void` para limpiar alertas por hogar.

### Paso 4: Alineación con el estilo existente

- Revisar `Task.php` y `TaskRepositoryInterface.php` para mantener una convención coherente.
- Mantener nombres y firmas muy similares al patrón del dominio actual.
- Evitar introducir tipos u operaciones extras que no estén justificadas por el brief.

### Paso 5: Validación de alcance

- Confirmar que no se crean implementaciones de infraestructura, endpoints ni adaptadores de HTTP.
- Confirmar que la lógica del módulo se limita a la entidad y al contrato de persistencia.

## Criterios de aceptación

- Existe el directorio `home-backend/src/Domain/Alerts/` con `Alert.php` y `AlertRepositoryInterface.php`.
- Ambos archivos usan `declare(strict_types=1);` y `namespace App\Domain\Alerts`.
- La entidad `Alert` expone exactamente los campos del brief con sus tipos esperados.
- El `status` acepta solo `unread` y `dismissed`.
- La interfaz del repositorio incluye los cuatro métodos requeridos y no más.
- El módulo no incluye implementaciones ni dependencias de infraestructura.
- El diseño es coherente con `App\Domain\Tasks`.

## Validación

1. Comprobar que el archivo `Alert.php` cumple con los tipos esperados y los getters del dominio.
2. Comprobar que `AlertRepositoryInterface.php` coincide con la firma pedida por el brief.
3. Revisar que el nombre `App\Domain\Alerts` y el `strict_types` están presentes en ambos archivos.
4. Confirmar que no existe trabajo fuera del alcance del módulo de dominio.

## Orden y dependencias

`T1 -> T2 -> T3 -> T4 -> T5 -> T6`

El orden sigue el flujo natural: primero se crea la estructura del módulo, luego la entidad, luego el contrato, y después se validan consistencia y alcance.

## Suposiciones y decisiones pendientes

- Se asume que el estilo del dominio `Tasks` es la referencia autorizada para la implementación del módulo `Alerts`.
- Se asume que las implementaciones concretas de repositorio se harán en una fase posterior, no en este brief ni en este plan.
- Se asume que `status` puede representarse como un string literal restringido en tipos o como un enum/constante del dominio según la convención que el backend tenga adoptada en la práctica.
- Se asume que el proyecto no requiere endpoints ni casos de uso de aplicación para esta fase, porque el brief los excluye explícitamente.

## Ejecución

- El trabajo está acotado al dominio y a la definición del contrato de persistencia.
- El objetivo es dejar un módulo `Alerts` listo para que la infraestructura de persistencia y la capa de aplicación se puedan construir sobre él en una fase posterior.
- La entrega principal es la entidad `Alert` y la interfaz `AlertRepositoryInterface` con la API solicitada y respetando el estilo del proyecto.

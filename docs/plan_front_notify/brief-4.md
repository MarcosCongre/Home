3# Brief: Tipo y cliente HTTP frontend para Alerts

## Solicitud original
Definir el tipo frontend `Alert` y crear un cliente HTTP para listar y descartar alertas, siguiendo los patrones existentes de tipos de dominio y `taskApi`.

## Objetivo
Proporcionar al frontend un contrato tipado para las alertas y funciones de acceso a los endpoints de Alerts usando la configuración de API y hogar existente.

## Enfoque propuesto
Añadir `src/domain/alerts/alert.ts` como definición del modelo y `src/infrastructure/http/alertApi.ts` como cliente de transporte. Usar `request()` de `apiClient.ts`, `environment.apiBaseUrl` y `environment.householdId`, siguiendo el estilo de `taskApi.ts`.

## Alcance
### Incluido
- Exportar el tipo `Alert` con `id: number`, `title: string`, `body: string`, `icon: string`, `time: string`, `urgent: boolean`, `user: string | null` y `status: 'unread' | 'dismissed'`.
- Mantener el modelo alineado con los campos que consume la UI desde `NOTIFS` en `src/infrastructure/demo/demoData.ts`.
- Exportar `listAlerts(): Promise<Alert[]>`, consultando `GET /alerts` con `householdId` obtenido de `environment.householdId`.
- Exportar `dismissAlert(id: number): Promise<void>`, llamando `PATCH /alerts/{id}/dismiss`.
- Exportar `dismissAllAlerts(): Promise<void>`, llamando `POST /alerts/dismiss-all` con `{ householdId }` en el body JSON.
- Construir las URLs desde `environment.apiBaseUrl` y realizar las solicitudes mediante `request()` de `apiClient.ts`.

### Excluido
- Cambios de pantallas o interacciones de UI para mostrar y descartar alertas, salvo los cambios estrictamente necesarios para alinear los datos demo con el tipo.
- Cambios en el backend o en la persistencia.

## Supuestos
- El endpoint de listado devuelve elementos compatibles con el tipo `Alert` solicitado, incluidos `time`, `user` y `status`.
- Las operaciones de descarte no necesitan procesar un cuerpo de respuesta; `request<void>` ya contempla respuestas HTTP 204.

## Preguntas abiertas
- En `NOTIFS`, los `id` actuales son cadenas como `n1`, pero el tipo solicitado requiere `number`; además, los elementos demo no incluyen `status`. Para mantener el requisito de coincidencia, hay que decidir si ajustar los fixtures o mapearlos a `Alert` en una capa de adaptación.
- El campo `user` de `NOTIFS` contiene iniciales como `J` o `L`, mientras que la API backend se especifica para devolver el nombre del miembro; confirmar si la UI debe mostrar iniciales o el nombre completo al usar datos reales.

## Siguiente paso
Usar este brief como entrada para `orch-dev`, que elaborará el plan técnico y decidirá cómo alinear los fixtures actuales con el contrato frontend/backend antes de implementar.
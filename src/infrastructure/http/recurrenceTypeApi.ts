import type { RecurrenceType, RecurrenceTypeDraft } from '@/domain/catalogs/catalog'
import { environment } from '@/infrastructure/config/environment'
import { request } from '@/infrastructure/http/apiClient'

type RecurrenceTypeDto = {
  id: number
  householdId?: string
  name: string
  intervalDays: number
}

const baseUrl = () => `${environment.apiBaseUrl}/recurrence-types`
const householdQuery = () => `householdId=${encodeURIComponent(environment.householdId)}`

function normalizeRecurrenceType(dto: RecurrenceTypeDto): RecurrenceType {
  if (!Number.isInteger(dto.id) || dto.id <= 0) {
    throw new Error('Recurrence type id must be a positive integer')
  }

  return {
    id: dto.id,
    householdId: dto.householdId ?? environment.householdId,
    name: dto.name,
    intervalDays: dto.intervalDays,
  }
}

// The first list for a household seeds the default recurrence types.
export async function listRecurrenceTypes(): Promise<RecurrenceType[]> {
  const payload = await request<RecurrenceTypeDto[]>(`${baseUrl()}?${householdQuery()}`)
  return payload.map(normalizeRecurrenceType)
}

export async function createRecurrenceType(draft: RecurrenceTypeDraft): Promise<RecurrenceType> {
  const payload = await request<RecurrenceTypeDto>(baseUrl(), {
    method: 'POST',
    body: JSON.stringify({ householdId: environment.householdId, name: draft.name, intervalDays: draft.intervalDays }),
  })
  return normalizeRecurrenceType(payload)
}

// PATCH replaces the whole entry: always send the name and the interval.
export async function updateRecurrenceType(id: number, draft: RecurrenceTypeDraft): Promise<RecurrenceType> {
  const payload = await request<RecurrenceTypeDto>(`${baseUrl()}/${encodeURIComponent(id)}?${householdQuery()}`, {
    method: 'PATCH',
    body: JSON.stringify({ householdId: environment.householdId, name: draft.name, intervalDays: draft.intervalDays }),
  })
  return normalizeRecurrenceType(payload)
}

export async function deleteRecurrenceType(id: number): Promise<void> {
  await request<void>(`${baseUrl()}/${encodeURIComponent(id)}?${householdQuery()}`, { method: 'DELETE' })
}

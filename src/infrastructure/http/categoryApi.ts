import type { Category, CategoryDraft } from '@/domain/catalogs/catalog'
import { environment } from '@/infrastructure/config/environment'
import { request } from '@/infrastructure/http/apiClient'

type CategoryDto = {
  id: number
  householdId?: string
  name: string
  color?: string | null
}

const baseUrl = () => `${environment.apiBaseUrl}/categories`
const householdQuery = () => `householdId=${encodeURIComponent(environment.householdId)}`

function normalizeCategory(dto: CategoryDto): Category {
  if (!Number.isInteger(dto.id) || dto.id <= 0) {
    throw new Error('Category id must be a positive integer')
  }

  return {
    id: dto.id,
    householdId: dto.householdId ?? environment.householdId,
    name: dto.name,
    color: dto.color ?? null,
  }
}

// The first list for a household seeds the default categories.
export async function listCategories(): Promise<Category[]> {
  const payload = await request<CategoryDto[]>(`${baseUrl()}?${householdQuery()}`)
  return payload.map(normalizeCategory)
}

export async function createCategory(draft: CategoryDraft): Promise<Category> {
  const payload = await request<CategoryDto>(baseUrl(), {
    method: 'POST',
    body: JSON.stringify({ householdId: environment.householdId, name: draft.name, color: draft.color }),
  })
  return normalizeCategory(payload)
}

// PATCH replaces the whole entry: always send the name and the current color (null clears it).
export async function updateCategory(id: number, draft: CategoryDraft): Promise<Category> {
  const payload = await request<CategoryDto>(`${baseUrl()}/${encodeURIComponent(id)}?${householdQuery()}`, {
    method: 'PATCH',
    body: JSON.stringify({ householdId: environment.householdId, name: draft.name, color: draft.color }),
  })
  return normalizeCategory(payload)
}

export async function deleteCategory(id: number): Promise<void> {
  await request<void>(`${baseUrl()}/${encodeURIComponent(id)}?${householdQuery()}`, { method: 'DELETE' })
}

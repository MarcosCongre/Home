// Household catalogs: the categories and recurrence types tasks pick from.

export type Category = {
  id: number
  householdId: string
  name: string
  color: string | null
}

export type RecurrenceType = {
  id: number
  householdId: string
  name: string
  intervalDays: number
}

export type CategoryDraft = Pick<Category, 'name' | 'color'>
export type RecurrenceTypeDraft = Pick<RecurrenceType, 'name' | 'intervalDays'>

// Same limits the backend enforces (CatalogName, RecurrenceType, Category).
export const CATALOG_NAME_MAX_LENGTH = 64
export const MIN_INTERVAL_DAYS = 1
export const MAX_INTERVAL_DAYS = 365

const HEX_COLOR = /^#[0-9A-Fa-f]{6}$/

export type CatalogNameError = 'required' | 'tooLong'

export function validateCatalogName(name: string): CatalogNameError | null {
  const trimmed = name.trim()
  if (trimmed === '') return 'required'
  // Count code points like the backend's mb_strlen.
  if ([...trimmed].length > CATALOG_NAME_MAX_LENGTH) return 'tooLong'
  return null
}

export function isValidColor(color: string | null): boolean {
  return color === null || HEX_COLOR.test(color)
}

export function isValidIntervalDays(value: number): boolean {
  return Number.isInteger(value) && value >= MIN_INTERVAL_DAYS && value <= MAX_INTERVAL_DAYS
}

// Names are unique per household regardless of case.
export function sameCatalogName(a: string, b: string): boolean {
  return a.trim().toLowerCase() === b.trim().toLowerCase()
}

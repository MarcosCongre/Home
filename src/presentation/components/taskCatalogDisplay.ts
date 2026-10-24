import type { Category, RecurrenceType } from '@/domain/catalogs/catalog'
import type { Task } from '@/domain/tasks/task'
import { categoryLabel, es, recurrenceLabel } from '@/presentation/i18n/es'

// Badge color for a task without a (resolvable) colored category.
export const NEUTRAL_CATEGORY_COLOR = '#888'

export type TaskCatalogs = {
  categories: Category[]
  recurrenceTypes: RecurrenceType[]
}

const byId = <T extends { id: number }>(items: T[], id: number | null) =>
  id === null ? undefined : items.find(item => item.id === id)

// Catalog name by id first (reflects renames), then the API-resolved name.
export function taskCategoryText(task: Task, categories: Category[]): string {
  const name = byId(categories, task.categoryId)?.name ?? task.category
  return name ? categoryLabel(name) : es.catalogs.noCategory
}

export function taskRecurrenceText(task: Task, recurrenceTypes: RecurrenceType[]): string {
  const name = byId(recurrenceTypes, task.recurrenceId)?.name ?? task.recurrence
  return name ? recurrenceLabel(name) : es.catalogs.once
}

export function taskCategoryColor(task: Task, categories: Category[]): string {
  return byId(categories, task.categoryId)?.color ?? NEUTRAL_CATEGORY_COLOR
}

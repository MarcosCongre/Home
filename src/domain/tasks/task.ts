export type TaskPriority = 'low' | 'med' | 'high'

export type Task = {
  id: number
  title: string
  assignee: number | ''
  // References into the household catalogs; null means none / one-off.
  categoryId: number | null
  recurrenceId: number | null
  // Names the API resolved for those ids (null when unset or dangling).
  // Display prefers the household catalogs by id and falls back to these.
  category: string | null
  recurrence: string | null
  done: boolean
  day?: string
  time?: string
  priority: TaskPriority
  history: string[]
  // Done several times a day: completing it hands it back as pending.
  repeatable: boolean
}

// The fields a user edits on a task.
export type TaskEditableFields = Pick<
  Task,
  'title' | 'assignee' | 'categoryId' | 'recurrenceId' | 'priority' | 'repeatable'
> & { day: string; time: string }

export type TaskChanges = Partial<TaskEditableFields>

const EDITABLE_KEYS: readonly (keyof TaskEditableFields)[] = [
  'title', 'assignee', 'day', 'time', 'categoryId', 'recurrenceId', 'priority', 'repeatable',
]

// Keeps only the fields that differ from the original task. The backend
// rejects a dangling catalog id even when it is already stored, so an
// unchanged categoryId/recurrenceId must never be re-sent.
export function taskChanges(original: Task, edited: TaskChanges): TaskChanges {
  const changes: Record<string, unknown> = {}
  for (const key of EDITABLE_KEYS) {
    if (!(key in edited)) continue
    const next = edited[key]
    const current = original[key] ?? (key === 'day' || key === 'time' ? '' : original[key])
    if (next !== current) changes[key] = next
  }
  return changes as TaskChanges
}

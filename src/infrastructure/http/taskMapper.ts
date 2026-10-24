import type { Task } from '@/domain/tasks/task'

export type TaskDto = {
  id: number
  title: string
  status: 'pending' | 'completed'
  householdId: string
  createdAt: string
  completedAt: string | null
  lastAssignedAt?: string | null
  assignedMemberId?: number | null
  assignee?: number | null
  categoryId?: number | null
  recurrenceId?: number | null
  // Read-only names the backend resolved for the ids.
  category?: string | null
  recurrence?: string | null
  day?: string | null
  time?: string | null
  priority?: Task['priority'] | null
  history?: string[]
  repeatable?: boolean
}

export function mapTaskDto(dto: TaskDto): Task {
  if (!Number.isInteger(dto.id) || dto.id <= 0) {
    throw new Error('Task id must be a positive integer')
  }

  return {
    id: dto.id,
    title: dto.title,
    assignee: dto.assignee ?? dto.assignedMemberId ?? '',
    categoryId: catalogId(dto.categoryId),
    recurrenceId: catalogId(dto.recurrenceId),
    category: dto.category ?? null,
    recurrence: dto.recurrence ?? null,
    done: dto.status === 'completed',
    day: dto.day ?? undefined,
    time: dto.time ?? undefined,
    priority: dto.priority ?? 'low',
    history: dto.history ?? [],
    repeatable: dto.repeatable ?? false,
  }
}

function catalogId(value: number | null | undefined): number | null {
  return typeof value === 'number' && Number.isInteger(value) && value > 0 ? value : null
}

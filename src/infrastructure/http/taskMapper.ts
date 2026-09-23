import type { Task } from '@/domain/tasks/task'

export type TaskDto = {
  id: number
  title: string
  status: 'pending' | 'completed'
  householdId: string
  createdAt: string
  completedAt: string | null
  assignedMemberId?: number | null
  assignee?: number | null
  category?: string | null
  recurrence?: string | null
  day?: string | null
  time?: string | null
  priority?: Task['priority'] | null
  history?: string[]
}

export function mapTaskDto(dto: TaskDto): Task {
  if (!Number.isInteger(dto.id) || dto.id <= 0) {
    throw new Error('Task id must be a positive integer')
  }

  return {
    id: dto.id,
    title: dto.title,
    assignee: dto.assignee ?? dto.assignedMemberId ?? '',
    category: dto.category ?? 'Uncategorized',
    recurrence: dto.recurrence ?? 'Once',
    done: dto.status === 'completed',
    day: dto.day ?? undefined,
    time: dto.time ?? undefined,
    priority: dto.priority ?? 'low',
    history: dto.history ?? [],
  }
}

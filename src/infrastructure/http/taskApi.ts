import { environment } from '@/infrastructure/config/environment'
import { request } from '@/infrastructure/http/apiClient'
import { mapTaskDto, type TaskDto } from '@/infrastructure/http/taskMapper'
import type { Task, TaskChanges, TaskEditableFields } from '@/domain/tasks/task'

export async function listTasks(): Promise<Task[]> {
  const payload = await request<TaskDto[]>(`${environment.apiBaseUrl}/tasks?householdId=${encodeURIComponent(environment.householdId)}`)
  return payload.map(mapTaskDto)
}

// nextAssigneeId only matters for repeatable tasks: who does it next.
export async function completeTask(taskId: number, userId: number, nextAssigneeId?: number): Promise<Task> {
  const payload = await request<TaskDto>(`${environment.apiBaseUrl}/tasks/${encodeURIComponent(taskId)}/complete`, {
    method: 'PATCH',
    body: JSON.stringify({ userId, nextAssigneeId: nextAssigneeId ?? null }),
  })

  return mapTaskDto(payload)
}

export async function reopenTask(taskId: number): Promise<Task> {
  const payload = await request<TaskDto>(`${environment.apiBaseUrl}/tasks/${encodeURIComponent(taskId)}/reopen`, {
    method: 'PATCH',
  })

  return mapTaskDto(payload)
}

export async function autoAssignTasks(): Promise<Task[]> {
  const payload = await request<TaskDto[]>(`${environment.apiBaseUrl}/tasks/auto-assign`, {
    method: 'POST',
    body: JSON.stringify({ householdId: environment.householdId }),
  })

  return payload.map(mapTaskDto)
}

export async function createTask(data: TaskEditableFields): Promise<Task> {
  const payload = await request<TaskDto>(`${environment.apiBaseUrl}/tasks`, {
    method: 'POST',
    body: JSON.stringify({
      title: data.title,
      householdId: environment.householdId,
      assignedMemberId: data.assignee || null,
      day: data.day,
      time: data.time,
      categoryId: data.categoryId,
      recurrenceId: data.recurrenceId,
      priority: data.priority,
      repeatable: data.repeatable
    }),
  })

  return mapTaskDto(payload)
}

// PATCH semantics: an absent field keeps its stored value. Callers pass only
// what changed (see taskChanges); an explicit null clears a catalog reference.
export async function updateTask(
  taskId: number,
  changes: TaskChanges & { status?: TaskDto['status'] }
): Promise<Task> {
  const body: Record<string, unknown> = {}
  if (changes.title !== undefined) body.title = changes.title
  if (changes.assignee !== undefined) body.assignedMemberId = changes.assignee || null
  if (changes.status !== undefined) body.status = changes.status
  if (changes.day !== undefined) body.day = changes.day
  if (changes.time !== undefined) body.time = changes.time
  if (changes.categoryId !== undefined) body.categoryId = changes.categoryId
  if (changes.recurrenceId !== undefined) body.recurrenceId = changes.recurrenceId
  if (changes.priority !== undefined) body.priority = changes.priority
  if (changes.repeatable !== undefined) body.repeatable = changes.repeatable

  const payload = await request<TaskDto>(`${environment.apiBaseUrl}/tasks/${encodeURIComponent(taskId)}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  })

  return mapTaskDto(payload)
}

export async function deleteTask(taskId: number): Promise<void> {
  await request<void>(`${environment.apiBaseUrl}/tasks/${encodeURIComponent(taskId)}`, {
    method: 'DELETE',
  })
}

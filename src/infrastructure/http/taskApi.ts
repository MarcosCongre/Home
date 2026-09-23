import { environment } from '@/infrastructure/config/environment'
import { request } from '@/infrastructure/http/apiClient'
import { mapTaskDto, type TaskDto } from '@/infrastructure/http/taskMapper'
import type { Task } from '@/domain/tasks/task'

export async function listTasks(): Promise<Task[]> {
  const payload = await request<TaskDto[]>(`${environment.apiBaseUrl}/tasks?householdId=${encodeURIComponent(environment.householdId)}`)
  return payload.map(mapTaskDto)
}

export async function completeTask(taskId: string, userId: string): Promise<Task> {
  const payload = await request<TaskDto>(`${environment.apiBaseUrl}/tasks/${encodeURIComponent(taskId)}/complete`, {
    method: 'PATCH',
    body: JSON.stringify({ userId }),
  })

  return mapTaskDto(payload)
}

export async function createTask(data: { title: string; assignee?: string }): Promise<Task> {
  const payload = await request<TaskDto>(`${environment.apiBaseUrl}/tasks`, {
    method: 'POST',
    body: JSON.stringify({
      title: data.title,
      householdId: environment.householdId,
      assignedMemberId: data.assignee || null,
    }),
  })

  return mapTaskDto(payload)
}

export async function updateTask(taskId: string, data: { title?: string; assignee?: string; status?: TaskDto['status'] }): Promise<Task> {
  const payload = await request<TaskDto>(`${environment.apiBaseUrl}/tasks/${encodeURIComponent(taskId)}`, {
    method: 'PATCH',
    body: JSON.stringify({
      ...data,
      assignedMemberId: data.assignee || null,
    }),
  })

  return mapTaskDto(payload)
}

export async function deleteTask(taskId: string): Promise<void> {
  await request<void>(`${environment.apiBaseUrl}/tasks/${encodeURIComponent(taskId)}`, {
    method: 'DELETE',
  })
}

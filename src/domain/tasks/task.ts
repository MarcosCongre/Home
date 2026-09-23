export type TaskPriority = 'low' | 'med' | 'high'

export type Task = {
  id: string
  title: string
  assignee: string
  category: string
  recurrence: string
  done: boolean
  day?: string
  time?: string
  priority: TaskPriority
  history: string[]
}

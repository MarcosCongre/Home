export type TaskPriority = 'low' | 'med' | 'high'

export type Task = {
  id: number
  title: string
  assignee: number | ''
  category: string
  recurrence: string
  done: boolean
  day?: string
  time?: string
  priority: TaskPriority
  history: string[]
}

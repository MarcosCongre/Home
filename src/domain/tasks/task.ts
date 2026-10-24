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
  // Done several times a day: completing it hands it back as pending.
  repeatable: boolean
}

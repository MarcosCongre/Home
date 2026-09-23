import { useCallback, useEffect, useReducer } from 'react'

import type { Task } from '@/domain/tasks/task'
import { INIT_TASKS } from '@/infrastructure/demo/demoData'
import { environment } from '@/infrastructure/config/environment'
import { completeTask as completeTaskRequest, createTask as createTaskRequest, deleteTask as deleteTaskRequest, listTasks, updateTask as updateTaskRequest } from '@/infrastructure/http/taskApi'

type TaskState = {
  tasks: Task[]
  isLoading: boolean
  isEmpty: boolean
  error: string | null
}

type TaskAction =
  | { type: 'load-start' }
  | { type: 'load-success'; tasks: Task[] }
  | { type: 'load-failure' }
  | { type: 'set-tasks'; tasks: Task[] }
  | { type: 'update-task'; taskId: string; updater: (task: Task) => Task }
  | { type: 'replace-task'; task: Task }

const initialState: TaskState = {
  tasks: environment.demoMode ? INIT_TASKS : [],
  isLoading: false,
  isEmpty: environment.demoMode ? INIT_TASKS.length === 0 : true,
  error: null,
}

function taskReducer(state: TaskState, action: TaskAction): TaskState {
  switch (action.type) {
    case 'load-start':
      return { ...state, isLoading: true, error: null }

    case 'load-success':
      return {
        tasks: action.tasks,
        isLoading: false,
        isEmpty: action.tasks.length === 0,
        error: null,
      }

    case 'load-failure':
      return {
        tasks: [],
        isLoading: false,
        isEmpty: true,
        error: 'Unable to load tasks. Check the API connection and retry.',
      }

    case 'set-tasks':
      return {
        ...state,
        tasks: action.tasks,
        isEmpty: action.tasks.length === 0,
      }

    case 'update-task': {
      const nextTasks = state.tasks.map(task =>
        task.id === action.taskId ? action.updater(task) : task,
      )

      return {
        ...state,
        tasks: nextTasks,
        isEmpty: nextTasks.length === 0,
      }
    }

    case 'replace-task': {
      const nextTasks = state.tasks.map(task =>
        task.id === action.task.id ? action.task : task,
      )

      return {
        ...state,
        tasks: nextTasks,
        isEmpty: nextTasks.length === 0,
      }
    }

    default:
      return state
  }
}

export function useTasks() {
  const [state, dispatch] = useReducer(taskReducer, initialState)

  const setTasks = useCallback((tasks: Task[]) => {
    dispatch({ type: 'set-tasks', tasks })
  }, [])

  const updateTaskOptimistically = useCallback((taskId: string, updater: (task: Task) => Task) => {
    dispatch({ type: 'update-task', taskId, updater })
  }, [])

  const replaceTask = useCallback((task: Task) => {
    dispatch({ type: 'replace-task', task })
  }, [])

  const loadTasks = useCallback(async () => {
    dispatch({ type: 'load-start' })

    try {
      const mapped = await listTasks()
      dispatch({
        type: 'load-success',
        tasks: mapped,
      })
    } catch {
      dispatch({ type: 'load-failure' })
    }
  }, [])

  const createTask = useCallback(async (title: string, assignee = '') => {
    const created = await createTaskRequest({ title, assignee })
    dispatch({ type: 'set-tasks', tasks: [...state.tasks, created] })
    return created
  }, [state.tasks])

  const updateTaskRequestById = useCallback(async (taskId: string, data: { title?: string; assignee?: string; status?: 'pending' | 'completed' }) => {
    const updated = await updateTaskRequest(taskId, data)
    dispatch({ type: 'replace-task', task: updated })
    return updated
  }, [])

  const deleteTask = useCallback(async (taskId: string) => {
    await deleteTaskRequest(taskId)
    dispatch({ type: 'set-tasks', tasks: state.tasks.filter(task => task.id !== taskId) })
  }, [state.tasks])

  const completeTaskById = useCallback(async (taskId: string, userId: string) => {
    const current = state.tasks.find(task => task.id === taskId)
    if (!current) return

    const nextDone = !current.done

    updateTaskOptimistically(taskId, task => ({ ...task, done: nextDone }))

    if (!nextDone) {
      return
    }

    try {
      const completedTask = await completeTaskRequest(taskId, userId)
      replaceTask(completedTask)
    } catch {
      updateTaskOptimistically(taskId, () => current)
    }
  }, [replaceTask, state.tasks, updateTaskOptimistically])

  useEffect(() => {
    void loadTasks()
  }, [loadTasks])

  return {
    tasks: state.tasks,
    isLoading: state.isLoading,
    isEmpty: state.isEmpty,
    error: state.error,
    setTasks,
    updateTask: updateTaskRequestById,
    replaceTask,
    loadTasks,
    createTask,
    deleteTask,
    completeTaskById,
  }
}

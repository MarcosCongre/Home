import { useCallback, useEffect, useReducer } from 'react'

import { taskChanges, type Task, type TaskChanges, type TaskEditableFields } from '@/domain/tasks/task'
import { INIT_TASKS } from '@/infrastructure/demo/demoData'
import { environment } from '@/infrastructure/config/environment'
import { autoAssignTasks as autoAssignTasksRequest, completeTask as completeTaskRequest, createTask as createTaskRequest, deleteTask as deleteTaskRequest, listTasks, reopenTask as reopenTaskRequest, updateTask as updateTaskRequest } from '@/infrastructure/http/taskApi'

type TaskState = {
  tasks: Task[]
  isLoading: boolean
  isEmpty: boolean
  error: string | null
  isAutoAssigning: boolean
  autoAssignFailed: boolean
}

type TaskAction =
  | { type: 'load-start' }
  | { type: 'load-success'; tasks: Task[] }
  | { type: 'load-failure' }
  | { type: 'set-tasks'; tasks: Task[] }
  | { type: 'update-task'; taskId: number; updater: (task: Task) => Task }
  | { type: 'replace-task'; task: Task }
  | { type: 'auto-assign-start' }
  | { type: 'auto-assign-success'; tasks: Task[] }
  | { type: 'auto-assign-failure' }

function demoTask(id: number, data: TaskEditableFields): Task {
  return { id, ...data, category: null, recurrence: null, done: false, history: [] }
}

const initialState: TaskState = {
  tasks: environment.demoMode ? INIT_TASKS : [],
  isLoading: false,
  isEmpty: environment.demoMode ? INIT_TASKS.length === 0 : true,
  error: null,
  isAutoAssigning: false,
  autoAssignFailed: false,
}

function taskReducer(state: TaskState, action: TaskAction): TaskState {
  switch (action.type) {
    case 'load-start':
      return { ...state, isLoading: true, error: null, autoAssignFailed: false }

    case 'load-success':
      return {
        ...state,
        tasks: action.tasks,
        isLoading: false,
        isEmpty: action.tasks.length === 0,
        error: null,
      }

    case 'load-failure':
      return {
        ...state,
        tasks: [],
        isLoading: false,
        isEmpty: true,
        error: 'No se pudieron cargar las tareas. Verifica la conexión con la API e intenta de nuevo.',
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

    case 'auto-assign-start':
      return { ...state, isAutoAssigning: true, autoAssignFailed: false }

    case 'auto-assign-success': {
      const updatedById = new Map(action.tasks.map(task => [task.id, task]))
      const nextTasks = state.tasks.map(task => updatedById.get(task.id) ?? task)

      return {
        ...state,
        tasks: nextTasks,
        isEmpty: nextTasks.length === 0,
        isAutoAssigning: false,
      }
    }

    case 'auto-assign-failure':
      return { ...state, isAutoAssigning: false, autoAssignFailed: true }

    default:
      return state
  }
}

export function useTasks() {
  const [state, dispatch] = useReducer(taskReducer, initialState)

  const setTasks = useCallback((tasks: Task[]) => {
    dispatch({ type: 'set-tasks', tasks })
  }, [])

  const updateTaskOptimistically = useCallback((taskId: number, updater: (task: Task) => Task) => {
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

  const createTask = useCallback(
    async (data: TaskEditableFields) => {
      // Demo mode has no backend: the task lives in memory until reload.
      const created = environment.demoMode
        ? demoTask(Math.max(0, ...state.tasks.map(task => task.id)) + 1, data)
        : await createTaskRequest(data)
      const taskWithFormData = {
        ...created,
        ...data,
        assignee: data.assignee,
      }

      dispatch({
        type: 'set-tasks',
        tasks: [...state.tasks, taskWithFormData]
      })
    }, [state.tasks]
  )

  // Sends only the fields that differ from the stored task, so an unchanged
  // (possibly dangling) catalog id is never re-sent.
  const updateTaskRequestById = useCallback(
    async (taskId: number, edited: TaskChanges) => {
      const original = state.tasks.find(task => task.id === taskId)
      if (!original) return

      const changes = taskChanges(original, edited)
      if (Object.keys(changes).length === 0) return

      const updated = environment.demoMode
        ? original
        : await updateTaskRequest(taskId, changes)
      dispatch({
        type: 'replace-task',
        task: { ...updated, ...changes },
      })
    }, [state.tasks]
  )

  const deleteTask = useCallback(async (taskId: number) => {
    await deleteTaskRequest(taskId)
    dispatch({
      type: 'set-tasks',
      tasks: state.tasks.filter(task => task.id !== taskId) })
  }, [state.tasks])

  const completeTaskById = useCallback(
    async (
      taskId: number,
      userId: number,
      nextAssigneeId?: number
    ) => {
      const current = state.tasks.find(task => task.id === taskId)
      if (!current) return

      const nextDone = !current.done

      // A repeatable task stays pending and only changes hands.
      updateTaskOptimistically(taskId, task => current.repeatable && nextDone
        ? { ...task, assignee: nextAssigneeId ?? task.assignee }
        : { ...task, done: nextDone })

      try {
        const savedTask = nextDone
          ? await completeTaskRequest(taskId, userId, current.repeatable ? nextAssigneeId : undefined)
          : await reopenTaskRequest(taskId)
        replaceTask(savedTask)
      } catch {
        updateTaskOptimistically(taskId, () => current)
      }
    }, [replaceTask, state.tasks, updateTaskOptimistically]
  )

  // Demo mode has no backend, so auto-assign is only offered against the API.
  const canAutoAssign = !environment.demoMode

  const autoAssign = useCallback(async () => {
    if (!canAutoAssign) return

    dispatch({ type: 'auto-assign-start' })

    try {
      const updated = await autoAssignTasksRequest()
      dispatch({ type: 'auto-assign-success', tasks: updated })
    } catch {
      dispatch({ type: 'auto-assign-failure' })
    }
  }, [canAutoAssign])

  useEffect(() => {
    void loadTasks()
  }, [loadTasks])

  return {
    tasks: state.tasks,
    isLoading: state.isLoading,
    isEmpty: state.isEmpty,
    error: state.error,
    isAutoAssigning: state.isAutoAssigning,
    autoAssignFailed: state.autoAssignFailed,
    canAutoAssign,
    autoAssign,
    setTasks,
    updateTask: updateTaskRequestById,
    replaceTask,
    loadTasks,
    createTask,
    deleteTask,
    completeTaskById,
  }
}

import { useCallback, useEffect, useReducer } from 'react'

import type { Alert } from '@/domain/alerts/alert'
import { environment } from '@/infrastructure/config/environment'
import { NOTIFS } from '@/infrastructure/demo/demoData'
import { dismissAlert as dismissAlertRequest, dismissAllAlerts as dismissAllAlertsRequest, listAlerts } from '@/infrastructure/http/alertApi'

type AlertState = {
  alerts: Alert[]
  isLoading: boolean
  error: string | null
}

type AlertAction =
  | { type: 'load-start' }
  | { type: 'load-success'; alerts: Alert[] }
  | { type: 'load-failure' }
  | { type: 'set-alerts'; alerts: Alert[] }
  | { type: 'dismiss-alert'; alertId: number }
  | { type: 'dismiss-all' }

const initialState: AlertState = {
  alerts: environment.demoMode ? NOTIFS : [],
  isLoading: false,
  error: null,
}

function alertReducer(state: AlertState, action: AlertAction): AlertState {
  switch (action.type) {
    case 'load-start':
      return { ...state, isLoading: true, error: null }

    case 'load-success':
      return {
        alerts: action.alerts,
        isLoading: false,
        error: null,
      }

    case 'load-failure':
      return {
        ...state,
        isLoading: false,
        error: 'Unable to load alerts. Check the API connection and retry.',
      }

    case 'set-alerts':
      return {
        ...state,
        alerts: action.alerts,
      }

    case 'dismiss-alert':
      return {
        ...state,
        alerts: state.alerts.filter(alert => alert.id !== action.alertId),
      }

    case 'dismiss-all':
      return {
        ...state,
        alerts: [],
      }

    default:
      return state
  }
}

export function useAlerts() {
  const [state, dispatch] = useReducer(alertReducer, initialState)

  const loadAlerts = useCallback(async () => {
    dispatch({ type: 'load-start' })

    try {
      const alerts = await listAlerts()
      dispatch({ type: 'load-success', alerts })
    } catch {
      dispatch({ type: 'load-failure' })
    }
  }, [])

  const dismissAlert = useCallback(async (alertId: number) => {
    dispatch({ type: 'dismiss-alert', alertId })

    try {
      await dismissAlertRequest(alertId)
    } catch {
      await loadAlerts()
    }
  }, [loadAlerts])

  const dismissAll = useCallback(async () => {
    dispatch({ type: 'dismiss-all' })

    try {
      await dismissAllAlertsRequest()
    } catch {
      await loadAlerts()
    }
  }, [loadAlerts])

  useEffect(() => {
    void loadAlerts()

    if (environment.demoMode) {
      return undefined
    }

    const intervalId = window.setInterval(() => {
      void loadAlerts()
    }, 15000)

    return () => window.clearInterval(intervalId)
  }, [loadAlerts])

  const unreadCount = state.alerts.filter(alert => alert.status === 'unread').length

  return {
    alerts: state.alerts,
    isLoading: state.isLoading,
    error: state.error,
    dismissAlert,
    dismissAll,
    loadAlerts,
    unreadCount,
  }
}

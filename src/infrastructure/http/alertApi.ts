import type { Alert } from '@/domain/alerts/alert'
import { environment } from '@/infrastructure/config/environment'
import { request } from '@/infrastructure/http/apiClient'

export async function listAlerts(): Promise<Alert[]> {
  return request<Alert[]>(`${environment.apiBaseUrl}/alerts?householdId=${encodeURIComponent(environment.householdId)}`)
}

export async function dismissAlert(alertId: number): Promise<void> {
  await request<void>(`${environment.apiBaseUrl}/alerts/${encodeURIComponent(alertId)}/dismiss`, {
    method: 'PATCH',
  })
}

export async function dismissAllAlerts(): Promise<void> {
  await request<void>(`${environment.apiBaseUrl}/alerts/dismiss-all`, {
    method: 'POST',
    body: JSON.stringify({ householdId: environment.householdId }),
  })
}

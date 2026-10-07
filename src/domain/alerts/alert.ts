export type AlertStatus = 'unread' | 'dismissed'

export type Alert = {
  id: number
  title: string
  body: string
  icon: string
  time: string
  urgent: boolean
  user: string | null
  status: AlertStatus
}

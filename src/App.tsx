import { useState } from 'react'

import { useAlerts } from '@/application/alerts/useAlerts'
import { useMembers } from '@/application/members/useMembers'
import { useTasks } from '@/application/tasks/useTasks'
import {
  CalendarView,
  Dashboard,
  Members,
  Notifications,
  TaskDetail,
} from '@/presentation/screens/homeScreens'
import { es } from '@/presentation/i18n/es'

const TABS = [
  { id: 'home', label: es.tabs.home, icon: '⌂' },
  { id: 'calendar', label: es.tabs.calendar, icon: '◫' },
  { id: 'alerts', label: es.tabs.alerts, icon: '◻' },
  { id: 'members', label: es.tabs.members, icon: '◎' },
]

export default function App() {
  const [tab, setTab] = useState('home')
  const [detailId, setDetailId] = useState<number | null>(null)
  const { users, addUser, updateUser, removeUser, loadMembers, error: memberError } = useMembers()
  const {
    tasks,
    setTasks,
    completeTaskById,
    loadTasks,
    createTask,
    updateTask,
    error: taskError,
    autoAssign,
    isAutoAssigning,
    autoAssignFailed,
    canAutoAssign,
  } = useTasks()
  const { alerts, dismissAlert, dismissAll, unreadCount } = useAlerts()
  const error = memberError ?? taskError ?? (autoAssignFailed ? es.dashboard.autoAssignFailed : null)

  const openDetail = (id: number) => {
    setDetailId(id)
    setTab('detail')
  }

  const backFromDetail = () => {
    setDetailId(null)
    setTab('home')
  }

  const selectTab = (id: string) => {
    setDetailId(null)
    setTab(id)
  }

  return (
    <div
      className="h-dvh flex flex-col md:flex-row bg-[#EDE4D4] text-[#3D2E1E]"
      style={{ paddingLeft: 'env(safe-area-inset-left)', paddingRight: 'env(safe-area-inset-right)' }}
    >
      {error && (
        <div
          className="fixed left-1/2 -translate-x-1/2 z-[60] max-w-[calc(100%-2rem)] rounded-2xl bg-[#3D2E1E] text-[#F6EFE3] pl-4 pr-1 py-0.5 text-xs font-medium shadow-md flex items-center gap-3"
          style={{ top: 'calc(env(safe-area-inset-top) + 0.75rem)' }}
        >
          <span>{error}</span>
          <button
            className="min-h-11 px-3 underline underline-offset-2 shrink-0"
            onClick={() => {
              void loadMembers()
              void loadTasks()
            }}
          >
            {es.common.retry}
          </button>
        </div>
      )}

      <nav
        className="hidden md:flex md:flex-col md:w-60 md:shrink-0 md:gap-1 md:px-4 md:py-6 bg-[#F6EFE3]"
        style={{ borderRight: '1px solid rgba(61,46,30,0.08)' }}
      >
        <span className="font-display text-xl px-3 pb-6">{es.appTitle}</span>
        {TABS.map(t => {
          const active = tab === t.id || (t.id === 'home' && tab === 'detail')

          return (
            <button
              key={t.id}
              onClick={() => selectTab(t.id)}
              className="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium text-left transition-colors"
              style={{
                color: active ? '#C4623A' : '#3D2E1E',
                background: active ? 'rgba(196,98,58,0.1)' : 'transparent',
              }}
            >
              <span className="text-lg w-6 text-center">{t.icon}</span>
              <span className="flex-1">{t.label}</span>
              {t.id === 'alerts' && unreadCount > 0 && (
                <span className="min-w-5 h-5 px-1 rounded-full bg-[#C4623A] text-white text-[11px] font-bold flex items-center justify-center">
                  {unreadCount}
                </span>
              )}
            </button>
          )
        })}
      </nav>

      <main
        className="flex-1 min-h-0 overflow-hidden bg-[#F6EFE3] md:bg-[#EDE4D4]"
        style={{ paddingTop: 'env(safe-area-inset-top)' }}
      >
        <div className="h-full w-full max-w-5xl mx-auto bg-[#EDE4D4] md:shadow-sm">
          {detailId && tab === 'detail' ? (
            <TaskDetail taskId={detailId} tasks={tasks} setTasks={setTasks} onBack={backFromDetail} users={users} />
          ) : tab === 'home' ? (
            <Dashboard
              tasks={tasks}
              onSelect={openDetail}
              users={users}
              onToggleComplete={completeTaskById}
              onAutoAssign={canAutoAssign ? autoAssign : undefined}
              isAutoAssigning={isAutoAssigning}
            />
          ) : tab === 'calendar' ? (
            <CalendarView
              tasks={tasks}
              onSelect={openDetail}
              users={users}
              onCreateTask={createTask}
              onUpdateTask={updateTask}
            />
          ) : tab === 'alerts' ? (
            <Notifications
              users={users}
              alerts={alerts}
              onDismiss={dismissAlert}
              onDismissAll={dismissAll}
            />
          ) : (
            <Members
              users={users}
              tasks={tasks}
              onAddUser={addUser}
              onUpdateUser={updateUser}
              onRemoveUser={removeUser}
              onUnassignTasks={(userId) => setTasks(tasks.map(t => t.assignee === userId ? { ...t, assignee: '' } : t))}
            />
          )}
        </div>
      </main>

      <nav
        className="md:hidden flex items-stretch px-2 pt-1 bg-white/80 shrink-0"
        style={{
          backdropFilter: 'blur(12px)',
          borderTop: '1px solid rgba(61,46,30,0.08)',
          paddingBottom: 'calc(env(safe-area-inset-bottom) + 0.5rem)',
        }}
      >
        {TABS.map(t => {
          const active = tab === t.id || (t.id === 'home' && tab === 'detail')

          return (
            <button
              key={t.id}
              onClick={() => selectTab(t.id)}
              className="flex-1 flex flex-col items-center justify-center min-h-12 py-1.5 relative"
            >
              {t.id === 'alerts' && unreadCount > 0 && (
                <span className="absolute top-1 left-1/2 ml-2 min-w-5 h-5 px-1 rounded-full bg-[#C4623A] text-white text-[11px] font-bold flex items-center justify-center">
                  {unreadCount}
                </span>
              )}
              <span className="text-xl" style={{ filter: active ? 'none' : 'grayscale(1) opacity(0.65)' }}>
                {t.icon}
              </span>
              <span
                className="text-[11px] font-medium mt-0.5"
                style={{ color: active ? '#C4623A' : '#6E604E' }}
              >
                {t.label}
              </span>
              {active && (
                <span className="absolute bottom-0 left-1/2 -translate-x-1/2 w-5 h-0.5 rounded-full bg-[#C4623A]" />
              )}
            </button>
          )
        })}
      </nav>
    </div>
  )
}

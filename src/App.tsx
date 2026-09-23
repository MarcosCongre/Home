import { useState } from 'react'

import { useMembers } from '@/application/members/useMembers'
import { useTasks } from '@/application/tasks/useTasks'
import { NOTIFS } from '@/infrastructure/demo/demoData'
import {
  CalendarView,
  Dashboard,
  Members,
  Notifications,
  TaskDetail,
} from '@/presentation/screens/homeScreens'

const TABS = [
  { id: 'home', label: 'Home', icon: '⌂' },
  { id: 'calendar', label: 'Calendar', icon: '◫' },
  { id: 'alerts', label: 'Alerts', icon: '◻' },
  { id: 'members', label: 'Members', icon: '◎' },
]

export default function App() {
  const [tab, setTab] = useState('home')
  const [detailId, setDetailId] = useState<string | null>(null)
  const { users, addUser, updateUser, removeUser, loadMembers, error: memberError } = useMembers()
  const { tasks, setTasks, completeTaskById, loadTasks, error: taskError } = useTasks()
  const error = memberError ?? taskError

  const openDetail = (id: string) => {
    setDetailId(id)
    setTab('detail')
  }

  const backFromDetail = () => {
    setDetailId(null)
    setTab('home')
  }

  const unreadAlerts = NOTIFS.filter(n => n.urgent).length

  return (
    <>
      {error && (
        <div className="fixed top-3 left-1/2 -translate-x-1/2 z-50 rounded-2xl bg-[#3D2E1E] text-[#F6EFE3] px-3 py-2 text-[10px] font-medium shadow-md flex items-center gap-3">
          <span>{error}</span>
          <button
            className="underline underline-offset-2"
            onClick={() => {
              void loadMembers()
              void loadTasks()
            }}
          >
            Retry
          </button>
        </div>
      )}

      <div className="min-h-screen flex items-center justify-center bg-[#D8CEBC] p-4">
        <div
          className="relative flex flex-col overflow-hidden shadow-2xl"
          style={{
            width: 375,
            height: 780,
            borderRadius: 44,
            background: '#EDE4D4',
            boxShadow: '0 32px 80px rgba(61,46,30,0.35), 0 0 0 1px rgba(61,46,30,0.1)',
          }}
        >
          <div className="flex items-center justify-between px-7 pt-3 pb-1 bg-[#F6EFE3] shrink-0">
            <span className="text-[11px] font-semibold text-[#3D2E1E]">9:41</span>
            <div className="flex gap-1 items-center">
              <span className="text-[10px] text-[#3D2E1E]">●●●●</span>
              <span className="text-[10px] text-[#3D2E1E]">WiFi</span>
              <span className="text-[10px] text-[#6B7C4E]">█▉</span>
            </div>
          </div>

          <div className="flex-1 overflow-hidden">
            {detailId && tab === 'detail' ? (
              <TaskDetail taskId={detailId} tasks={tasks} setTasks={setTasks} onBack={backFromDetail} users={users} />
            ) : tab === 'home' ? (
              <Dashboard tasks={tasks} onSelect={openDetail} users={users} onToggleComplete={completeTaskById} />
            ) : tab === 'calendar' ? (
              <CalendarView tasks={tasks} onSelect={openDetail} users={users} />
            ) : tab === 'alerts' ? (
              <Notifications users={users} />
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

          <div
            className="flex items-stretch px-2 pb-2 pt-1 bg-white/80 shrink-0"
            style={{ backdropFilter: 'blur(12px)', borderTop: '1px solid rgba(61,46,30,0.08)' }}
          >
            {TABS.map(t => {
              const active = tab === t.id || (t.id === 'home' && tab === 'detail')

              return (
                <button
                  key={t.id}
                  onClick={() => {
                    setDetailId(null)
                    setTab(t.id)
                  }}
                  className="flex-1 flex flex-col items-center justify-center py-1.5 relative"
                >
                  {t.id === 'alerts' && unreadAlerts > 0 && (
                    <span className="absolute top-1 right-6 w-4 h-4 rounded-full bg-[#C4623A] text-white text-[9px] font-bold flex items-center justify-center">
                      {unreadAlerts}
                    </span>
                  )}
                  <span className="text-xl" style={{ filter: active ? 'none' : 'grayscale(1) opacity(0.4)' }}>
                    {t.icon}
                  </span>
                  <span
                    className="text-[10px] font-medium mt-0.5"
                    style={{ color: active ? '#C4623A' : '#A89880' }}
                  >
                    {t.label}
                  </span>
                  {active && (
                    <span className="absolute bottom-0 left-1/2 -translate-x-1/2 w-5 h-0.5 rounded-full bg-[#C4623A]" />
                  )}
                </button>
              )
            })}
          </div>
        </div>
      </div>
    </>
  )
}

import { useId, useState } from 'react'

import type { Member as User } from '@/domain/members/member'
import type { Task } from '@/domain/tasks/task'
import {
  CATEGORIES,
  DAYS,
  NOTIFS,
  RECURRENCES,
  categoryColor,
} from '@/infrastructure/demo/demoData'
import { Avatar } from '@/presentation/shared/Avatar'
import { Badge } from '@/presentation/shared/Badge'

export function Dashboard({ tasks, onSelect, users, onToggleComplete }: {
  tasks: Task[]
  onSelect: (id: number) => void
  users: User[]
  onToggleComplete: (taskId: number, userId: string) => Promise<void>
}) {
  const userOf = (id: number | '') => users.find(u => u.id === id)
  const [filter, setFilter] = useState('All')
  const done = tasks.filter(t => t.done).length
  const visible = filter === 'All' ? tasks : tasks.filter(t => t.category === filter)

  return (
    <div className="flex flex-col h-full">
      <div className="px-5 pt-6 pb-4 bg-[#F6EFE3]">
        <p className="text-xs text-[#A89880] font-medium uppercase tracking-widest mb-0.5">Wednesday, 16 Sep</p>
        <h1 className="font-display text-[28px] text-[#3D2E1E] leading-tight">Good morning,<br /><em>Maya</em> 🌤</h1>
        <div className="flex gap-3 mt-4">
          {[
            { label: 'Total', val: tasks.length, color: '#3D2E1E' },
            { label: 'Done', val: done, color: '#6B7C4E' },
            { label: 'Pending', val: tasks.length - done, color: '#C4623A' },
          ].map(s => (
            <div key={s.label} className="flex-1 bg-white rounded-2xl px-3 py-2.5 text-center shadow-sm">
              <p className="text-xl font-semibold" style={{ color: s.color }}>{s.val}</p>
              <p className="text-[10px] text-[#A89880] font-medium">{s.label}</p>
            </div>
          ))}
        </div>
        <div className="mt-3 h-1.5 rounded-full bg-[#EDE4D4]">
          <div
            className="h-full rounded-full bg-[#6B7C4E] transition-all"
            style={{ width: `${Math.round((done / tasks.length) * 100)}%` }}
          />
        </div>
        <p className="text-[10px] text-[#A89880] mt-1">{Math.round((done / tasks.length) * 100)}% complete this week</p>
      </div>

      <div className="flex gap-2 px-5 py-3 overflow-x-auto shrink-0" style={{ scrollbarWidth: 'none' }}>
        {CATEGORIES.slice(0, 7).map(cat => (
          <button
            key={cat}
            onClick={() => setFilter(cat)}
            className="shrink-0 text-[11px] font-medium px-3 py-1.5 rounded-full transition-all"
            style={filter === cat
              ? { background: '#3D2E1E', color: '#F6EFE3' }
              : { background: '#EDE4D4', color: '#A89880' }}
          >
            {cat}
          </button>
        ))}
      </div>

      <div className="flex-1 overflow-y-auto px-5 pb-4 space-y-2">
        {visible.map(task => {
          const user = userOf(task.assignee)
          const catColor = categoryColor[task.category] ?? '#888'
          return (
            <div
              key={task.id}
              className="bg-white rounded-2xl px-4 py-3 flex items-center gap-3 shadow-sm active:scale-[0.99] transition-transform"
            >
              <button
                onClick={() => void onToggleComplete(task.id, task.assignee || 'user-demo')}
                className="shrink-0 w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all"
                style={task.done
                  ? { background: '#6B7C4E', borderColor: '#6B7C4E' }
                  : { borderColor: '#D8CEBC' }}
              >
                {task.done && <span className="text-white text-xs">✓</span>}
              </button>
              <button className="flex-1 text-left" onClick={() => onSelect(task.id)}>
                <p className={`text-sm font-medium leading-snug ${task.done ? 'line-through text-[#A89880]' : 'text-[#3D2E1E]'}`}>
                  {task.title}
                </p>
                <div className="flex items-center gap-1.5 mt-1">
                  <Badge label={task.category} color={catColor} />
                  <Badge label={task.recurrence} color="#A89880" />
                  {task.time && task.time !== '—' && (
                    <span className="text-[10px] text-[#A89880]">· {task.time}</span>
                  )}
                </div>
              </button>
              {user && <Avatar user={user} size={26} />}
            </div>
          )
        })}
      </div>
    </div>
  )
}

export function CalendarView({ tasks, onSelect, users }: { tasks: Task[]; onSelect: (id: number) => void; users: User[] }) {
  const userOf = (id: number | '') => users.find(u => u.id === id)
  const [activeDay, setActiveDay] = useState('Mon')
  const [filterUser, setFilterUser] = useState<number | null>(null)

  const dayTasks = tasks.filter(t =>
    t.day === activeDay && (filterUser === null || t.assignee === filterUser)
  )

  return (
    <div className="flex flex-col h-full">
      <div className="px-5 pt-6 pb-4 bg-[#F6EFE3]">
        <p className="text-xs text-[#A89880] font-medium uppercase tracking-widest">Week of Sep 15</p>
        <h2 className="font-display text-2xl text-[#3D2E1E] mt-0.5">Calendar</h2>
        <div className="flex gap-1.5 mt-4">
          {DAYS.map(d => (
            <button
              key={d}
              onClick={() => setActiveDay(d)}
              className="flex-1 py-2 rounded-xl text-[11px] font-semibold transition-all"
              style={activeDay === d
                ? { background: '#C4623A', color: '#fff' }
                : { background: '#EDE4D4', color: '#A89880' }}
            >
              {d}
            </button>
          ))}
        </div>

        <div className="flex gap-2 mt-3">
          <button
            onClick={() => setFilterUser(null)}
            className="text-[11px] font-medium px-3 py-1 rounded-full transition-all"
            style={filterUser === null ? { background: '#3D2E1E', color: '#fff' } : { background: '#EDE4D4', color: '#A89880' }}
          >
            All
          </button>
          {users.map(u => (
            <button
              key={u.id}
              onClick={() => setFilterUser(filterUser === u.id ? null : u.id)}
              className="flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-full transition-all"
              style={filterUser === u.id ? { background: u.color, color: '#fff' } : { background: '#EDE4D4', color: '#888' }}
            >
              <span>{u.name}</span>
            </button>
          ))}
        </div>
      </div>

      <div className="flex-1 overflow-y-auto px-5 py-3 space-y-2">
        {dayTasks.length === 0 && (
          <div className="text-center text-[#A89880] text-sm pt-10">No tasks for {activeDay}</div>
        )}
        {dayTasks.map(task => {
          const user = userOf(task.assignee)
          const catColor = categoryColor[task.category] ?? '#888'
          return (
            <button
              key={task.id}
              onClick={() => onSelect(task.id)}
              className="w-full text-left bg-white rounded-2xl overflow-hidden shadow-sm flex active:scale-[0.99] transition-transform"
            >
              <div className="w-1 self-stretch" style={{ background: catColor }} />
              <div className="flex-1 px-4 py-3">
                <div className="flex items-start justify-between">
                  <div>
                    <p className={`text-sm font-medium ${task.done ? 'line-through text-[#A89880]' : 'text-[#3D2E1E]'}`}>{task.title}</p>
                    <div className="flex gap-1.5 mt-1">
                      <Badge label={task.category} color={catColor} />
                      <Badge label={task.recurrence} color="#A89880" />
                    </div>
                  </div>
                  <div className="flex flex-col items-end gap-1.5 ml-3">
                    {user && <Avatar user={user} size={24} />}
                    {task.time && task.time !== '—' && (
                      <span className="text-[10px] text-[#A89880] font-medium">{task.time}</span>
                    )}
                  </div>
                </div>
              </div>
            </button>
          )
        })}
      </div>
    </div>
  )
}

export function Notifications({ users }: { users: User[] }) {
  const userOf = (id: number | '') => users.find(u => u.id === id)
  const [dismissed, setDismissed] = useState<string[]>([])
  const visible = NOTIFS.filter(n => !dismissed.includes(n.id))
  const urgent = visible.filter(n => n.urgent)
  const rest = visible.filter(n => !n.urgent)

  return (
    <div className="flex flex-col h-full">
      <div className="px-5 pt-6 pb-4 bg-[#F6EFE3]">
        <p className="text-xs text-[#A89880] font-medium uppercase tracking-widest">Today</p>
        <div className="flex items-center justify-between mt-0.5">
          <h2 className="font-display text-2xl text-[#3D2E1E]">Alerts</h2>
          {dismissed.length < NOTIFS.length && (
            <button
              onClick={() => setDismissed(NOTIFS.map(n => n.id))}
              className="text-[11px] text-[#A89880] font-medium"
            >
              Clear all
            </button>
          )}
        </div>
      </div>

      <div className="flex-1 overflow-y-auto px-5 py-3 space-y-4">
        {urgent.length > 0 && (
          <div>
            <p className="text-[10px] font-semibold uppercase tracking-widest text-[#C4623A] mb-2">Needs attention</p>
            <div className="space-y-2">
              {urgent.map(n => {
                const user = n.user ? userOf(n.user) : null
                return (
                  <div key={n.id} className="bg-white rounded-2xl px-4 py-3.5 shadow-sm border border-[#F0C4A8]">
                    <div className="flex items-start gap-3">
                      <span className="text-xl mt-0.5">{n.icon}</span>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <p className="text-sm font-semibold text-[#3D2E1E]">{n.title}</p>
                          <button onClick={() => setDismissed(d => [...d, n.id])} className="text-[#D8CEBC] text-base leading-none ml-2 shrink-0">×</button>
                        </div>
                        <p className="text-[12px] text-[#A89880] mt-0.5 leading-relaxed">{n.body}</p>
                        <div className="flex items-center gap-2 mt-2">
                          <span className="text-[10px] text-[#C4623A] font-medium">{n.time}</span>
                          {user && <Avatar user={user} size={18} />}
                        </div>
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        )}

        {rest.length > 0 && (
          <div>
            <p className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] mb-2">Earlier</p>
            <div className="space-y-2">
              {rest.map(n => {
                const user = n.user ? userOf(n.user) : null
                return (
                  <div key={n.id} className="bg-white rounded-2xl px-4 py-3 shadow-sm">
                    <div className="flex items-start gap-3">
                      <span className="text-lg mt-0.5">{n.icon}</span>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <p className="text-sm font-medium text-[#3D2E1E]">{n.title}</p>
                          <button onClick={() => setDismissed(d => [...d, n.id])} className="text-[#D8CEBC] text-base leading-none ml-2 shrink-0">×</button>
                        </div>
                        <p className="text-[12px] text-[#A89880] mt-0.5 leading-relaxed">{n.body}</p>
                        <div className="flex items-center gap-2 mt-1.5">
                          <span className="text-[10px] text-[#A89880]">{n.time}</span>
                          {user && <Avatar user={user} size={16} />}
                        </div>
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        )}

        {visible.length === 0 && (
          <div className="text-center pt-16">
            <p className="text-4xl mb-3">🌿</p>
            <p className="text-sm font-medium text-[#A89880]">All caught up!</p>
          </div>
        )}
      </div>
    </div>
  )
}

const PALETTE = [
  '#C4623A','#6B7C4E','#9B7DB5','#C4963A',
  '#3A8FB5','#B53A3A','#3A7C8F','#7C5E3A',
  '#5E6FAA','#AA5E8A','#4AAA7C','#AA804A',
]

export function MemberModal({
  initial,
  onSave,
  onClose,
}: {
  initial?: User
  onSave: (u: Omit<User, 'id'>) => void
  onClose: () => void
}) {
  const [name, setName] = useState(initial?.name ?? '')
  const [color, setColor] = useState(initial?.color ?? PALETTE[0])
  const avatar = name.trim().charAt(0).toUpperCase() || '?'

  return (
    <div
      className="absolute inset-0 z-50 flex items-end"
      style={{ background: 'rgba(61,46,30,0.45)', backdropFilter: 'blur(4px)' }}
      onClick={onClose}
    >
      <div
        className="w-full bg-[#F6EFE3] rounded-t-3xl px-5 pt-5 pb-8"
        onClick={e => e.stopPropagation()}
      >
        <div className="w-10 h-1 rounded-full bg-[#D8CEBC] mx-auto mb-5" />
        <h3 className="font-display text-xl text-[#3D2E1E] mb-4">
          {initial ? 'Edit member' : 'Add member'}
        </h3>

        <div className="flex justify-center mb-5">
          <span
            className="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-semibold shadow-md"
            style={{ background: color }}
          >
            {avatar}
          </span>
        </div>

        <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-1.5">Name</label>
        <input
          value={name}
          onChange={e => setName(e.target.value)}
          placeholder="Member name"
          className="w-full bg-white rounded-xl px-4 py-3 text-sm text-[#3D2E1E] outline-none border border-transparent focus:border-[#C4623A] transition-colors mb-4"
          autoFocus
        />

        <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-2">Color</label>
        <div className="grid grid-cols-6 gap-2 mb-5">
          {PALETTE.map(c => (
            <button
              key={c}
              onClick={() => setColor(c)}
              className="w-full aspect-square rounded-full transition-transform"
              style={{
                background: c,
                outline: color === c ? `3px solid ${c}` : 'none',
                outlineOffset: 2,
                transform: color === c ? 'scale(1.15)' : 'scale(1)',
              }}
            />
          ))}
        </div>

        <div className="flex gap-3">
          <button
            onClick={onClose}
            className="flex-1 py-3 rounded-2xl bg-[#EDE4D4] text-[#A89880] text-sm font-semibold"
          >
            Cancel
          </button>
          <button
            onClick={() => { if (name.trim()) onSave({ name: name.trim(), avatar, color }) }}
            disabled={!name.trim()}
            className="flex-1 py-3 rounded-2xl text-white text-sm font-semibold transition-opacity disabled:opacity-40"
            style={{ background: '#C4623A' }}
          >
            {initial ? 'Save changes' : 'Add member'}
          </button>
        </div>
      </div>
    </div>
  )
}

export function ConfirmDelete({ user, taskCount, onConfirm, onClose }: {
  user: User
  taskCount: number
  onConfirm: () => void
  onClose: () => void
}) {
  return (
    <div
      className="absolute inset-0 z-50 flex items-center justify-center px-6"
      style={{ background: 'rgba(61,46,30,0.45)', backdropFilter: 'blur(4px)' }}
      onClick={onClose}
    >
      <div
        className="w-full bg-[#F6EFE3] rounded-3xl px-5 py-6"
        onClick={e => e.stopPropagation()}
      >
        <div className="flex justify-center mb-3">
          <Avatar user={user} size={52} />
        </div>
        <h3 className="font-display text-xl text-[#3D2E1E] text-center mb-1">Remove {user.name}?</h3>
        {taskCount > 0 && (
          <p className="text-xs text-[#A89880] text-center mb-4">
            Their {taskCount} task{taskCount !== 1 ? 's' : ''} will become unassigned.
          </p>
        )}
        {taskCount === 0 && <div className="mb-4" />}
        <div className="flex gap-3">
          <button onClick={onClose} className="flex-1 py-3 rounded-2xl bg-[#EDE4D4] text-[#A89880] text-sm font-semibold">
            Cancel
          </button>
          <button onClick={onConfirm} className="flex-1 py-3 rounded-2xl bg-[#B53A3A] text-white text-sm font-semibold">
            Remove
          </button>
        </div>
      </div>
    </div>
  )
}

export function TaskDetail({ taskId, tasks, setTasks, onBack, users }: {
  taskId: number
  tasks: Task[]
  setTasks: (t: Task[]) => void
  onBack: () => void
  users: User[]
}) {
  const task = tasks.find(t => t.id === taskId)!
  const [title, setTitle] = useState(task.title)
  const [assignee, setAssignee] = useState(task.assignee)
  const [recurrence, setRecurrence] = useState(task.recurrence)
  const [priority, setPriority] = useState(task.priority)

  const save = () => {
    setTasks(tasks.map(t => t.id === taskId ? { ...t, title, assignee, recurrence, priority } : t))
    onBack()
  }

  const priorityColors = { low: '#6B7C4E', med: '#C4963A', high: '#C4623A' }

  return (
    <div className="flex flex-col h-full">
      <div className="px-5 pt-6 pb-4 bg-[#F6EFE3]">
        <button onClick={onBack} className="flex items-center gap-1 text-[#A89880] text-sm mb-3">
          <span>←</span> <span>Back</span>
        </button>
        <div className="flex items-center gap-2">
          <span
            className="w-2.5 h-2.5 rounded-full"
            style={{ background: categoryColor[task.category] ?? '#888' }}
          />
          <p className="text-xs text-[#A89880] font-medium uppercase tracking-widest">{task.category}</p>
        </div>
        <h2 className="font-display text-2xl text-[#3D2E1E] mt-1 leading-snug">{task.title}</h2>
      </div>

      <div className="flex-1 overflow-y-auto px-5 py-4 space-y-4">
        <div>
          <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-1.5">Task name</label>
          <input
            value={title}
            onChange={e => setTitle(e.target.value)}
            className="w-full bg-white rounded-xl px-4 py-3 text-sm text-[#3D2E1E] outline-none border border-transparent focus:border-[#C4623A] transition-colors"
          />
        </div>

        <div>
          <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-1.5">Assigned to</label>
          <div className="flex gap-2">
            {users.map(u => (
              <button
                key={u.id}
                onClick={() => setAssignee(u.id)}
                className="flex-1 flex flex-col items-center gap-1.5 py-2.5 rounded-xl transition-all border-2"
                style={assignee === u.id
                  ? { borderColor: u.color, background: u.color + '15' }
                  : { borderColor: 'transparent', background: '#fff' }}
              >
                <Avatar user={u} size={30} />
                <span className="text-[10px] font-medium" style={{ color: assignee === u.id ? u.color : '#A89880' }}>{u.name}</span>
              </button>
            ))}
          </div>
        </div>

        <div>
          <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-1.5">Recurrence</label>
          <div className="grid grid-cols-3 gap-2">
            {RECURRENCES.map(r => (
              <button
                key={r}
                onClick={() => setRecurrence(r)}
                className="py-2 rounded-xl text-xs font-medium transition-all"
                style={recurrence === r
                  ? { background: '#3D2E1E', color: '#F6EFE3' }
                  : { background: '#fff', color: '#A89880' }}
              >
                {r}
              </button>
            ))}
          </div>
        </div>

        <div>
          <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-1.5">Priority</label>
          <div className="flex gap-2">
            {(['low', 'med', 'high'] as const).map(p => (
              <button
                key={p}
                onClick={() => setPriority(p)}
                className="flex-1 py-2 rounded-xl text-xs font-semibold transition-all capitalize"
                style={priority === p
                  ? { background: priorityColors[p], color: '#fff' }
                  : { background: '#fff', color: '#A89880' }}
              >
                {p === 'med' ? 'Medium' : p.charAt(0).toUpperCase() + p.slice(1)}
              </button>
            ))}
          </div>
        </div>

        <div>
          <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-1.5">Completion history</label>
          <div className="bg-white rounded-xl divide-y divide-[#F6EFE3]">
            {task.history.length === 0 && (
              <p className="text-xs text-[#A89880] px-4 py-3">No history yet</p>
            )}
            {task.history.map((h, i) => {
              const u = users.find(u => u.id === task.assignee)
              return (
                <div key={i} className="flex items-center gap-3 px-4 py-3">
                  <span className="w-5 h-5 rounded-full bg-[#D8E4C8] flex items-center justify-center text-[#6B7C4E] text-xs">✓</span>
                  <span className="text-xs text-[#3D2E1E]">Completed {h}</span>
                  {u && <Avatar user={u} size={18} />}
                </div>
              )
            })}
          </div>
        </div>
      </div>

      <div className="px-5 pb-8 pt-2">
        <button
          onClick={save}
          className="w-full py-3.5 rounded-2xl bg-[#C4623A] text-white text-sm font-semibold tracking-wide"
        >
          Save changes
        </button>
      </div>
    </div>
  )
}

export function Members({ users, tasks, onAddUser, onUpdateUser, onRemoveUser, onUnassignTasks }: {
  users: User[]
  tasks: Task[]
  onAddUser: (u: User) => void
  onUpdateUser: (userId: number, data: Omit<User, 'id'>) => void
  onRemoveUser: (userId: number) => void
  onUnassignTasks: (userId: number) => void
}) {
  const [selected, setSelected] = useState<number | null>(null)
  const [modal, setModal] = useState<'add' | 'edit' | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<number | null>(null)
  const uid = useId()

  const stats = users.map(u => {
    const mine = tasks.filter(t => t.assignee === u.id)
    const done = mine.filter(t => t.done).length
    return { ...u, total: mine.length, done, pct: mine.length ? Math.round((done / mine.length) * 100) : 0 }
  })

  const selectedUser = selected ? users.find(u => u.id === selected) : null
  const selectedStat = selected ? stats.find(u => u.id === selected) : null
  const userTasks = selected ? tasks.filter(t => t.assignee === selected) : []

  const deleteUser = (id: number) => {
    onRemoveUser(id)
    onUnassignTasks(id)
    setDeleteTarget(null)
    setSelected(null)
  }

  const deleteTargetUser = deleteTarget ? users.find(u => u.id === deleteTarget) : null

  return (
    <div className="flex flex-col h-full relative">
      <div className="px-5 pt-6 pb-4 bg-[#F6EFE3]">
        {selected ? (
          <button onClick={() => setSelected(null)} className="flex items-center gap-1 text-[#A89880] text-sm mb-3">
            <span>←</span> <span>All members</span>
          </button>
        ) : null}
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs text-[#A89880] font-medium uppercase tracking-widest">Household</p>
            <h2 className="font-display text-2xl text-[#3D2E1E] mt-0.5">
              {selectedUser ? selectedUser.name : 'Members'}
            </h2>
          </div>
          {!selected && (
            <button
              onClick={() => setModal('add')}
              className="w-9 h-9 rounded-full bg-[#C4623A] text-white text-xl flex items-center justify-center shadow-sm"
            >
              +
            </button>
          )}
          {selected && selectedUser && (
            <div className="flex gap-2">
              <button
                onClick={() => setModal('edit')}
                className="w-8 h-8 rounded-full bg-[#EDE4D4] flex items-center justify-center text-sm"
                title="Edit"
              >
                ✎
              </button>
              <button
                onClick={() => setDeleteTarget(selected)}
                className="w-8 h-8 rounded-full bg-[#F5D4C2] flex items-center justify-center text-sm"
                title="Delete"
              >
                🗑
              </button>
            </div>
          )}
        </div>
      </div>

      {!selected ? (
        <div className="flex-1 overflow-y-auto px-5 py-3 space-y-3">
          {users.length === 0 && (
            <div className="text-center pt-12">
              <p className="text-3xl mb-2">👥</p>
              <p className="text-sm text-[#A89880]">No members yet. Add one!</p>
            </div>
          )}
          {stats.map(u => (
            <button
              key={u.id}
              onClick={() => setSelected(u.id)}
              className="w-full bg-white rounded-2xl px-4 py-4 text-left shadow-sm active:scale-[0.99] transition-transform"
            >
              <div className="flex items-center gap-3">
                <Avatar user={u} size={44} />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-semibold text-[#3D2E1E]">{u.name}</p>
                  <p className="text-[11px] text-[#A89880]">{u.done} of {u.total} tasks done this week</p>
                  <div className="mt-2 h-1.5 rounded-full bg-[#EDE4D4]">
                    <div className="h-full rounded-full transition-all" style={{ width: `${u.pct}%`, background: u.color }} />
                  </div>
                </div>
                <span className="text-sm font-bold ml-2" style={{ color: u.color }}>{u.pct}%</span>
              </div>
            </button>
          ))}

          {users.length > 0 && tasks.length > 0 && (
            <div className="bg-[#3D2E1E] rounded-2xl px-4 py-4 mt-1">
              <p className="text-[10px] text-[#A89880] uppercase tracking-widest mb-2">Household total</p>
              <div className="flex items-end justify-between">
                <div>
                  <p className="text-3xl font-display text-[#F6EFE3]">
                    {Math.round((tasks.filter(t => t.done).length / tasks.length) * 100)}%
                  </p>
                  <p className="text-xs text-[#A89880] mt-0.5">Weekly completion</p>
                </div>
                <div className="flex -space-x-2">
                  {users.map(u => <Avatar key={u.id} user={u} size={28} />)}
                </div>
              </div>
            </div>
          )}
        </div>
      ) : selectedUser && selectedStat && (
        <div className="flex-1 overflow-y-auto px-5 py-3">
          <div className="rounded-2xl px-4 py-4 mb-4 text-white" style={{ background: selectedUser.color }}>
            <div className="flex items-center gap-3">
              <Avatar user={selectedUser} size={48} />
              <div>
                <p className="font-semibold text-lg">{selectedUser.name}</p>
                <p className="text-sm opacity-80">{selectedStat.done} done · {selectedStat.total - selectedStat.done} pending</p>
              </div>
            </div>
            <div className="mt-3 h-2 rounded-full bg-white/30">
              <div className="h-full rounded-full bg-white transition-all" style={{ width: `${selectedStat.pct}%` }} />
            </div>
            <p className="text-xs opacity-70 mt-1">{selectedStat.pct}% complete</p>
          </div>

          <p className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] mb-2">Assigned tasks</p>
          {userTasks.length === 0 && (
            <p className="text-xs text-[#A89880] text-center pt-4">No tasks assigned</p>
          )}
          <div className="space-y-2">
            {userTasks.map(task => (
              <div key={task.id} className="bg-white rounded-xl px-4 py-3 flex items-center gap-3">
                <span
                  className="w-5 h-5 rounded-full flex items-center justify-center text-xs shrink-0"
                  style={task.done ? { background: '#6B7C4E', color: '#fff' } : { background: '#EDE4D4', color: '#A89880' }}
                >
                  {task.done ? '✓' : '○'}
                </span>
                <div className="flex-1 min-w-0">
                  <p className={`text-sm font-medium ${task.done ? 'line-through text-[#A89880]' : 'text-[#3D2E1E]'}`}>{task.title}</p>
                  <div className="flex gap-1.5 mt-0.5">
                    <Badge label={task.category} color={categoryColor[task.category] ?? '#888'} />
                    <Badge label={task.recurrence} color="#A89880" />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {modal === 'add' && (
        <MemberModal onSave={(data) => {
          const id = `${uid}-${Date.now()}`
          onAddUser({ id, ...data })
          setModal(null)
        }} onClose={() => setModal(null)} />
      )}
      {modal === 'edit' && selectedUser && (
        <MemberModal initial={selectedUser} onSave={(data) => {
          onUpdateUser(selectedUser.id, data)
          setModal(null)
        }} onClose={() => setModal(null)} />
      )}
      {deleteTarget && deleteTargetUser && (
        <ConfirmDelete
          user={deleteTargetUser}
          taskCount={tasks.filter(t => t.assignee === deleteTarget).length}
          onConfirm={() => deleteUser(deleteTarget)}
          onClose={() => setDeleteTarget(null)}
        />
      )}
    </div>
  )
}

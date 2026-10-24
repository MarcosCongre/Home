import { useId, useState } from 'react'

import type { Alert } from '@/domain/alerts/alert'
import type { Category, RecurrenceType } from '@/domain/catalogs/catalog'
import type { Member as User } from '@/domain/members/member'
import type { Task, TaskChanges } from '@/domain/tasks/task'
import { DAYS } from '@/infrastructure/demo/demoData'
import {
  taskCategoryColor,
  taskCategoryText,
  taskRecurrenceText,
} from '@/presentation/components/taskCatalogDisplay'
import { Avatar } from '@/presentation/shared/Avatar'
import { Badge } from '@/presentation/shared/Badge'
import { ConfirmDialog } from '@/presentation/shared/ConfirmDialog'
import { TaskModal, type TaskFormData } from '@/presentation/screens/TaskModal'
import {
  categoryLabel,
  dayLabel,
  es,
  priorityLabel,
  recurrenceLabel,
} from '@/presentation/i18n/es'

export function Dashboard({ tasks, onSelect, users, categories, recurrenceTypes, onToggleComplete, onAutoAssign, isAutoAssigning = false }: {
  tasks: Task[]
  onSelect: (id: number) => void
  users: User[]
  categories: Category[]
  recurrenceTypes: RecurrenceType[]
  onToggleComplete: (taskId: number, userId: number, nextAssigneeId?: number) => Promise<void>
  onAutoAssign?: () => Promise<void>
  isAutoAssigning?: boolean
}) {
  const userOf = (id: number | '') => users.find(u => u.id === id)
  // 'All' is a UI-only sentinel; otherwise a household category id.
  const [filter, setFilter] = useState<'All' | number>('All')
  // A repeatable task being completed waits here until the next member is chosen.
  const [handOff, setHandOff] = useState<{ task: Task; next: number | '' } | null>(null)
  const done = tasks.filter(t => t.done).length
  const completionPct = tasks.length ? Math.round((done / tasks.length) * 100) : 0
  // A filter whose category was deleted falls back to showing everything.
  const activeFilter = categories.some(c => c.id === filter) ? filter : 'All'
  const visible = activeFilter === 'All' ? tasks : tasks.filter(t => t.categoryId === activeFilter)
  const filterChipStyle = (selected: boolean) => selected
    ? { background: '#3D2E1E', color: '#F6EFE3' }
    : { background: '#EDE4D4', color: '#6E604E' }

  return (
    <div className="flex flex-col h-full">
      <div className="px-5 pt-6 pb-4 md:px-8 md:pt-8 bg-[#F6EFE3]">
        <p className="text-xs text-[#6E604E] font-medium uppercase tracking-widest mb-0.5">{es.dashboard.date}</p>
        <h1 className="font-display text-[28px] text-[#3D2E1E] leading-tight">{es.dashboard.greeting}<br /><em>Maya</em> 🌤</h1>
        <div className="flex gap-3 mt-4">
          {[
            { label: es.dashboard.total, val: tasks.length, color: '#3D2E1E' },
            { label: es.dashboard.done, val: done, color: '#6B7C4E' },
            { label: es.dashboard.pending, val: tasks.length - done, color: '#C4623A' },
          ].map(s => (
            <div key={s.label} className="flex-1 bg-white rounded-2xl px-3 py-2.5 text-center shadow-sm">
              <p className="text-xl font-semibold" style={{ color: s.color }}>{s.val}</p>
              <p className="text-[11px] text-[#6E604E] font-medium">{s.label}</p>
            </div>
          ))}
        </div>
        <div className="mt-3 h-1.5 rounded-full bg-[#EDE4D4]">
          <div
            className="h-full rounded-full bg-[#6B7C4E] transition-all"
            style={{ width: `${completionPct}%` }}
          />
        </div>
        <p className="text-[11px] text-[#6E604E] mt-1">{es.dashboard.completeThisWeek(completionPct)}</p>
        {onAutoAssign && (
          <button
            onClick={() => void onAutoAssign()}
            disabled={isAutoAssigning}
            aria-busy={isAutoAssigning}
            className="mt-3 w-full min-h-11 py-2 rounded-xl bg-[#C4623A] text-white text-sm font-semibold md:w-auto md:px-8 shadow-sm transition-opacity disabled:opacity-60"
          >
            {isAutoAssigning ? es.dashboard.autoAssigning : es.dashboard.autoAssign}
          </button>
        )}
      </div>

      <div className="flex gap-2 px-5 py-3 md:px-8 md:flex-wrap overflow-x-auto shrink-0" style={{ scrollbarWidth: 'none' }}>
        <button
          onClick={() => setFilter('All')}
          className="shrink-0 min-h-11 text-xs font-medium px-4 py-1.5 rounded-full transition-all"
          style={filterChipStyle(activeFilter === 'All')}
        >
          {categoryLabel('All')}
        </button>
        {categories.map(cat => (
          <button
            key={cat.id}
            onClick={() => setFilter(cat.id)}
            className="shrink-0 min-h-11 max-w-[60vw] truncate text-xs font-medium px-4 py-1.5 rounded-full transition-all"
            style={filterChipStyle(activeFilter === cat.id)}
          >
            {categoryLabel(cat.name)}
          </button>
        ))}
      </div>

      <div className="flex-1 overflow-y-auto px-5 pb-4 md:px-8 flex flex-col gap-2 md:grid md:grid-cols-2 md:gap-3 md:content-start xl:grid-cols-3">
        {visible.map(task => {
          const user = userOf(task.assignee)
          const catColor = taskCategoryColor(task, categories)
          return (
            <div
              key={task.id}
              className="bg-white rounded-2xl pl-2 pr-4 py-3 flex items-center gap-2 shadow-sm active:scale-[0.99] transition-transform"
            >
              <button
                onClick={() => {
                  if (task.repeatable && !task.done) {
                    setHandOff({ task, next: task.assignee })
                    return
                  }
                  void onToggleComplete(task.id, task.assignee || 0)
                }}
                className="shrink-0 w-11 h-11 flex items-center justify-center"
              >
                <span
                  className="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all"
                  style={task.done
                    ? { background: '#6B7C4E', borderColor: '#6B7C4E' }
                    : { borderColor: '#D8CEBC' }}
                >
                  {task.done && <span className="text-white text-xs">✓</span>}
                </span>
              </button>
              <button className="flex-1 min-w-0 text-left" onClick={() => onSelect(task.id)}>
                <p className={`text-sm font-medium leading-snug ${task.done ? 'line-through text-[#6E604E]' : 'text-[#3D2E1E]'}`}>
                  {task.title}
                </p>
                <div className="flex flex-wrap items-center gap-1.5 mt-1">
                  <Badge label={taskCategoryText(task, categories)} color={catColor} />
                  <Badge label={taskRecurrenceText(task, recurrenceTypes)} color="#6E604E" />
                  {task.repeatable && <Badge label={es.repeatable.badge} color="#6B7C4E" />}
                  {task.time && task.time !== '—' && (
                    <span className="text-[11px] text-[#6E604E]">· {task.time}</span>
                  )}
                </div>
              </button>
              {user && <Avatar user={user} size={26} />}
            </div>
          )
        })}
      </div>

      {handOff && (
        <ConfirmDialog
          open
          tone="primary"
          title={es.repeatable.nextTitle}
          message={es.repeatable.nextMessage(handOff.task.title)}
          confirmLabel={es.repeatable.complete}
          onConfirm={() => {
            const { task, next } = handOff
            setHandOff(null)
            void onToggleComplete(task.id, task.assignee || 0, next === '' ? undefined : next)
          }}
          onCancel={() => setHandOff(null)}
        >
          <div className="flex flex-wrap gap-2">
            {users.map(u => (
              <button
                key={u.id}
                onClick={() => setHandOff({ ...handOff, next: u.id })}
                aria-pressed={handOff.next === u.id}
                className="flex-1 basis-20 min-w-0 flex flex-col items-center gap-1.5 py-2.5 px-1 rounded-xl transition-all border-2"
                style={handOff.next === u.id
                  ? { borderColor: u.color, background: u.color + '15' }
                  : { borderColor: 'transparent', background: '#fff' }}
              >
                <Avatar user={u} size={30} />
                <span className="max-w-full truncate text-[11px] font-medium" style={{ color: handOff.next === u.id ? u.color : '#6E604E' }}>{u.name}</span>
              </button>
            ))}
          </div>
        </ConfirmDialog>
      )}
    </div>
  )
}

export function CalendarView({ tasks, users, categories, recurrenceTypes, onSelect: _onSelect, onCreateTask, onUpdateTask, onDeleteTask }: {
  tasks: Task[]
  users: User[]
  categories: Category[]
  recurrenceTypes: RecurrenceType[]
  onSelect: (id: number) => void
  onCreateTask: (data: TaskFormData) => Promise<void>
  onUpdateTask: (id: number, data: TaskFormData) => Promise<void>
  onDeleteTask: (id: number) => Promise<void>
}) {
  const userOf = (id: number | '') => users.find(u => u.id === id)
  const [activeDay, setActiveDay] = useState('All')
  const [filterUser, setFilterUser] = useState<number | null>(null)
  const [taskModal, setTaskModal] = useState<'add' | Task | null>(null)
  const dayTasks = tasks.filter(t =>
    (activeDay === 'All' || t.day === activeDay) &&
    (filterUser === null || t.assignee === filterUser)
  )

  return (
    <div className="flex flex-col h-full">
      <div className="px-5 pt-6 pb-4 md:px-8 md:pt-8 bg-[#F6EFE3]">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs text-[#6E604E] font-medium uppercase tracking-widest">{es.calendar.weekOf}</p>
            <h2 className="font-display text-2xl text-[#3D2E1E] mt-0.5">{es.calendar.title}</h2>
          </div>
          <button
            onClick={() => setTaskModal('add')}
            aria-label={es.calendar.addTask}
            title={es.calendar.addTask}
            className="w-11 h-11 shrink-0 rounded-full bg-[#C4623A] text-white text-xl flex items-center justify-center shadow-sm"
          >
            +
          </button>
        </div>

        <div
          className="flex gap-1.5 mt-4 -mx-5 px-5 overflow-x-auto md:mx-0 md:px-0 md:overflow-visible"
          style={{ scrollbarWidth: 'none' }}
        >
          {['All', ...DAYS].map(d => (
            <button
              key={d}
              onClick={() => setActiveDay(d)}
              className="shrink-0 min-w-11 min-h-11 px-3 md:flex-1 md:px-0 py-2 rounded-xl text-xs font-semibold transition-all"
              style={activeDay === d
                ? { background: '#C4623A', color: '#fff' }
                : { background: '#EDE4D4', color: '#6E604E' }}
            >
              {d === 'All' ? es.common.allDays : dayLabel(d)}
            </button>
          ))}
        </div>

        <div
          className="flex gap-2 mt-3 -mx-5 px-5 overflow-x-auto md:mx-0 md:px-0 md:flex-wrap md:overflow-visible"
          style={{ scrollbarWidth: 'none' }}
        >
          <button
            onClick={() => setFilterUser(null)}
            className="shrink-0 min-h-11 text-xs font-medium px-4 py-1 rounded-full transition-all"
            style={filterUser === null ? { background: '#3D2E1E', color: '#fff' } : { background: '#EDE4D4', color: '#6E604E' }}
          >
            {es.common.all}
          </button>
          {users.map(u => (
            <button
              key={u.id}
              onClick={() => setFilterUser(filterUser === u.id ? null : u.id)}
              className="shrink-0 min-h-11 flex items-center gap-1.5 text-xs font-medium px-4 py-1 rounded-full transition-all"
              style={filterUser === u.id ? { background: u.color, color: '#fff' } : { background: '#EDE4D4', color: '#888' }}
            >
              <span>{u.name}</span>
            </button>
          ))}
        </div>
      </div>


      <div className="flex-1 overflow-y-auto px-5 py-3 md:px-8 flex flex-col gap-2 md:grid md:grid-cols-2 md:auto-rows-max md:gap-3 md:content-start xl:grid-cols-3">
        {dayTasks.length === 0 && (
          <div className="col-span-full text-center text-[#6E604E] text-sm pt-10">{activeDay === 'All' ? es.calendar.noTasks : es.calendar.noTasksFor(dayLabel(activeDay))}</div>
        )}
        {dayTasks.map(task => {
          const user = userOf(task.assignee)
          const catColor = taskCategoryColor(task, categories)
          return (
            <button
              key={task.id}
              onClick={() => setTaskModal(task)}
              className="w-full shrink-0 text-left bg-white rounded-2xl overflow-hidden shadow-sm flex active:scale-[0.99] transition-transform"
            >
              <div className="w-1 self-stretch" style={{ background: catColor }} />
              <div className="flex-1 px-4 py-3">
                <div className="flex items-start justify-between">
                  <div className="min-w-0">
                    <p className={`text-sm font-medium ${task.done ? 'line-through text-[#6E604E]' : 'text-[#3D2E1E]'}`}>{task.title}</p>
                    <div className="flex flex-wrap gap-1.5 mt-1">
                      <Badge label={taskCategoryText(task, categories)} color={catColor} />
                      <Badge label={taskRecurrenceText(task, recurrenceTypes)} color="#6E604E" />
                    </div>
                  </div>
                  <div className="flex flex-col items-end gap-1.5 ml-3">
                    {user && <Avatar user={user} size={24} />}
                    {task.time && task.time !== '—' && (
                      <span className="text-[11px] text-[#6E604E] font-medium">{task.time}</span>
                    )}
                  </div>
                </div>
              </div>
            </button>
          )
        })}
      </div>
      {taskModal !== null && (
        <TaskModal
          initial={taskModal === 'add' ? undefined : taskModal}
          defaultDay={activeDay === 'All' ? DAYS[0] : activeDay}
          users={users}
          categories={categories}
          recurrenceTypes={recurrenceTypes}
          onClose={() => setTaskModal(null)}
          onDelete={taskModal === 'add' ? undefined : async () => {
            await onDeleteTask(taskModal.id)
            setTaskModal(null)
          }}
          onSave={async (data) => {
            if (taskModal === 'add') {
              await onCreateTask(data)
              setActiveDay(data.day)
              setFilterUser(data.assignee === '' ? null : data.assignee)
            } else {
              await onUpdateTask(taskModal.id, data)
            }
            setTaskModal(null)
          }}
        />
      )}
    </div>
  )
}

export function Notifications({ alerts, onDismiss, onDismissAll, users }: {
  alerts: Alert[]
  onDismiss: (alertId: number) => Promise<void>
  onDismissAll: () => Promise<void>
  users: User[]
}) {
  const userByInitial = (initial: string | null) => initial
    ? users.find(u => u.avatar === initial || u.name[0]?.toUpperCase() === initial.toUpperCase()) ?? null
    : null
  const visible = alerts.filter(n => n.status !== 'dismissed')
  const urgent = visible.filter(n => n.urgent)
  const rest = visible.filter(n => !n.urgent)

  return (
    <div className="flex flex-col h-full">
      <div className="px-5 pt-6 pb-4 md:px-8 md:pt-8 bg-[#F6EFE3]">
        <p className="text-xs text-[#6E604E] font-medium uppercase tracking-widest">{es.alerts.today}</p>
        <div className="flex items-center justify-between mt-0.5">
          <h2 className="font-display text-2xl text-[#3D2E1E]">{es.alerts.title}</h2>
          {visible.length > 0 && (
            <button
              onClick={() => void onDismissAll()}
              className="min-h-11 px-3 -mr-3 text-xs text-[#6E604E] font-medium"
            >
              {es.alerts.clearAll}
            </button>
          )}
        </div>
      </div>

      <div className="flex-1 overflow-y-auto px-5 py-3 md:px-8 space-y-4">
        {urgent.length > 0 && (
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-widest text-[#C4623A] mb-2">{es.alerts.needsAttention}</p>
            <div className="flex flex-col gap-2 md:grid md:grid-cols-2 md:gap-3">
              {urgent.map(n => {
                const user = n.user ? userByInitial(n.user) : null
                return (
                  <div key={n.id} className="bg-white rounded-2xl px-4 py-3.5 shadow-sm border border-[#F0C4A8]">
                    <div className="flex items-start gap-3">
                      <span className="text-xl mt-0.5">{n.icon}</span>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <p className="text-sm font-semibold text-[#3D2E1E]">{n.title}</p>
                          <button onClick={() => void onDismiss(n.id)} aria-label={es.common.dismiss} title={es.common.dismiss} className="w-11 h-11 -my-3 -mr-3 ml-1 flex items-center justify-center text-[#6E604E] text-xl leading-none shrink-0">×</button>
                        </div>
                        <p className="text-[13px] text-[#6E604E] mt-0.5 leading-relaxed">{n.body}</p>
                        <div className="flex items-center gap-2 mt-2">
                          <span className="text-[11px] text-[#C4623A] font-medium">{n.time}</span>
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
            <p className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] mb-2">{es.alerts.earlier}</p>
            <div className="flex flex-col gap-2 md:grid md:grid-cols-2 md:gap-3">
              {rest.map(n => {
                const user = n.user ? userByInitial(n.user) : null
                return (
                  <div key={n.id} className="bg-white rounded-2xl px-4 py-3 shadow-sm">
                    <div className="flex items-start gap-3">
                      <span className="text-lg mt-0.5">{n.icon}</span>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between">
                          <p className="text-sm font-medium text-[#3D2E1E]">{n.title}</p>
                          <button onClick={() => void onDismiss(n.id)} aria-label={es.common.dismiss} title={es.common.dismiss} className="w-11 h-11 -my-3 -mr-3 ml-1 flex items-center justify-center text-[#6E604E] text-xl leading-none shrink-0">×</button>
                        </div>
                        <p className="text-[13px] text-[#6E604E] mt-0.5 leading-relaxed">{n.body}</p>
                        <div className="flex items-center gap-2 mt-1.5">
                          <span className="text-[11px] text-[#6E604E]">{n.time}</span>
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
            <p className="text-sm font-medium text-[#6E604E]">{es.alerts.allCaughtUp}</p>
          </div>
        )}
      </div>
    </div>
  )
}

export const PALETTE = [
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
      className="fixed inset-0 z-50 flex items-end md:items-center md:justify-center md:p-6"
      style={{ background: 'rgba(61,46,30,0.45)', backdropFilter: 'blur(4px)' }}
      onClick={onClose}
    >
      <div
        className="w-full max-h-[90dvh] overflow-y-auto bg-[#F6EFE3] rounded-t-3xl md:max-w-lg md:rounded-3xl px-5 pt-5 pb-[calc(env(safe-area-inset-bottom)+2rem)] md:pb-6"
        onClick={e => e.stopPropagation()}
      >
        <div className="w-10 h-1 rounded-full bg-[#D8CEBC] mx-auto mb-5 md:hidden" />
        <h3 className="font-display text-xl text-[#3D2E1E] mb-4">
          {initial ? es.members.editMember : es.members.addMember}
        </h3>

        <div className="flex justify-center mb-5">
          <span
            className="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-semibold shadow-md"
            style={{ background: color }}
          >
            {avatar}
          </span>
        </div>

        <label className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] block mb-1.5">{es.members.name}</label>
        <input
          value={name}
          onChange={e => setName(e.target.value)}
          placeholder={es.members.namePlaceholder}
          className="w-full bg-white rounded-xl px-4 py-3 text-sm text-[#3D2E1E] outline-none border border-transparent focus:border-[#C4623A] transition-colors mb-4"
          autoFocus
        />

        <label className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] block mb-2">{es.members.color}</label>
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
            className="flex-1 py-3 rounded-2xl bg-[#EDE4D4] text-[#6E604E] text-sm font-semibold"
          >
            {es.common.cancel}
          </button>
          <button
            onClick={() => { if (name.trim()) onSave({ name: name.trim(), avatar, color }) }}
            disabled={!name.trim()}
            className="flex-1 py-3 rounded-2xl text-white text-sm font-semibold transition-opacity disabled:opacity-40"
            style={{ background: '#C4623A' }}
          >
            {initial ? es.common.saveChanges : es.members.addMember}
          </button>
        </div>
      </div>
    </div>
  )
}

export function TaskDetail({ taskId, tasks, onSave, onBack, users, categories, recurrenceTypes }: {
  taskId: number
  tasks: Task[]
  // Receives the edited fields; only the ones that changed are sent.
  onSave: (taskId: number, changes: TaskChanges) => Promise<void>
  onBack: () => void
  users: User[]
  categories: Category[]
  recurrenceTypes: RecurrenceType[]
}) {
  const task = tasks.find(t => t.id === taskId)!
  const [title, setTitle] = useState(task.title)
  const [assignee, setAssignee] = useState(task.assignee)
  const [recurrenceId, setRecurrenceId] = useState(task.recurrenceId)
  const [priority, setPriority] = useState(task.priority)
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)
  // A dangling recurrence id shows as "Una vez"; it is only sent if changed.
  const once = !recurrenceTypes.some(r => r.id === recurrenceId)

  const save = async () => {
    if (saving) return
    setSaving(true)
    setSaveError(null)
    try {
      await onSave(taskId, { title, assignee, recurrenceId, priority })
      onBack()
    } catch {
      setSaveError(es.taskDetail.saveFailed)
      setSaving(false)
    }
  }
  const recurrenceChipStyle = (selected: boolean) => selected
    ? { background: '#3D2E1E', color: '#F6EFE3' }
    : { background: '#fff', color: '#6E604E' }

  const priorityColors = { low: '#6B7C4E', med: '#C4963A', high: '#C4623A' }

  return (
    <div className="flex flex-col h-full">
      <div className="px-5 pt-6 pb-4 md:px-8 md:pt-8 bg-[#F6EFE3]">
        <button onClick={onBack} className="flex items-center gap-1 min-h-11 pr-3 -mt-2 mb-1 text-[#6E604E] text-sm">
          <span>←</span> <span>{es.common.back}</span>
        </button>
        <div className="flex items-center gap-2">
          <span
            className="w-2.5 h-2.5 rounded-full"
            style={{ background: taskCategoryColor(task, categories) }}
          />
          <p className="text-xs text-[#6E604E] font-medium uppercase tracking-widest">{taskCategoryText(task, categories)}</p>
        </div>
        <h2 className="font-display text-2xl text-[#3D2E1E] mt-1 leading-snug">{task.title}</h2>
      </div>

      <div className="flex-1 overflow-y-auto px-5 py-4 md:px-8 space-y-4 md:space-y-0 md:grid md:grid-cols-2 md:gap-x-6 md:gap-y-5 md:content-start">
        <div>
          <label className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] block mb-1.5">{es.fields.taskName}</label>
          <input
            value={title}
            onChange={e => setTitle(e.target.value)}
            className="w-full bg-white rounded-xl px-4 py-3 text-sm text-[#3D2E1E] outline-none border border-transparent focus:border-[#C4623A] transition-colors"
          />
        </div>

        <div>
          <label className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] block mb-1.5">{es.fields.assignedTo}</label>
          <div className="flex flex-wrap gap-2">
            {users.map(u => (
              <button
                key={u.id}
                onClick={() => setAssignee(u.id)}
                className="flex-1 basis-20 min-w-0 flex flex-col items-center gap-1.5 py-2.5 px-1 rounded-xl transition-all border-2"
                style={assignee === u.id
                  ? { borderColor: u.color, background: u.color + '15' }
                  : { borderColor: 'transparent', background: '#fff' }}
              >
                <Avatar user={u} size={30} />
                <span className="max-w-full truncate text-[11px] font-medium" style={{ color: assignee === u.id ? u.color : '#6E604E' }}>{u.name}</span>
              </button>
            ))}
          </div>
        </div>

        <div>
          <label className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] block mb-1.5">{es.fields.recurrence}</label>
          <div className="grid grid-cols-3 gap-2">
            <button
              onClick={() => setRecurrenceId(null)}
              className="min-h-11 py-2 px-1 rounded-xl text-xs font-medium transition-all"
              style={recurrenceChipStyle(once)}
            >
              {es.catalogs.once}
            </button>
            {recurrenceTypes.map(r => (
              <button
                key={r.id}
                onClick={() => setRecurrenceId(r.id)}
                className="min-h-11 py-2 px-1 rounded-xl text-xs font-medium transition-all break-words"
                style={recurrenceChipStyle(recurrenceId === r.id)}
              >
                {recurrenceLabel(r.name)}
              </button>
            ))}
          </div>
        </div>

        <div>
          <label className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] block mb-1.5">{es.fields.priority}</label>
          <div className="flex gap-2">
            {(['low', 'med', 'high'] as const).map(p => (
              <button
                key={p}
                onClick={() => setPriority(p)}
                className="flex-1 min-h-11 py-2 rounded-xl text-xs font-semibold transition-all"
                style={priority === p
                  ? { background: priorityColors[p], color: '#fff' }
                  : { background: '#fff', color: '#6E604E' }}
              >
                {priorityLabel(p)}
              </button>
            ))}
          </div>
        </div>

        <div className="md:col-span-2">
          <label className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] block mb-1.5">{es.taskDetail.completionHistory}</label>
          <div className="bg-white rounded-xl divide-y divide-[#F6EFE3]">
            {task.history.length === 0 && (
              <p className="text-xs text-[#6E604E] px-4 py-3">{es.taskDetail.noHistory}</p>
            )}
            {task.history.map((h, i) => {
              const u = users.find(u => u.id === task.assignee)
              return (
                <div key={i} className="flex items-center gap-3 px-4 py-3">
                  <span className="w-5 h-5 rounded-full bg-[#D8E4C8] flex items-center justify-center text-[#6B7C4E] text-xs">✓</span>
                  <span className="text-xs text-[#3D2E1E]">{es.taskDetail.completedOn(h)}</span>
                  {u && <Avatar user={u} size={18} />}
                </div>
              )
            })}
          </div>
        </div>
      </div>

      <div className="px-5 pb-8 pt-2 md:px-8 md:flex md:items-center md:justify-end md:gap-4">
        {saveError && (
          <p role="alert" className="text-xs text-[#B53A3A] mb-2 md:mb-0">{saveError}</p>
        )}
        <button
          onClick={() => void save()}
          disabled={saving}
          aria-busy={saving}
          className="w-full md:w-auto md:px-10 py-3.5 rounded-2xl bg-[#C4623A] text-white text-sm font-semibold tracking-wide transition-opacity disabled:opacity-60"
        >
          {saving ? es.taskDetail.saving : es.common.saveChanges}
        </button>
      </div>
    </div>
  )
}

export function Members({ users, tasks, categories, recurrenceTypes, onAddUser, onUpdateUser, onRemoveUser, onUnassignTasks }: {
  users: User[]
  tasks: Task[]
  categories: Category[]
  recurrenceTypes: RecurrenceType[]
  onAddUser: (u: User) => void
  onUpdateUser: (userId: number, data: Omit<User, 'id'>) => void
  onRemoveUser: (userId: number) => Promise<void>
  onUnassignTasks: (userId: number) => void
}) {
  const [selected, setSelected] = useState<number | null>(null)
  const [modal, setModal] = useState<'add' | 'edit' | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<number | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const uid = useId()

  const stats = users.map(u => {
    const mine = tasks.filter(t => t.assignee === u.id)
    const done = mine.filter(t => t.done).length
    return { ...u, total: mine.length, done, pct: mine.length ? Math.round((done / mine.length) * 100) : 0 }
  })

  const selectedUser = selected ? users.find(u => u.id === selected) : null
  const selectedStat = selected ? stats.find(u => u.id === selected) : null
  const userTasks = selected ? tasks.filter(t => t.assignee === selected) : []

  const deleteUser = async (id: number) => {
    if (deleting) return
    setDeleting(true)
    setDeleteError(null)
    try {
      await onRemoveUser(id)
      onUnassignTasks(id)
      setDeleteTarget(null)
      setSelected(null)
    } catch {
      // Keep the dialog open so the user can retry or cancel.
      setDeleteError(es.members.removeFailed)
    } finally {
      setDeleting(false)
    }
  }

  const cancelDelete = () => {
    setDeleteTarget(null)
    setDeleteError(null)
  }

  const deleteTargetUser = deleteTarget ? users.find(u => u.id === deleteTarget) : null
  const deleteTargetTaskCount = deleteTarget ? tasks.filter(t => t.assignee === deleteTarget).length : 0

  return (
    <div className="flex flex-col h-full relative">
      <div className="px-5 pt-6 pb-4 md:px-8 md:pt-8 bg-[#F6EFE3]">
        {selected ? (
          <button onClick={() => setSelected(null)} className="flex items-center gap-1 min-h-11 pr-3 -mt-2 mb-1 text-[#6E604E] text-sm">
            <span>←</span> <span>{es.members.allMembers}</span>
          </button>
        ) : null}
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs text-[#6E604E] font-medium uppercase tracking-widest">{es.members.household}</p>
            <h2 className="font-display text-2xl text-[#3D2E1E] mt-0.5">
              {selectedUser ? selectedUser.name : es.members.title}
            </h2>
          </div>
          {!selected && (
            <button
              onClick={() => setModal('add')}
              aria-label={es.members.addMember}
              title={es.members.addMember}
              className="w-11 h-11 shrink-0 rounded-full bg-[#C4623A] text-white text-xl flex items-center justify-center shadow-sm"
            >
              +
            </button>
          )}
          {selected && selectedUser && (
            <div className="flex gap-2">
              <button
                onClick={() => setModal('edit')}
                className="w-11 h-11 rounded-full bg-[#EDE4D4] flex items-center justify-center text-sm"
                title={es.members.edit}
                aria-label={es.members.edit}
              >
                ✎
              </button>
              <button
                onClick={() => setDeleteTarget(selected)}
                className="w-11 h-11 rounded-full bg-[#F5D4C2] flex items-center justify-center text-sm"
                title={es.members.delete}
                aria-label={es.members.delete}
              >
                🗑
              </button>
            </div>
          )}
        </div>
      </div>

      {!selected ? (
        <div className="flex-1 overflow-y-auto px-5 py-3 md:px-8 flex flex-col gap-3 md:grid md:grid-cols-2 md:content-start xl:grid-cols-3">
          {users.length === 0 && (
            <div className="col-span-full text-center pt-12">
              <p className="text-3xl mb-2">👥</p>
              <p className="text-sm text-[#6E604E]">{es.members.empty}</p>
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
                  <p className="text-[11px] text-[#6E604E]">{es.members.doneThisWeek(u.done, u.total)}</p>
                  <div className="mt-2 h-1.5 rounded-full bg-[#EDE4D4]">
                    <div className="h-full rounded-full transition-all" style={{ width: `${u.pct}%`, background: u.color }} />
                  </div>
                </div>
                <span className="text-sm font-bold ml-2" style={{ color: u.color }}>{u.pct}%</span>
              </div>
            </button>
          ))}

          {users.length > 0 && tasks.length > 0 && (
            <div className="col-span-full bg-[#3D2E1E] rounded-2xl px-4 py-4 mt-1">
              <p className="text-[11px] text-[#6E604E] uppercase tracking-widest mb-2">{es.members.householdTotal}</p>
              <div className="flex items-end justify-between">
                <div>
                  <p className="text-3xl font-display text-[#F6EFE3]">
                    {tasks.length ? Math.round((tasks.filter(t => t.done).length / tasks.length) * 100) : 0}%
                  </p>
                  <p className="text-xs text-[#6E604E] mt-0.5">{es.members.weeklyCompletion}</p>
                </div>
                <div className="flex -space-x-2">
                  {users.map(u => <Avatar key={u.id} user={u} size={28} />)}
                </div>
              </div>
            </div>
          )}
        </div>
      ) : selectedUser && selectedStat && (
        <div className="flex-1 overflow-y-auto px-5 py-3 md:px-8">
          <div className="rounded-2xl px-4 py-4 mb-4 text-white" style={{ background: selectedUser.color }}>
            <div className="flex items-center gap-3">
              <Avatar user={selectedUser} size={48} />
              <div>
                <p className="font-semibold text-lg">{selectedUser.name}</p>
                <p className="text-sm opacity-80">{es.members.doneAndPending(selectedStat.done, selectedStat.total - selectedStat.done)}</p>
              </div>
            </div>
            <div className="mt-3 h-2 rounded-full bg-white/30">
              <div className="h-full rounded-full bg-white transition-all" style={{ width: `${selectedStat.pct}%` }} />
            </div>
            <p className="text-xs opacity-70 mt-1">{es.members.percentComplete(selectedStat.pct)}</p>
          </div>

          <p className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] mb-2">{es.members.assignedTasks}</p>
          {userTasks.length === 0 && (
            <p className="text-xs text-[#6E604E] text-center pt-4">{es.members.noTasksAssigned}</p>
          )}
          <div className="flex flex-col gap-2 md:grid md:grid-cols-2 md:gap-3">
            {userTasks.map(task => (
              <div key={task.id} className="bg-white rounded-xl px-4 py-3 flex items-center gap-3">
                <span
                  className="w-5 h-5 rounded-full flex items-center justify-center text-xs shrink-0"
                  style={task.done ? { background: '#6B7C4E', color: '#fff' } : { background: '#EDE4D4', color: '#6E604E' }}
                >
                  {task.done ? '✓' : '○'}
                </span>
                <div className="flex-1 min-w-0">
                  <p className={`text-sm font-medium ${task.done ? 'line-through text-[#6E604E]' : 'text-[#3D2E1E]'}`}>{task.title}</p>
                  <div className="flex flex-wrap gap-1.5 mt-0.5">
                    <Badge label={taskCategoryText(task, categories)} color={taskCategoryColor(task, categories)} />
                    <Badge label={taskRecurrenceText(task, recurrenceTypes)} color="#6E604E" />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {modal === 'add' && (
        <MemberModal onSave={(data) => {
          const id = Math.max(0, ...users.map(user => user.id)) + 1
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
        <ConfirmDialog
          open
          illustration={<Avatar user={deleteTargetUser} size={52} />}
          title={es.members.removeTitle(deleteTargetUser.name)}
          message={deleteTargetTaskCount > 0 ? es.members.unassignWarning(deleteTargetTaskCount) : undefined}
          confirmLabel={es.members.remove}
          pending={deleting}
          error={deleteError}
          onConfirm={() => void deleteUser(deleteTarget)}
          onCancel={cancelDelete}
        />
      )}
    </div>
  )
}

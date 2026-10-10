import { useState } from 'react'
import type { Task } from '@/domain/tasks/task'
import type { Member as User } from '@/domain/members/member'
import { Avatar } from '@/presentation/shared/Avatar'
import { CATEGORIES, DAYS, RECURRENCES } from '@/infrastructure/demo/demoData'
import {
  categoryLabel,
  dayLabel,
  es,
  priorityLabel,
  recurrenceLabel,
} from '@/presentation/i18n/es'

const priorityColors = { low: '#6B7C4E', med: '#C4963A', high: '#C4623A' }

export type TaskFormData = {
  title: string
  assignee: number | ''
  day: string
  time: string
  category: string
  recurrence: string
  priority: Task['priority']
}

export function TaskModal({
  initial,
  defaultDay = 'Mon',
  users,
  onSave,
  onClose,  
}: {
  initial?: Task
  defaultDay?: string
  users: User[]
  onSave: (data: TaskFormData) => void
  onClose: () => void  
}) {
  const [title, setTitle] = useState(initial?.title ?? '')
  const [activeDay, setActiveDay] = useState(initial?.day ?? defaultDay)
  const [time, setTime] = useState(initial?.time ?? '')
  const [assignee, setAssignee] = useState<number | ''>(initial?.assignee ?? '')
  const [recurrence, setRecurrence] = useState(initial?.recurrence ?? 'none')
  const [priority, setPriority] = useState<Task['priority']>(initial?.priority ?? 'med')
  const [category, setCategory] = useState(initial?.category ?? CATEGORIES[0])

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
          {initial ? es.taskModal.editTitle : es.taskModal.addTitle}
        </h3>

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
                {dayLabel(d)}
            </button>
            ))}
        </div>

        <div className="mt-4">
            <input
              type="time"
              value={time}
              onChange={e => setTime(e.target.value)}
              className="w-full rounded-xl bg-white px-3 py-3 text-sm text-[#3D2E1E] outline-none"
            />
        </div>

        <div className="mt-3">
            <input
              type="text"
              value={title}
              onChange={e => setTitle(e.target.value)}
              placeholder={es.fields.taskName}
              className="w-full rounded-xl bg-white px-3 py-3 text-sm text-[#3D2E1E] outline-none placeholder:text-[#A89880]"
            />
        </div>

        <div>
            <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-1.5">{es.fields.assignedTo}</label>
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
            <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-1.5">{es.fields.category}</label>
            <div className="grid grid-cols-3 gap-2">
            {CATEGORIES.map(r => (
                <button
                key={r}
                onClick={() => setCategory(r)}
                className="py-2 rounded-xl text-xs font-medium transition-all"
                style={category === r
                    ? { background: '#3D2E1E', color: '#F6EFE3' }
                    : { background: '#fff', color: '#A89880' }}
                >
                {categoryLabel(r)}
                </button>
            ))}
            </div>
        </div>

        <div>
            <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-1.5">{es.fields.recurrence}</label>
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
                {recurrenceLabel(r)}
                </button>
            ))}
            </div>
        </div>

        <div>
            <label className="text-[10px] font-semibold uppercase tracking-widest text-[#A89880] block mb-1.5">{es.fields.priority}</label>
            <div className="flex gap-2">
            {(['low', 'med', 'high'] as const).map(p => (
                <button
                key={p}
                onClick={() => setPriority(p)}
                className="flex-1 py-2 rounded-xl text-xs font-semibold transition-all"
                style={priority === p
                    ? { background: priorityColors[p], color: '#fff' }
                    : { background: '#fff', color: '#A89880' }}
                >
                {priorityLabel(p)}
                </button>
            ))}
            </div>
        </div>


        <div className="flex gap-3">
          <button
            onClick={onClose}
            className="flex-1 py-3 rounded-2xl bg-[#EDE4D4] text-[#A89880] text-sm font-semibold"
          >
            {es.common.cancel}
          </button>
          <button
            onClick={() => onSave({
              title: title.trim(),
              assignee,
              day: activeDay,
              time,
              category,
              recurrence,
              priority,
            })}
            disabled={!title.trim()}
            className="flex-1 py-3 rounded-2xl text-white text-sm font-semibold transition-opacity disabled:opacity-40"
            style={{ background: '#C4623A' }}
          >
            {initial ? es.common.saveChanges : es.taskModal.addButton}
          </button>
        </div>
      </div>
    </div>
  )
}
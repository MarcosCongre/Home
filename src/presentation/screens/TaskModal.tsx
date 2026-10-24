import { useState } from 'react'
import type { Category, RecurrenceType } from '@/domain/catalogs/catalog'
import type { Task, TaskEditableFields } from '@/domain/tasks/task'
import type { Member as User } from '@/domain/members/member'
import { Avatar } from '@/presentation/shared/Avatar'
import { ConfirmDialog } from '@/presentation/shared/ConfirmDialog'
import { DAYS } from '@/infrastructure/demo/demoData'
import {
  categoryLabel,
  dayLabel,
  es,
  priorityLabel,
  recurrenceLabel,
} from '@/presentation/i18n/es'

const priorityColors = { low: '#6B7C4E', med: '#C4963A', high: '#C4623A' }

export type TaskFormData = TaskEditableFields

const chipStyle = (selected: boolean) => selected
  ? { background: '#3D2E1E', color: '#F6EFE3' }
  : { background: '#fff', color: '#6E604E' }

export function TaskModal({
  initial,
  defaultDay = 'Mon',
  users,
  categories,
  recurrenceTypes,
  onSave,
  onDelete,
  onClose,
}: {
  initial?: Task
  defaultDay?: string
  users: User[]
  categories: Category[]
  recurrenceTypes: RecurrenceType[]
  onSave: (data: TaskFormData) => void
  // Only offered when editing an existing task. Rejects when the delete fails.
  onDelete?: () => Promise<void>
  onClose: () => void
}) {
  const [title, setTitle] = useState(initial?.title ?? '')
  const [activeDay, setActiveDay] = useState(initial?.day ?? defaultDay)
  const [time, setTime] = useState(initial?.time ?? '')
  const [assignee, setAssignee] = useState<number | ''>(initial?.assignee ?? '')
  // A new task starts as one-off with no category.
  const [recurrenceId, setRecurrenceId] = useState<number | null>(initial?.recurrenceId ?? null)
  const [priority, setPriority] = useState<Task['priority']>(initial?.priority ?? 'med')
  const [categoryId, setCategoryId] = useState<number | null>(initial?.categoryId ?? null)
  const [repeatable, setRepeatable] = useState(initial?.repeatable ?? false)
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  // A dangling id (deleted entry) shows as "none"; it is only sent if changed.
  const noCategory = !categories.some(c => c.id === categoryId)
  const once = !recurrenceTypes.some(r => r.id === recurrenceId)

  const confirmDelete = async () => {
    if (!onDelete || deleting) return
    setDeleting(true)
    setDeleteError(null)
    try {
      // On success the parent closes (unmounts) this modal.
      await onDelete()
    } catch {
      setDeleteError(es.taskModal.deleteFailed)
      setDeleting(false)
    }
  }

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
          {initial ? es.taskModal.editTitle : es.taskModal.addTitle}
        </h3>

        <div className="flex gap-1.5 mt-4">
            {DAYS.map(d => (
            <button
                key={d}
                onClick={() => setActiveDay(d)}
                className="flex-1 min-h-11 py-2 rounded-xl text-xs font-semibold transition-all"
                style={activeDay === d
                ? { background: '#C4623A', color: '#fff' }
                : { background: '#EDE4D4', color: '#6E604E' }}
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
              className="w-full rounded-xl bg-white px-3 py-3 text-sm text-[#3D2E1E] outline-none placeholder:text-[#6E604E]"
            />
        </div>

        <div className="mt-4">
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

        <div className="mt-4">
            <label className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] block mb-1.5">{es.fields.category}</label>
            <div className="grid grid-cols-3 gap-2">
            <button
                onClick={() => setCategoryId(null)}
                className="min-h-11 py-2 px-1 rounded-xl text-xs font-medium transition-all"
                style={chipStyle(noCategory)}
            >
                {es.catalogs.noCategory}
            </button>
            {categories.map(c => (
                <button
                key={c.id}
                onClick={() => setCategoryId(c.id)}
                className="min-h-11 py-2 px-1 rounded-xl text-xs font-medium transition-all break-words"
                style={chipStyle(categoryId === c.id)}
                >
                {categoryLabel(c.name)}
                </button>
            ))}
            </div>
        </div>

        <div className="mt-4">
            <label className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] block mb-1.5">{es.fields.recurrence}</label>
            <div className="grid grid-cols-3 gap-2">
            <button
                onClick={() => setRecurrenceId(null)}
                className="min-h-11 py-2 px-1 rounded-xl text-xs font-medium transition-all"
                style={chipStyle(once)}
            >
                {es.catalogs.once}
            </button>
            {recurrenceTypes.map(r => (
                <button
                key={r.id}
                onClick={() => setRecurrenceId(r.id)}
                className="min-h-11 py-2 px-1 rounded-xl text-xs font-medium transition-all break-words"
                style={chipStyle(recurrenceId === r.id)}
                >
                {recurrenceLabel(r.name)}
                </button>
            ))}
            </div>
        </div>

        <div className="mt-4">
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

        <button
            type="button"
            role="switch"
            aria-checked={repeatable}
            onClick={() => setRepeatable(r => !r)}
            className="mt-4 w-full flex items-center gap-3 rounded-xl bg-white px-3 py-3 text-left"
        >
            <span className="flex-1 min-w-0">
              <span className="block text-sm font-medium text-[#3D2E1E]">{es.fields.repeatable}</span>
              <span className="block text-[11px] text-[#6E604E] mt-0.5">{es.fields.repeatableHint}</span>
            </span>
            <span
              className="shrink-0 w-11 h-6 rounded-full p-0.5 transition-colors"
              style={{ background: repeatable ? '#6B7C4E' : '#D8CEBC' }}
            >
              <span
                className="block w-5 h-5 rounded-full bg-white shadow-sm transition-transform"
                style={{ transform: repeatable ? 'translateX(20px)' : 'translateX(0)' }}
              />
            </span>
        </button>

        <div className="flex gap-3 mt-5">
          <button
            onClick={onClose}
            className="flex-1 py-3 rounded-2xl bg-[#EDE4D4] text-[#6E604E] text-sm font-semibold"
          >
            {es.common.cancel}
          </button>
          <button
            onClick={() => onSave({
              title: title.trim(),
              assignee,
              day: activeDay,
              time,
              categoryId,
              recurrenceId,
              priority,
              repeatable,
            })}
            disabled={!title.trim()}
            className="flex-1 py-3 rounded-2xl text-white text-sm font-semibold transition-opacity disabled:opacity-40"
            style={{ background: '#C4623A' }}
          >
            {initial ? es.common.saveChanges : es.taskModal.addButton}
          </button>
        </div>

        {initial && onDelete && (
          <button
            onClick={() => setConfirmingDelete(true)}
            disabled={deleting}
            className="w-full min-h-11 mt-3 py-2 rounded-2xl text-sm font-semibold text-[#B53A3A]"
          >
            {es.taskModal.delete}
          </button>
        )}
      </div>

      {initial && onDelete && (
        <ConfirmDialog
          open={confirmingDelete}
          title={es.taskModal.deleteTitle(initial.title)}
          message={es.taskModal.deleteMessage}
          confirmLabel={es.taskModal.deleteConfirm}
          pending={deleting}
          error={deleteError}
          onConfirm={() => void confirmDelete()}
          onCancel={() => {
            setConfirmingDelete(false)
            setDeleteError(null)
          }}
        />
      )}
    </div>
  )
}
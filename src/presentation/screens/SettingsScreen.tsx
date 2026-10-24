import { useId, useState, type FormEvent, type ReactNode } from 'react'

import {
  CATALOG_NAME_MAX_LENGTH,
  MAX_INTERVAL_DAYS,
  MIN_INTERVAL_DAYS,
  isValidColor,
  isValidIntervalDays,
  validateCatalogName,
  type Category,
  type CategoryDraft,
  type RecurrenceType,
  type RecurrenceTypeDraft,
} from '@/domain/catalogs/catalog'
import { ApiError } from '@/infrastructure/http/apiClient'
import { categoryLabel, es, recurrenceLabel } from '@/presentation/i18n/es'
import { PALETTE } from '@/presentation/screens/homeScreens'
import { ConfirmDialog } from '@/presentation/shared/ConfirmDialog'

type Catalog<T, D> = {
  items: T[]
  isLoading: boolean
  loadFailed: boolean
  load: () => Promise<void>
  add: (draft: D) => Promise<T>
  update: (id: number, draft: D) => Promise<T>
  remove: (id: number) => Promise<void>
}

type Editing =
  | { kind: 'category'; entry?: Category }
  | { kind: 'recurrence'; entry?: RecurrenceType }

type Deleting =
  | { kind: 'category'; entry: Category }
  | { kind: 'recurrence'; entry: RecurrenceType }

const labelClass = 'text-[11px] font-semibold uppercase tracking-widest text-[#6E604E] block mb-1.5'
const inputClass =
  'w-full bg-white rounded-xl px-4 py-3 text-sm text-[#3D2E1E] outline-none border border-transparent focus:border-[#C4623A] transition-colors'

function saveErrorMessage(error: unknown): string {
  if (error instanceof ApiError && error.status === 400) return es.settings.duplicateOrInvalid
  if (error instanceof ApiError && error.status === 404) return es.settings.notFound
  return es.settings.saveFailed
}

function nameErrorMessage(name: string): string | null {
  const error = validateCatalogName(name)
  if (error === 'required') return es.settings.nameRequired
  if (error === 'tooLong') return es.settings.nameTooLong(CATALOG_NAME_MAX_LENGTH)
  return null
}

// Default entries are stored in English and shown translated. If the user keeps the
// translated label untouched, send the stored name back so an edit does not rename it.
function resolveName(input: string, entry: { name: string } | undefined, label: (name: string) => string): string {
  const trimmed = input.trim()
  return entry && trimmed === label(entry.name) ? entry.name : trimmed
}

export function SettingsScreen({ categories, recurrenceTypes }: {
  categories: Catalog<Category, CategoryDraft>
  recurrenceTypes: Catalog<RecurrenceType, RecurrenceTypeDraft>
}) {
  const [editing, setEditing] = useState<Editing | null>(null)
  const [deleting, setDeleting] = useState<Deleting | null>(null)
  const [deletePending, setDeletePending] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const confirmDelete = async () => {
    if (!deleting || deletePending) return
    setDeletePending(true)
    setDeleteError(null)
    try {
      if (deleting.kind === 'category') await categories.remove(deleting.entry.id)
      else await recurrenceTypes.remove(deleting.entry.id)
      setDeleting(null)
    } catch (error) {
      // An entry that is already gone is the outcome the user wanted; reload to drop it.
      if (error instanceof ApiError && error.status === 404) {
        setDeleting(null)
        void (deleting.kind === 'category' ? categories.load() : recurrenceTypes.load())
      } else {
        setDeleteError(es.settings.deleteFailed)
      }
    } finally {
      setDeletePending(false)
    }
  }

  const cancelDelete = () => {
    setDeleting(null)
    setDeleteError(null)
  }

  const deletingName = deleting
    ? deleting.kind === 'category' ? categoryLabel(deleting.entry.name) : recurrenceLabel(deleting.entry.name)
    : ''

  return (
    <div className="flex flex-col h-full relative">
      <div className="px-5 pt-6 pb-4 md:px-8 md:pt-8 bg-[#F6EFE3]">
        <p className="text-xs text-[#6E604E] font-medium uppercase tracking-widest">{es.settings.household}</p>
        <h2 className="font-display text-2xl text-[#3D2E1E] mt-0.5">{es.settings.title}</h2>
      </div>

      <div className="flex-1 overflow-y-auto px-5 py-4 md:px-8 flex flex-col gap-6 md:grid md:grid-cols-2 md:content-start">
        <CatalogSection
          title={es.settings.categories.title}
          addLabel={es.settings.categories.add}
          emptyLabel={es.settings.categories.empty}
          catalog={categories}
          onAdd={() => setEditing({ kind: 'category' })}
        >
          {categories.items.map(category => (
            <CatalogRow
              key={category.id}
              name={categoryLabel(category.name)}
              leading={
                <span
                  className="w-4 h-4 rounded-full shrink-0 border"
                  style={category.color
                    ? { background: category.color, borderColor: category.color }
                    : { background: 'transparent', borderColor: '#B8AC98', borderStyle: 'dashed' }}
                  aria-hidden="true"
                />
              }
              onEdit={() => setEditing({ kind: 'category', entry: category })}
              onDelete={() => setDeleting({ kind: 'category', entry: category })}
            />
          ))}
        </CatalogSection>

        <CatalogSection
          title={es.settings.recurrences.title}
          addLabel={es.settings.recurrences.add}
          emptyLabel={es.settings.recurrences.empty}
          catalog={recurrenceTypes}
          onAdd={() => setEditing({ kind: 'recurrence' })}
        >
          {recurrenceTypes.items.map(recurrence => (
            <CatalogRow
              key={recurrence.id}
              name={recurrenceLabel(recurrence.name)}
              detail={es.settings.recurrences.everyNDays(recurrence.intervalDays)}
              onEdit={() => setEditing({ kind: 'recurrence', entry: recurrence })}
              onDelete={() => setDeleting({ kind: 'recurrence', entry: recurrence })}
            />
          ))}
        </CatalogSection>
      </div>

      {editing?.kind === 'category' && (
        <CategoryModal
          initial={editing.entry}
          onSave={draft => (editing.entry ? categories.update(editing.entry.id, draft) : categories.add(draft))}
          onClose={() => setEditing(null)}
        />
      )}
      {editing?.kind === 'recurrence' && (
        <RecurrenceModal
          initial={editing.entry}
          onSave={draft => (editing.entry ? recurrenceTypes.update(editing.entry.id, draft) : recurrenceTypes.add(draft))}
          onClose={() => setEditing(null)}
        />
      )}
      {deleting && (
        <ConfirmDialog
          open
          title={deleting.kind === 'category'
            ? es.settings.categories.deleteTitle(deletingName)
            : es.settings.recurrences.deleteTitle(deletingName)}
          message={es.settings.deleteMessage}
          confirmLabel={es.settings.deleteConfirm}
          pending={deletePending}
          error={deleteError}
          onConfirm={() => void confirmDelete()}
          onCancel={cancelDelete}
        />
      )}
    </div>
  )
}

function CatalogSection({ title, addLabel, emptyLabel, catalog, onAdd, children }: {
  title: string
  addLabel: string
  emptyLabel: string
  catalog: { items: unknown[]; isLoading: boolean; loadFailed: boolean; load: () => Promise<void> }
  onAdd: () => void
  children: ReactNode
}) {
  return (
    <section>
      <div className="flex items-center justify-between mb-2">
        <h3 className="text-[11px] font-semibold uppercase tracking-widest text-[#6E604E]">{title}</h3>
        <button
          onClick={onAdd}
          aria-label={addLabel}
          title={addLabel}
          className="w-11 h-11 shrink-0 rounded-full bg-[#C4623A] text-white text-xl flex items-center justify-center shadow-sm"
        >
          +
        </button>
      </div>

      {catalog.loadFailed && (
        <div role="alert" className="mb-2 rounded-xl bg-[#F5D4C2] pl-3 pr-1 py-0.5 text-xs font-medium text-[#B53A3A] flex items-center gap-3">
          <span className="flex-1">{es.settings.loadFailed}</span>
          <button onClick={() => void catalog.load()} className="min-h-11 px-2 underline underline-offset-2 shrink-0">
            {es.common.retry}
          </button>
        </div>
      )}

      {catalog.items.length === 0 && !catalog.isLoading && !catalog.loadFailed && (
        <p className="text-xs text-[#6E604E] text-center py-6">{emptyLabel}</p>
      )}

      {catalog.items.length > 0 && (
        <ul className="bg-white rounded-2xl shadow-sm divide-y divide-[#F6EFE3]">{children}</ul>
      )}
    </section>
  )
}

function CatalogRow({ name, detail, leading, onEdit, onDelete }: {
  name: string
  detail?: string
  leading?: ReactNode
  onEdit: () => void
  onDelete: () => void
}) {
  return (
    <li className="flex items-center gap-3 pl-4 pr-2 py-1.5">
      {leading}
      <div className="flex-1 min-w-0 py-1.5">
        <p className="text-sm font-medium text-[#3D2E1E] truncate">{name}</p>
        {detail && <p className="text-[11px] text-[#6E604E]">{detail}</p>}
      </div>
      <button
        onClick={onEdit}
        className="w-11 h-11 shrink-0 rounded-full bg-[#EDE4D4] flex items-center justify-center text-sm"
        title={es.settings.edit}
        aria-label={`${es.settings.edit}: ${name}`}
      >
        ✎
      </button>
      <button
        onClick={onDelete}
        className="w-11 h-11 shrink-0 rounded-full bg-[#F5D4C2] flex items-center justify-center text-sm"
        title={es.settings.delete}
        aria-label={`${es.settings.delete}: ${name}`}
      >
        🗑
      </button>
    </li>
  )
}

// Bottom sheet on mobile, centered dialog from md up (same shell as MemberModal).
function CatalogModal({ title, submitLabel, pending, error, onSubmit, onClose, children }: {
  title: string
  submitLabel: string
  pending: boolean
  error: string | null
  onSubmit: () => void
  onClose: () => void
  children: ReactNode
}) {
  const titleId = useId()
  const close = () => {
    if (!pending) onClose()
  }
  const submit = (event: FormEvent) => {
    event.preventDefault()
    if (!pending) onSubmit()
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-end md:items-center md:justify-center md:p-6"
      style={{ background: 'rgba(61,46,30,0.45)', backdropFilter: 'blur(4px)' }}
      onClick={close}
    >
      <form
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        noValidate
        onSubmit={submit}
        onKeyDown={event => {
          if (event.key === 'Escape') close()
        }}
        className="w-full max-h-[90dvh] overflow-y-auto bg-[#F6EFE3] rounded-t-3xl md:max-w-lg md:rounded-3xl px-5 pt-5 pb-[calc(env(safe-area-inset-bottom)+2rem)] md:pb-6"
        onClick={e => e.stopPropagation()}
      >
        <div className="w-10 h-1 rounded-full bg-[#D8CEBC] mx-auto mb-5 md:hidden" />
        <h3 id={titleId} className="font-display text-xl text-[#3D2E1E] mb-4">{title}</h3>

        {children}

        {error && (
          <p role="alert" className="mb-4 rounded-xl bg-[#F5D4C2] px-3 py-2 text-xs font-medium text-[#B53A3A]">
            {error}
          </p>
        )}

        <div className="flex gap-3">
          <button
            type="button"
            onClick={close}
            disabled={pending}
            className="flex-1 py-3 rounded-2xl bg-[#EDE4D4] text-[#6E604E] text-sm font-semibold transition-opacity disabled:opacity-40"
          >
            {es.common.cancel}
          </button>
          <button
            type="submit"
            disabled={pending}
            className="flex-1 py-3 rounded-2xl text-white text-sm font-semibold transition-opacity disabled:opacity-40"
            style={{ background: '#C4623A' }}
          >
            {pending ? es.settings.saving : submitLabel}
          </button>
        </div>
      </form>
    </div>
  )
}

// Runs a save, keeps the modal open with an inline message on failure and closes it on success.
function useSave<D>(onSave: (draft: D) => Promise<unknown>, onClose: () => void) {
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const save = async (draft: D) => {
    setPending(true)
    setError(null)
    try {
      await onSave(draft)
      onClose()
    } catch (saveError) {
      setError(saveErrorMessage(saveError))
      setPending(false)
    }
  }

  return { pending, error, save }
}

function NameField({ value, onChange, placeholder, error }: {
  value: string
  onChange: (value: string) => void
  placeholder: string
  error: string | null
}) {
  const id = useId()
  const errorId = useId()

  return (
    <div className="mb-4">
      <label htmlFor={id} className={labelClass}>{es.settings.name}</label>
      <input
        id={id}
        value={value}
        onChange={e => onChange(e.target.value)}
        placeholder={placeholder}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        className={inputClass}
        autoFocus
      />
      {error && <p id={errorId} className="mt-1.5 text-xs font-medium text-[#B53A3A]">{error}</p>}
    </div>
  )
}

function CategoryModal({ initial, onSave, onClose }: {
  initial?: Category
  onSave: (draft: CategoryDraft) => Promise<unknown>
  onClose: () => void
}) {
  const [name, setName] = useState(initial ? categoryLabel(initial.name) : '')
  const [color, setColor] = useState<string | null>(initial ? initial.color : PALETTE[0])
  const [submitted, setSubmitted] = useState(false)
  const { pending, error, save } = useSave(onSave, onClose)
  const nameError = submitted ? nameErrorMessage(name) : null

  const submit = () => {
    setSubmitted(true)
    if (nameErrorMessage(name) || !isValidColor(color)) return
    void save({ name: resolveName(name, initial, categoryLabel), color })
  }

  const swatch = (value: string | null, label: string, content?: ReactNode) => {
    const selected = color === value
    return (
      <button
        key={value ?? 'none'}
        type="button"
        onClick={() => setColor(value)}
        aria-label={label}
        aria-pressed={selected}
        title={label}
        className="w-full aspect-square rounded-full transition-transform flex items-center justify-center text-[#6E604E] text-sm"
        style={{
          background: value ?? 'transparent',
          border: value ? 'none' : '2px dashed #B8AC98',
          outline: selected ? `3px solid ${value ?? '#6E604E'}` : 'none',
          outlineOffset: 2,
          transform: selected ? 'scale(1.15)' : 'scale(1)',
        }}
      >
        {content}
      </button>
    )
  }

  const customColor = color !== null && !PALETTE.includes(color) ? color : null

  return (
    <CatalogModal
      title={initial ? es.settings.categories.editTitle : es.settings.categories.add}
      submitLabel={initial ? es.common.saveChanges : es.settings.categories.add}
      pending={pending}
      error={error}
      onSubmit={submit}
      onClose={onClose}
    >
      <NameField
        value={name}
        onChange={setName}
        placeholder={es.settings.categories.namePlaceholder}
        error={nameError}
      />

      <span className={labelClass}>{es.settings.categories.color}</span>
      <div className="grid grid-cols-7 gap-2 mb-5">
        {swatch(null, es.settings.categories.noColor, '⦸')}
        {PALETTE.map(c => swatch(c, c))}
        <label
          title={es.settings.categories.customColor}
          className="relative w-full aspect-square rounded-full flex items-center justify-center text-sm text-white cursor-pointer transition-transform"
          style={{
            background: customColor ?? 'conic-gradient(#C4623A, #C4963A, #6B7C4E, #3A8FB5, #9B7DB5, #C4623A)',
            outline: customColor ? `3px solid ${customColor}` : 'none',
            outlineOffset: 2,
            transform: customColor ? 'scale(1.15)' : 'scale(1)',
          }}
        >
          <span aria-hidden="true">+</span>
          <input
            type="color"
            aria-label={es.settings.categories.customColor}
            value={color ?? PALETTE[0]}
            onChange={e => setColor(e.target.value.toUpperCase())}
            className="absolute inset-0 opacity-0 cursor-pointer"
          />
        </label>
      </div>
    </CatalogModal>
  )
}

function RecurrenceModal({ initial, onSave, onClose }: {
  initial?: RecurrenceType
  onSave: (draft: RecurrenceTypeDraft) => Promise<unknown>
  onClose: () => void
}) {
  const [name, setName] = useState(initial ? recurrenceLabel(initial.name) : '')
  const [intervalText, setIntervalText] = useState(initial ? String(initial.intervalDays) : '7')
  const [submitted, setSubmitted] = useState(false)
  const { pending, error, save } = useSave(onSave, onClose)
  const intervalId = useId()
  const intervalErrorId = useId()

  const intervalDays = /^\d+$/.test(intervalText.trim()) ? Number(intervalText.trim()) : Number.NaN
  const intervalValid = isValidIntervalDays(intervalDays)
  const nameError = submitted ? nameErrorMessage(name) : null
  const intervalError = submitted && !intervalValid
    ? es.settings.recurrences.intervalInvalid(MIN_INTERVAL_DAYS, MAX_INTERVAL_DAYS)
    : null

  const submit = () => {
    setSubmitted(true)
    if (nameErrorMessage(name) || !intervalValid) return
    void save({ name: resolveName(name, initial, recurrenceLabel), intervalDays })
  }

  return (
    <CatalogModal
      title={initial ? es.settings.recurrences.editTitle : es.settings.recurrences.add}
      submitLabel={initial ? es.common.saveChanges : es.settings.recurrences.add}
      pending={pending}
      error={error}
      onSubmit={submit}
      onClose={onClose}
    >
      <NameField
        value={name}
        onChange={setName}
        placeholder={es.settings.recurrences.namePlaceholder}
        error={nameError}
      />

      <label htmlFor={intervalId} className={labelClass}>{es.settings.recurrences.interval}</label>
      <div className="flex items-center gap-3 mb-1.5">
        <input
          id={intervalId}
          type="number"
          inputMode="numeric"
          min={MIN_INTERVAL_DAYS}
          max={MAX_INTERVAL_DAYS}
          step={1}
          value={intervalText}
          onChange={e => setIntervalText(e.target.value)}
          aria-invalid={intervalError ? true : undefined}
          aria-describedby={intervalError ? intervalErrorId : undefined}
          className={`${inputClass} w-28`}
        />
        <span className="text-sm text-[#3D2E1E]">
          {es.settings.recurrences.intervalUnit(intervalValid ? intervalDays : 2)}
        </span>
      </div>
      <div className="mb-5">
        {intervalError && <p id={intervalErrorId} className="text-xs font-medium text-[#B53A3A]">{intervalError}</p>}
      </div>
    </CatalogModal>
  )
}

import { useEffect, useId, useRef, type ReactNode } from 'react'

import { es } from '@/presentation/i18n/es'

type ConfirmDialogProps = {
  open: boolean
  title: string
  message?: ReactNode
  confirmLabel: string
  cancelLabel?: string
  tone?: 'danger' | 'primary'
  confirmDisabled?: boolean
  // While the confirmed action runs, both buttons are locked and dismissing is ignored.
  pending?: boolean
  pendingLabel?: string
  // Failure message from the last attempt; the dialog stays open so the user can retry or cancel.
  error?: ReactNode
  // Optional visual above the title (e.g. an avatar) and extra body content (e.g. a picker).
  illustration?: ReactNode
  children?: ReactNode
  onConfirm: () => void
  onCancel: () => void
}

const confirmColors = { danger: '#B53A3A', primary: '#C4623A' }

export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel,
  cancelLabel = es.common.cancel,
  tone = 'danger',
  confirmDisabled = false,
  pending = false,
  pendingLabel = es.common.deleting,
  error,
  illustration,
  children,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const cancelRef = useRef<HTMLButtonElement>(null)
  const titleId = useId()
  const messageId = useId()
  const cancel = () => {
    if (!pending) onCancel()
  }
  // Keep the latest handler without re-running the focus effect on every parent render.
  const onCancelRef = useRef(cancel)
  onCancelRef.current = cancel

  useEffect(() => {
    if (!open) return

    // Focus the safe action first and give focus back to the trigger on close.
    const previouslyFocused = document.activeElement as HTMLElement | null
    cancelRef.current?.focus()

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.stopPropagation()
        onCancelRef.current()
      }
    }
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('keydown', onKeyDown)
      previouslyFocused?.focus?.()
    }
  }, [open])

  if (!open) return null

  return (
    <div
      className="fixed inset-0 z-[70] flex items-center justify-center px-6"
      style={{ background: 'rgba(61,46,30,0.45)', backdropFilter: 'blur(4px)' }}
      onClick={e => {
        // Keep the click from also reaching a parent modal's backdrop.
        e.stopPropagation()
        cancel()
      }}
    >
      <div
        role="alertdialog"
        aria-modal="true"
        aria-busy={pending}
        aria-labelledby={titleId}
        aria-describedby={message ? messageId : undefined}
        className="w-full max-w-sm max-h-[90dvh] overflow-y-auto bg-[#F6EFE3] rounded-3xl px-5 py-6"
        onClick={e => e.stopPropagation()}
      >
        {illustration && <div className="flex justify-center mb-3">{illustration}</div>}
        <h3 id={titleId} className="font-display text-xl text-[#3D2E1E] text-center mb-1">{title}</h3>
        {message && (
          <p id={messageId} className="text-xs text-[#6E604E] text-center">{message}</p>
        )}
        {children && <div className="mt-4">{children}</div>}
        {error && (
          <p role="alert" className="mt-4 rounded-xl bg-[#F5D4C2] px-3 py-2 text-xs font-medium text-[#B53A3A] text-center">
            {error}
          </p>
        )}
        <div className="flex gap-3 mt-5">
          <button
            ref={cancelRef}
            onClick={cancel}
            disabled={pending}
            className="flex-1 py-3 rounded-2xl bg-[#EDE4D4] text-[#6E604E] text-sm font-semibold transition-opacity disabled:opacity-40"
          >
            {cancelLabel}
          </button>
          <button
            onClick={onConfirm}
            disabled={confirmDisabled || pending}
            className="flex-1 py-3 rounded-2xl text-white text-sm font-semibold transition-opacity disabled:opacity-40"
            style={{ background: confirmColors[tone] }}
          >
            {pending ? pendingLabel : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}

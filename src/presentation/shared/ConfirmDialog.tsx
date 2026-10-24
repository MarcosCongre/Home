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
  illustration,
  children,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const cancelRef = useRef<HTMLButtonElement>(null)
  const titleId = useId()
  const messageId = useId()
  // Keep the latest handler without re-running the focus effect on every parent render.
  const onCancelRef = useRef(onCancel)
  onCancelRef.current = onCancel

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
      onClick={onCancel}
    >
      <div
        role="alertdialog"
        aria-modal="true"
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
        <div className="flex gap-3 mt-5">
          <button
            ref={cancelRef}
            onClick={onCancel}
            className="flex-1 py-3 rounded-2xl bg-[#EDE4D4] text-[#6E604E] text-sm font-semibold"
          >
            {cancelLabel}
          </button>
          <button
            onClick={onConfirm}
            disabled={confirmDisabled}
            className="flex-1 py-3 rounded-2xl text-white text-sm font-semibold transition-opacity disabled:opacity-40"
            style={{ background: confirmColors[tone] }}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}

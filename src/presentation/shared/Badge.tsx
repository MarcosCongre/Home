type BadgeProps = {
  label: string
  color: string
}

export function Badge({ label, color }: BadgeProps) {
  return (
    <span className="text-[10px] font-medium px-1.5 py-0.5 rounded-full" style={{ background: color + '22', color }}>
      {label}
    </span>
  )
}

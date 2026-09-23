import type { Member } from '@/domain/members/member'

type AvatarProps = {
  user: Member
  size?: number
}

export function Avatar({ user, size = 28 }: AvatarProps) {
  return (
    <span
      style={{ width: size, height: size, background: user.color, fontSize: size * 0.42 }}
      className="inline-flex items-center justify-center rounded-full text-white font-semibold shrink-0"
    >
      {user.avatar}
    </span>
  )
}

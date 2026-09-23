import { environment } from '@/infrastructure/config/environment'
import { request } from '@/infrastructure/http/apiClient'
import type { Member } from '@/domain/members/member'

export type MemberDto = Member & {
  householdId?: string
}

function normalizeMember(member: MemberDto): Member {
  return {
    id: member.id,
    name: member.name,
    avatar: member.avatar,
    color: member.color,
    householdId: member.householdId ?? environment.householdId,
  }
}

export async function listMembers(): Promise<Member[]> {
  const payload = await request<MemberDto[]>(`${environment.apiBaseUrl}/members?householdId=${encodeURIComponent(environment.householdId)}`)
  return payload.map(normalizeMember)
}

export async function createMember(data: Omit<Member, 'id'> & { householdId?: string }): Promise<Member> {
  const payload = await request<MemberDto>(`${environment.apiBaseUrl}/members`, {
    method: 'POST',
    body: JSON.stringify({
      name: data.name,
      avatar: data.avatar,
      color: data.color,
      householdId: data.householdId ?? environment.householdId,
    }),
  })

  return normalizeMember(payload)
}

export async function updateMember(userId: string, data: Omit<Member, 'id'> & { householdId?: string }): Promise<Member> {
  const payload = await request<MemberDto>(`${environment.apiBaseUrl}/members/${encodeURIComponent(userId)}`, {
    method: 'PATCH',
    body: JSON.stringify({
      name: data.name,
      avatar: data.avatar,
      color: data.color,
      householdId: data.householdId ?? environment.householdId,
    }),
  })

  return normalizeMember(payload)
}

export async function deleteMember(userId: string): Promise<void> {
  await request<void>(`${environment.apiBaseUrl}/members/${encodeURIComponent(userId)}`, {
    method: 'DELETE',
  })
}

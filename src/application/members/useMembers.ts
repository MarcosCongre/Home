import { useCallback, useEffect, useReducer } from 'react'

import type { Member } from '@/domain/members/member'
import { environment } from '@/infrastructure/config/environment'
import { INIT_USERS } from '@/infrastructure/demo/demoData'
import { createMember, deleteMember, listMembers, updateMember } from '@/infrastructure/http/memberApi'

type MemberState = {
  users: Member[]
  isLoading: boolean
  error: string | null
}

type MemberAction =
  | { type: 'load-start' }
  | { type: 'load-success'; users: Member[] }
  | { type: 'load-failure' }
  | { type: 'set-users'; users: Member[] }
  | { type: 'add-user'; user: Member }
  | { type: 'update-user'; userId: string; data: Omit<Member, 'id'> }
  | { type: 'remove-user'; userId: string }

const initialState: MemberState = {
  users: environment.demoMode ? INIT_USERS : [],
  isLoading: false,
  error: null,
}

function memberReducer(state: MemberState, action: MemberAction): MemberState {
  switch (action.type) {
    case 'load-start':
      return { ...state, isLoading: true, error: null }

    case 'load-success':
      return { users: action.users, isLoading: false, error: null }

    case 'load-failure':
      return { users: [], isLoading: false, error: 'Unable to load members. Check the API connection and retry.' }

    case 'set-users':
      return { ...state, users: action.users }

    case 'add-user':
      return { ...state, users: [...state.users, action.user] }

    case 'update-user': {
      const nextUsers = state.users.map(user =>
        user.id === action.userId ? { ...user, ...action.data } : user,
      )
      return { ...state, users: nextUsers }
    }

    case 'remove-user':
      return { ...state, users: state.users.filter(user => user.id !== action.userId) }

    default:
      return state
  }
}

export function useMembers() {
  const [state, dispatch] = useReducer(memberReducer, initialState)

  const loadMembers = useCallback(async () => {
    dispatch({ type: 'load-start' })

    try {
      const members = await listMembers()
      dispatch({ type: 'load-success', users: members })
    } catch {
      dispatch({ type: 'load-failure' })
    }
  }, [])

  const setUsers = useCallback((users: Member[]) => {
    dispatch({ type: 'set-users', users })
  }, [])

  const addUser = useCallback(async (user: Member) => {
    try {
      const created = await createMember(user)
      dispatch({ type: 'add-user', user: created })
      return created
    } catch (error) {
      throw error
    }
  }, [])

  const updateUser = useCallback(async (userId: string, data: Omit<Member, 'id'>) => {
    try {
      const updated = await updateMember(userId, data)
      dispatch({ type: 'update-user', userId, data: updated })
      return updated
    } catch (error) {
      throw error
    }
  }, [])

  const removeUser = useCallback(async (userId: string) => {
    try {
      await deleteMember(userId)
      dispatch({ type: 'remove-user', userId })
    } catch (error) {
      throw error
    }
  }, [])

  useEffect(() => {
    void loadMembers()
  }, [loadMembers])

  return {
    users: state.users,
    isLoading: state.isLoading,
    error: state.error,
    setUsers,
    addUser,
    updateUser,
    removeUser,
    loadMembers,
  }
}

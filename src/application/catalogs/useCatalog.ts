import { useCallback, useEffect, useReducer } from 'react'

import { sameCatalogName } from '@/domain/catalogs/catalog'
import { environment } from '@/infrastructure/config/environment'
import { ApiError } from '@/infrastructure/http/apiClient'

type CatalogEntry = { id: number; name: string }

export type CatalogGateway<T extends CatalogEntry, D> = {
  list: () => Promise<T[]>
  create: (draft: D) => Promise<T>
  update: (id: number, draft: D) => Promise<T>
  remove: (id: number) => Promise<void>
}

type CatalogState<T> = {
  items: T[]
  isLoading: boolean
  loadFailed: boolean
}

type CatalogAction<T> =
  | { type: 'load-start' }
  | { type: 'load-success'; items: T[] }
  | { type: 'load-failure' }
  | { type: 'add'; item: T }
  | { type: 'replace'; item: T }
  | { type: 'remove'; id: number }

function catalogReducer<T extends CatalogEntry>(state: CatalogState<T>, action: CatalogAction<T>): CatalogState<T> {
  switch (action.type) {
    case 'load-start':
      return { ...state, isLoading: true, loadFailed: false }

    case 'load-success':
      return { items: action.items, isLoading: false, loadFailed: false }

    case 'load-failure':
      return { ...state, isLoading: false, loadFailed: true }

    case 'add':
      return { ...state, items: [...state.items, action.item] }

    case 'replace':
      return { ...state, items: state.items.map(item => (item.id === action.item.id ? action.item : item)) }

    case 'remove':
      return { ...state, items: state.items.filter(item => item.id !== action.id) }

    default:
      return state
  }
}

// In demo mode there is no backend: entries live in memory, with the same duplicate-name rule (400).
function demoGateway<T extends CatalogEntry, D extends { name: string }>(
  items: T[],
  build: (id: number, draft: D) => T,
): CatalogGateway<T, D> {
  const assertExists = (id: number) => {
    if (!items.some(item => item.id === id)) throw new ApiError('Catalog entry not found', 404)
  }
  const assertUniqueName = (name: string, exceptId?: number) => {
    if (items.some(item => item.id !== exceptId && sameCatalogName(item.name, name))) {
      throw new ApiError('Duplicate catalog name', 400)
    }
  }

  return {
    list: async () => items,
    create: async draft => {
      assertUniqueName(draft.name)
      return build(Math.max(0, ...items.map(item => item.id)) + 1, draft)
    },
    update: async (id, draft) => {
      assertExists(id)
      assertUniqueName(draft.name, id)
      return build(id, draft)
    },
    remove: async id => {
      assertExists(id)
    },
  }
}

export function useCatalog<T extends CatalogEntry, D extends { name: string }>(
  apiGateway: CatalogGateway<T, D>,
  demoItems: T[],
  buildDemoItem: (id: number, draft: D) => T,
) {
  const [state, dispatch] = useReducer(catalogReducer<T>, {
    items: environment.demoMode ? demoItems : [],
    isLoading: false,
    loadFailed: false,
  })

  // Rebuilt every render so the demo gateway always sees the current items.
  const gateway = environment.demoMode ? demoGateway(state.items, buildDemoItem) : apiGateway

  const load = useCallback(async () => {
    if (environment.demoMode) return
    dispatch({ type: 'load-start' })
    try {
      dispatch({ type: 'load-success', items: await apiGateway.list() })
    } catch {
      dispatch({ type: 'load-failure' })
    }
  }, [apiGateway])

  const add = async (draft: D) => {
    const created = await gateway.create(draft)
    dispatch({ type: 'add', item: created })
    return created
  }

  const update = async (id: number, draft: D) => {
    const updated = await gateway.update(id, draft)
    dispatch({ type: 'replace', item: updated })
    return updated
  }

  const remove = async (id: number) => {
    await gateway.remove(id)
    dispatch({ type: 'remove', id })
  }

  useEffect(() => {
    void load()
  }, [load])

  return {
    items: state.items,
    isLoading: state.isLoading,
    loadFailed: state.loadFailed,
    load,
    add,
    update,
    remove,
  }
}

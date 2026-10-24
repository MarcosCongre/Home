import type { Category, CategoryDraft } from '@/domain/catalogs/catalog'
import { environment } from '@/infrastructure/config/environment'
import { DEMO_CATEGORIES } from '@/infrastructure/demo/demoData'
import { createCategory, deleteCategory, listCategories, updateCategory } from '@/infrastructure/http/categoryApi'
import { useCatalog, type CatalogGateway } from '@/application/catalogs/useCatalog'

const categoryGateway: CatalogGateway<Category, CategoryDraft> = {
  list: listCategories,
  create: createCategory,
  update: updateCategory,
  remove: deleteCategory,
}

const buildDemoCategory = (id: number, draft: CategoryDraft): Category => ({
  id,
  householdId: environment.householdId,
  name: draft.name,
  color: draft.color,
})

export function useCategories() {
  return useCatalog(categoryGateway, DEMO_CATEGORIES, buildDemoCategory)
}

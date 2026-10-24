import type { RecurrenceType, RecurrenceTypeDraft } from '@/domain/catalogs/catalog'
import { environment } from '@/infrastructure/config/environment'
import { DEMO_RECURRENCE_TYPES } from '@/infrastructure/demo/demoData'
import {
  createRecurrenceType,
  deleteRecurrenceType,
  listRecurrenceTypes,
  updateRecurrenceType,
} from '@/infrastructure/http/recurrenceTypeApi'
import { useCatalog, type CatalogGateway } from '@/application/catalogs/useCatalog'

const recurrenceTypeGateway: CatalogGateway<RecurrenceType, RecurrenceTypeDraft> = {
  list: listRecurrenceTypes,
  create: createRecurrenceType,
  update: updateRecurrenceType,
  remove: deleteRecurrenceType,
}

const buildDemoRecurrenceType = (id: number, draft: RecurrenceTypeDraft): RecurrenceType => ({
  id,
  householdId: environment.householdId,
  name: draft.name,
  intervalDays: draft.intervalDays,
})

export function useRecurrenceTypes() {
  return useCatalog(recurrenceTypeGateway, DEMO_RECURRENCE_TYPES, buildDemoRecurrenceType)
}

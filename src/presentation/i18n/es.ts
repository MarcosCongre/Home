// Spanish UI copy and display-only label maps.
// Stored/API values (days, categories, recurrences, priorities) never change;
// they are rendered through these maps and fall back to the raw value.

type LabelMap = Readonly<Record<string, string>>

export function labelFor(map: LabelMap, value: string): string {
  return Object.prototype.hasOwnProperty.call(map, value) ? map[value] : value
}

export const DAY_LABELS: LabelMap = {
  Mon: 'Lun',
  Tue: 'Mar',
  Wed: 'Mié',
  Thu: 'Jue',
  Fri: 'Vie',
  Sat: 'Sáb',
  Sun: 'Dom',
}

export const CATEGORY_LABELS: LabelMap = {
  All: 'Todas',
  Cleaning: 'Limpieza',
  Laundry: 'Lavandería',
  Pets: 'Mascotas',
  Garden: 'Jardín',
  Shopping: 'Compras',
  Waste: 'Residuos',
  Bills: 'Facturas',
  Kitchen: 'Cocina',
  Uncategorized: 'Sin categoría',
}

export const RECURRENCE_LABELS: LabelMap = {
  Daily: 'Diaria',
  Weekly: 'Semanal',
  Biweekly: 'Quincenal',
  'Mon & Thu': 'Lun y Jue',
  'Tue & Fri': 'Mar y Vie',
  Monthly: 'Mensual',
  Once: 'Una vez',
  none: 'Sin repetición',
}

export const PRIORITY_LABELS: LabelMap = {
  low: 'Baja',
  med: 'Media',
  medium: 'Media',
  high: 'Alta',
}

export const dayLabel = (value: string) => labelFor(DAY_LABELS, value)
export const categoryLabel = (value: string) => labelFor(CATEGORY_LABELS, value)
export const recurrenceLabel = (value: string) => labelFor(RECURRENCE_LABELS, value)
export const priorityLabel = (value: string) => labelFor(PRIORITY_LABELS, value)

export const es = {
  appTitle: 'Tareas del hogar',
  common: {
    all: 'Todos',
    allDays: 'Todos',
    cancel: 'Cancelar',
    saveChanges: 'Guardar cambios',
    back: 'Volver',
    retry: 'Reintentar',
    dismiss: 'Descartar alerta',
    deleting: 'Eliminando…',
  },
  tabs: {
    home: 'Inicio',
    calendar: 'Calendario',
    alerts: 'Alertas',
    members: 'Miembros',
    settings: 'Configuración',
  },
  fields: {
    taskName: 'Nombre de la tarea',
    assignedTo: 'Asignada a',
    category: 'Categoría',
    recurrence: 'Recurrencia',
    priority: 'Prioridad',
    repeatable: 'Se repite varias veces al día',
    repeatableHint: 'Al completarla vuelve a quedar pendiente para quien elijas.',
  },
  dashboard: {
    date: 'Miércoles, 16 sep',
    greeting: 'Buenos días,',
    total: 'Total',
    done: 'Hechas',
    pending: 'Pendientes',
    completeThisWeek: (pct: number) => `${pct}% completado esta semana`,
    autoAssign: 'Repartir tareas',
    autoAssigning: 'Repartiendo…',
    autoAssignFailed: 'No se pudieron repartir las tareas. Intenta de nuevo.',
  },
  calendar: {
    weekOf: 'Semana del 15 sep',
    title: 'Calendario',
    addTask: 'Agregar tarea',
    noTasksFor: (day: string) => `No hay tareas para ${day}`,
    noTasks: 'No hay tareas',
  },
  alerts: {
    today: 'Hoy',
    title: 'Alertas',
    clearAll: 'Borrar todo',
    needsAttention: 'Requiere atención',
    earlier: 'Anteriores',
    allCaughtUp: '¡Todo al día!',
  },
  taskModal: {
    addTitle: 'Agregar tarea',
    editTitle: 'Editar tarea',
    addButton: 'Agregar tarea',
    delete: 'Eliminar tarea',
    deleteTitle: (title: string) => `¿Eliminar «${title}»?`,
    deleteMessage: 'Esta acción no se puede deshacer.',
    deleteConfirm: 'Eliminar',
    deleteFailed: 'No se pudo eliminar la tarea. Intenta de nuevo.',
  },
  repeatable: {
    badge: 'Se repite',
    nextTitle: '¿Quién la hace ahora?',
    nextMessage: (title: string) => `«${title}» volverá a quedar pendiente para quien elijas.`,
    complete: 'Completar',
  },
  taskDetail: {
    completionHistory: 'Historial de cumplimiento',
    noHistory: 'Aún no hay historial',
    completedOn: (date: string) => `Completada el ${date}`,
  },
  members: {
    household: 'Hogar',
    title: 'Miembros',
    allMembers: 'Todos los miembros',
    addMember: 'Agregar miembro',
    editMember: 'Editar miembro',
    edit: 'Editar',
    delete: 'Eliminar',
    name: 'Nombre',
    namePlaceholder: 'Nombre del miembro',
    color: 'Color',
    empty: 'Aún no hay miembros. ¡Agrega uno!',
    doneThisWeek: (done: number, total: number) =>
      `${done} de ${total} ${total === 1 ? 'tarea hecha' : 'tareas hechas'} esta semana`,
    householdTotal: 'Total del hogar',
    weeklyCompletion: 'Cumplimiento semanal',
    doneAndPending: (done: number, pending: number) =>
      `${done} ${done === 1 ? 'hecha' : 'hechas'} · ${pending} ${pending === 1 ? 'pendiente' : 'pendientes'}`,
    percentComplete: (pct: number) => `${pct}% completado`,
    assignedTasks: 'Tareas asignadas',
    noTasksAssigned: 'No hay tareas asignadas',
    removeTitle: (name: string) => `¿Eliminar a ${name}?`,
    unassignWarning: (count: number) =>
      count === 1
        ? 'Su tarea quedará sin asignar.'
        : `Sus ${count} tareas quedarán sin asignar.`,
    remove: 'Eliminar',
    removeFailed: 'No se pudo eliminar al miembro. Intenta de nuevo.',
  },
  settings: {
    household: 'Hogar',
    title: 'Configuración',
    edit: 'Editar',
    delete: 'Eliminar',
    name: 'Nombre',
    saving: 'Guardando…',
    loadFailed: 'No se pudo cargar la lista. Verifica la conexión con la API.',
    nameRequired: 'El nombre es obligatorio.',
    nameTooLong: (max: number) => `El nombre no puede superar los ${max} caracteres.`,
    duplicateOrInvalid: 'Ya existe una entrada con ese nombre o los datos no son válidos.',
    notFound: 'Esta entrada ya no existe. Recarga la lista.',
    saveFailed: 'No se pudieron guardar los cambios. Intenta de nuevo.',
    deleteMessage: 'Las tareas que la usan la conservarán, pero ya no podrás elegirla.',
    deleteConfirm: 'Eliminar',
    deleteFailed: 'No se pudo eliminar. Intenta de nuevo.',
    categories: {
      title: 'Categorías',
      add: 'Agregar categoría',
      editTitle: 'Editar categoría',
      namePlaceholder: 'Nombre de la categoría',
      color: 'Color',
      noColor: 'Sin color',
      customColor: 'Elegir otro color',
      empty: 'Aún no hay categorías.',
      deleteTitle: (name: string) => `¿Eliminar la categoría «${name}»?`,
    },
    recurrences: {
      title: 'Recurrencias',
      add: 'Agregar recurrencia',
      editTitle: 'Editar recurrencia',
      namePlaceholder: 'Nombre de la recurrencia',
      interval: 'Se repite cada',
      intervalUnit: (days: number) => (days === 1 ? 'día' : 'días'),
      everyNDays: (days: number) => (days === 1 ? 'Cada día' : `Cada ${days} días`),
      intervalInvalid: (min: number, max: number) => `Ingresa un número entero de días entre ${min} y ${max}.`,
      empty: 'Aún no hay recurrencias.',
      deleteTitle: (name: string) => `¿Eliminar la recurrencia «${name}»?`,
    },
  },
} as const

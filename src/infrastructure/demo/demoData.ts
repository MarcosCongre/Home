import type { Alert } from '@/domain/alerts/alert'
import type { Member } from '@/domain/members/member'
import type { Task } from '@/domain/tasks/task'

export const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
export const CATEGORIES = ['All', 'Cleaning', 'Laundry', 'Pets', 'Garden', 'Shopping', 'Waste', 'Bills', 'Kitchen']
export const RECURRENCES = ['Daily', 'Weekly', 'Biweekly', 'Mon & Thu', 'Tue & Fri', 'Monthly']

export const INIT_USERS: Member[] = [
  { id: 1, name: 'Maya', avatar: 'M', color: '#C4623A' },
  { id: 2, name: 'James', avatar: 'J', color: '#6B7C4E' },
  { id: 3, name: 'Lily', avatar: 'L', color: '#9B7DB5' },
  { id: 4, name: 'Archie', avatar: 'A', color: '#C4963A' },
]

export const INIT_TASKS: Task[] = [
  { id: 1, title: 'Aspirar la sala', assignee: 1, category: 'Cleaning', recurrence: 'Weekly', done: false, day: 'Mon', time: '10:00', priority: 'med', history: ['9 sep', '2 sep', '26 ago'] },
  { id: 2, title: 'Sacar la basura', assignee: 2, category: 'Waste', recurrence: 'Tue & Fri', done: true, day: 'Tue', time: '07:30', priority: 'high', history: ['10 sep', '6 sep', '30 ago'] },
  { id: 3, title: 'Alimentar a Luna', assignee: 3, category: 'Pets', recurrence: 'Daily', done: false, day: 'Mon', time: '08:00', priority: 'high', history: ['11 sep', '10 sep', '9 sep'] },
  { id: 4, title: 'Hacer las compras', assignee: 1, category: 'Shopping', recurrence: 'Weekly', done: false, day: 'Wed', time: '14:00', priority: 'med', history: ['8 sep', '1 sep', '25 ago'] },
  { id: 5, title: 'Lavar la ropa de cama', assignee: 2, category: 'Laundry', recurrence: 'Biweekly', done: false, day: 'Sat', time: '09:00', priority: 'low', history: ['6 sep', '23 ago'] },
  { id: 6, title: 'Trapear el piso de la cocina', assignee: 4, category: 'Cleaning', recurrence: 'Weekly', done: false, day: 'Thu', time: '11:00', priority: 'med', history: ['5 sep', '29 ago'] },
  { id: 7, title: 'Regar las plantas', assignee: 3, category: 'Garden', recurrence: 'Mon & Thu', done: true, day: 'Mon', time: '09:00', priority: 'low', history: ['9 sep', '5 sep', '2 sep'] },
  { id: 8, title: 'Limpiar el baño', assignee: 1, category: 'Cleaning', recurrence: 'Weekly', done: false, day: 'Fri', time: '10:00', priority: 'med', history: ['5 sep', '29 ago'] },
  { id: 9, title: 'Pagar la factura de luz', assignee: 2, category: 'Bills', recurrence: 'Monthly', done: false, day: 'Wed', time: '—', priority: 'high', history: ['15 ago', '15 jul'] },
  { id: 10, title: 'Poner el lavavajillas', assignee: 4, category: 'Kitchen', recurrence: 'Daily', done: false, day: 'Tue', time: '20:00', priority: 'low', history: ['10 sep', '9 sep', '8 sep'] },
]

export const NOTIFS: Alert[] = [
  { id: 1, icon: '🫧', title: 'Lavado terminado', body: 'El ciclo terminó: recuerda pasar la ropa a la secadora.', time: '09:39 AM', urgent: true, user: 'J', status: 'unread' },
  { id: 2, icon: '🗑️', title: 'Recolección de basura mañana', body: 'Recolección del martes: los contenedores deben estar afuera antes de las 7 a. m.', time: '08:40 AM', urgent: true, user: 'J', status: 'unread' },
  { id: 3, icon: '🐾', title: 'Cena de Luna', body: 'Lily, Luna todavía no ha comido hoy.', time: '06:41 AM', urgent: true, user: 'L', status: 'unread' },
  { id: 4, icon: '🌿', title: 'Las plantas necesitan agua', body: 'Toca el riego del jueves: monstera y potus.', time: '04:41 AM', urgent: false, user: 'L', status: 'unread' },
  { id: 5, icon: '🛒', title: 'Lista de compras lista', body: 'Maya agregó 8 artículos. ¿Los compras de camino a casa?', time: '07:15 PM', urgent: false, user: 'M', status: 'unread' },
  { id: 6, icon: '💡', title: 'Vence la factura de luz', body: 'Vence en 3 días: $84.50 a través del portal del proveedor.', time: '02:15 PM', urgent: false, user: 'J', status: 'unread' },
  { id: 7, icon: '🧹', title: 'Resumen semanal de limpieza', body: '6 de 8 tareas completadas esta semana. ¡Buen trabajo!', time: '11:30 AM', urgent: false, user: null, status: 'unread' },
]

export const categoryColor: Record<string, string> = {
  Cleaning: '#C4623A', Laundry: '#9B7DB5', Pets: '#C4963A',
  Garden: '#6B7C4E', Shopping: '#3A8FB5', Waste: '#7C5E3A',
  Bills: '#B53A3A', Kitchen: '#3A7C8F',
}

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
  { id: 1, title: 'Vacuum living room', assignee: 1, category: 'Cleaning', recurrence: 'Weekly', done: false, day: 'Mon', time: '10:00', priority: 'med', history: ['Sep 9', 'Sep 2', 'Aug 26'] },
  { id: 2, title: 'Take out trash', assignee: 2, category: 'Waste', recurrence: 'Tue & Fri', done: true, day: 'Tue', time: '07:30', priority: 'high', history: ['Sep 10', 'Sep 6', 'Aug 30'] },
  { id: 3, title: 'Feed Luna', assignee: 3, category: 'Pets', recurrence: 'Daily', done: false, day: 'Mon', time: '08:00', priority: 'high', history: ['Sep 11', 'Sep 10', 'Sep 9'] },
  { id: 4, title: 'Grocery run', assignee: 1, category: 'Shopping', recurrence: 'Weekly', done: false, day: 'Wed', time: '14:00', priority: 'med', history: ['Sep 8', 'Sep 1', 'Aug 25'] },
  { id: 5, title: 'Wash bed linens', assignee: 2, category: 'Laundry', recurrence: 'Biweekly', done: false, day: 'Sat', time: '09:00', priority: 'low', history: ['Sep 6', 'Aug 23'] },
  { id: 6, title: 'Mop kitchen floor', assignee: 4, category: 'Cleaning', recurrence: 'Weekly', done: false, day: 'Thu', time: '11:00', priority: 'med', history: ['Sep 5', 'Aug 29'] },
  { id: 7, title: 'Water the plants', assignee: 3, category: 'Garden', recurrence: 'Mon & Thu', done: true, day: 'Mon', time: '09:00', priority: 'low', history: ['Sep 9', 'Sep 5', 'Sep 2'] },
  { id: 8, title: 'Clean bathroom', assignee: 1, category: 'Cleaning', recurrence: 'Weekly', done: false, day: 'Fri', time: '10:00', priority: 'med', history: ['Sep 5', 'Aug 29'] },
  { id: 9, title: 'Pay electricity bill', assignee: 2, category: 'Bills', recurrence: 'Monthly', done: false, day: 'Wed', time: '—', priority: 'high', history: ['Aug 15', 'Jul 15'] },
  { id: 10, title: 'Run dishwasher', assignee: 4, category: 'Kitchen', recurrence: 'Daily', done: false, day: 'Tue', time: '20:00', priority: 'low', history: ['Sep 10', 'Sep 9', 'Sep 8'] },
]

export const NOTIFS = [
  { id: 'n1', icon: '🫧', title: 'Washing machine done', body: 'Cycle finished — remember to move clothes to the dryer.', time: '2 min ago', urgent: true, user: 'J' },
  { id: 'n2', icon: '🗑️', title: 'Trash pickup tomorrow', body: 'Tuesday collection — bins need to be out by 7 am.', time: '1 hr ago', urgent: true, user: 'J' },
  { id: 'n3', icon: '🐾', title: "Luna's evening feed", body: "Lily, Luna hasn't been fed yet today.", time: '3 hr ago', urgent: true, user: 'L' },
  { id: 'n4', icon: '🌿', title: 'Plants need water', body: 'Thursday watering is due — monstera and pothos.', time: '5 hr ago', urgent: false, user: 'L' },
  { id: 'n5', icon: '🛒', title: 'Grocery list ready', body: 'Maya added 8 items. Pick up on the way home?', time: 'Yesterday', urgent: false, user: 'M' },
  { id: 'n6', icon: '💡', title: 'Electricity bill due', body: 'Due in 3 days — £84.50 via the provider portal.', time: 'Yesterday', urgent: false, user: 'J' },
  { id: 'n7', icon: '🧹', title: 'Weekly cleaning recap', body: '6 of 8 tasks completed this week. Great job!', time: '2 days ago', urgent: false, user: null },
]

export const categoryColor: Record<string, string> = {
  Cleaning: '#C4623A', Laundry: '#9B7DB5', Pets: '#C4963A',
  Garden: '#6B7C4E', Shopping: '#3A8FB5', Waste: '#7C5E3A',
  Bills: '#B53A3A', Kitchen: '#3A7C8F',
}

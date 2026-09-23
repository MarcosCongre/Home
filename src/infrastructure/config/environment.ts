const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL ?? '/api').replace(/\/$/, '')

export const environment = {
  apiBaseUrl,
  householdId: import.meta.env.VITE_HOUSEHOLD_ID ?? 'household-demo',
  demoMode: import.meta.env.VITE_DEMO_MODE === 'true',
} as const

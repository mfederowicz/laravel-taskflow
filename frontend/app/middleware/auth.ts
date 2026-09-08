export default defineNuxtRouteMiddleware(() => {
  const { getToken } = useAuth()

  // localStorage is unavailable during SSR, so the guard runs
  // client-side only; protected pages have no SSR-relevant content.
  if (!import.meta.client) {
    return
  }

  if (!getToken()) {
    return navigateTo('/login')
  }
})
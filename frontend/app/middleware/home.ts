export default defineNuxtRouteMiddleware((to) => {
  const { getToken } = useAuth()

  // localStorage is unavailable during SSR, so the redirect happens
  // client-side only. The index page has no content of its own.
  if (!import.meta.client) {
    return
  }

  return navigateTo(getToken() ? '/tasks' : '/login')
})
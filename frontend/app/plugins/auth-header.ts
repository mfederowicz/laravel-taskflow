export default defineNuxtPlugin(() => {
  const { authMethod } = useAuth()

  if (import.meta.client) {
    const stored = localStorage.getItem('auth-method')
    if (stored === 'sanctum' || stored === 'jwt' || stored === 'passport') {
      authMethod.value = stored
    }
  }
})
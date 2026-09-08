export default defineNuxtPlugin(() => {
  const { authMethod } = useAuth()

  // @ts-ignore
  if (import.meta.client) {
    const stored = localStorage.getItem('auth-method')
    if (stored === 'sanctum' || stored === 'jwt' || stored === 'passport') {
      authMethod.value = stored
    }
  }
})
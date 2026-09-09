export default defineNuxtRouteMiddleware(async () => {
    const { getToken, ensureProfile, getProfile } = useAuth()

    if (!import.meta.client) {
        return
    }

    if (!getToken()) {
        return navigateTo('/login')
    }

    await ensureProfile()

    const profile = getProfile()

    if (!profile) {
        return navigateTo('/login')
    }

    if (profile.role !== 'manager') {
        return navigateTo('/tasks')
    }
})
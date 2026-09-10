import { navigateTo } from '#app'
import useAuth from './useAuth'

function useApi() {
    const { getToken, authMethod, clearAuth, refreshAccessToken } = useAuth()

    async function apiFetch<T>(
        url: string,
        options: {
            method?: string
            body?: unknown
            headers?: Record<string, string>
        } = {},
    ): Promise<T> {
        const token = getToken()

        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Auth-Method': authMethod.value || 'sanctum',
            ...(options.headers ?? {}),
        }

        if (token) {
            headers.Authorization = `Bearer ${token}`
        }

        try {
            return await $fetch<T>(url, { ...options, headers })
        } catch (error: any) {
            // No HTTP response — network-level failure (offline, DNS, CORS,
            // timeout). Normalize so callers can surface the real cause.
            if (import.meta.client && !error?.response) {
                error.network = true
                error.data = { message: 'Unable to reach the server. Please check your connection.' }
                throw error
            }

            const status = error?.response?.status
            const is401 = import.meta.client && status === 401

            if (import.meta.client && status === 403 && error?.data?.message === 'Account is locked.') {
                clearAuth()
                navigateTo('/login?locked=1')
                throw error
            }

            if (is401) {
                const result = await refreshAccessToken()

                if (result.locked) {
                    clearAuth()
                    navigateTo('/login?locked=1')
                    throw error
                }

                if (result.retryable) {
                    // Transient refresh failure (server / network / throttle) —
                    // keep the session, surface the caller's error page.
                    throw error
                }

                if (result.ok) {
                    const newToken = getToken()
                    if (newToken) {
                        headers.Authorization = `Bearer ${newToken}`
                        try {
                            return await $fetch<T>(url, { ...options, headers })
                        } catch (retryError: any) {
                            const retryStatus = retryError?.response?.status
                            if (import.meta.client && retryStatus === 401) {
                                clearAuth()
                                navigateTo('/login')
                            }
                            throw retryError
                        }
                    }
                }
            }

            if (is401) {
                clearAuth()
                navigateTo('/login')
            }
            throw error
        }
    }

    return { apiFetch }
}

export default useApi
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

    /**
     * Download a file response (CSV/JSON export) with the auth headers,
     * retrying once on 401 after a successful token refresh.
     */
    async function apiDownload(
        url: string,
        filename: string,
        options: { headers?: Record<string, string> } = {},
    ): Promise<void> {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Auth-Method': authMethod.value || 'sanctum',
            ...(options.headers ?? {}),
        }

        const token = getToken()

        if (token) {
            headers.Authorization = `Bearer ${token}`
        }

        async function fetchBlob(overrideToken?: string): Promise<Blob> {
            const downloadHeaders = { ...headers }

            if (overrideToken) {
                downloadHeaders.Authorization = `Bearer ${overrideToken}`
            }

            return $fetch<Blob>(url, {
                ...options,
                headers: downloadHeaders,
                responseType: 'blob',
            })
        }

        let blob: Blob

        try {
            blob = await fetchBlob()
        } catch (error: any) {
            if (import.meta.client && error?.response?.status === 403 && error?.data?.message === 'Account is locked.') {
                clearAuth()
                navigateTo('/login?locked=1')
                throw error
            }

            if (import.meta.client && error?.response?.status === 401) {
                const result = await refreshAccessToken()

                if (result.locked) {
                    clearAuth()
                    navigateTo('/login?locked=1')
                    throw error
                }

                if (result.ok) {
                    const newToken = getToken()

                    if (!newToken) {
                        clearAuth()
                        navigateTo('/login')
                        throw error
                    }

                    try {
                        blob = await fetchBlob(newToken)
                    } catch (retryError: any) {
                        if (import.meta.client && retryError?.response?.status === 401) {
                            clearAuth()
                            navigateTo('/login')
                        }
                        throw retryError
                    }
                } else {
                    throw error
                }
            } else {
                throw error
            }
        }

        const objectUrl = URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = objectUrl
        link.download = filename
        document.body.appendChild(link)
        link.click()
        link.remove()
        setTimeout(() => URL.revokeObjectURL(objectUrl), 0)
    }

    return { apiFetch, apiDownload }
}

export default useApi
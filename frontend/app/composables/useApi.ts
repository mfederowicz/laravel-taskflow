function useApi() {
    const { getToken, authMethod } = useAuth()

    function apiFetch<T>(
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

        return $fetch<T>(url, { ...options, headers })
    }

    return { apiFetch }
}

export default useApi
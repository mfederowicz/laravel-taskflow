function useAuth() {
    const token = useState<string | null>('auth-token', () => null)
    const authMethod = useState<'sanctum' | 'jwt' | 'passport'>('auth-method', () => 'sanctum')
    const refreshToken = useState<string | null>('auth-refresh-token', () => null)
    const passportClientId = useState<string | null>('auth-passport-client-id', () => null)
    const passportClientSecret = useState<string | null>('auth-passport-client-secret', () => null)

    function setToken(value: string) {
        token.value = value

        if (import.meta.client) {
            localStorage.setItem('token', value)
        }
    }

    function setAuthMethod(method: 'sanctum' | 'jwt' | 'passport') {
        authMethod.value = method

        if (import.meta.client) {
            localStorage.setItem('auth-method', method)
        }
    }

    function setRefreshToken(value: string | null) {
        refreshToken.value = value

        if (import.meta.client && value) {
            localStorage.setItem('refresh-token', value)
        } else if (import.meta.client) {
            localStorage.removeItem('refresh-token')
            refreshToken.value = null
        }
    }

    function setPassportClientId(id: string | null) {
        passportClientId.value = id

        if (import.meta.client && id) {
            localStorage.setItem('passport-client-id', id)
        } else if (import.meta.client) {
            localStorage.removeItem('passport-client-id')
            passportClientId.value = null
        }
    }

    function setPassportClientSecret(secret: string | null) {
        passportClientSecret.value = secret

        if (import.meta.client && secret) {
            localStorage.setItem('passport-client-secret', secret)
        } else if (import.meta.client) {
            localStorage.removeItem('passport-client-secret')
            passportClientSecret.value = null
        }
    }

    function getToken(): string | null {
        if (!import.meta.client) {
            return null
        }

        if (token.value) {
            return token.value
        }

        token.value = localStorage.getItem('token')

        return token.value
    }

    async function logout() {
        const currentToken = getToken()

        if (currentToken) {
            try {
                await $fetch('/api/v1/logout', {
                    method: 'POST',
                    headers: {
                        Authorization: `Bearer ${currentToken}`,
                        Accept: 'application/json',
                        'X-Auth-Method': authMethod.value,
                    },
                })
            } catch {
                // Even if the API request fails, clear the local token.
            }
        }

        clearAuth()
    }

    function clearAuth() {
        token.value = null
        authMethod.value = 'sanctum'
        refreshToken.value = null
        passportClientId.value = null
        passportClientSecret.value = null

        if (import.meta.client) {
            localStorage.removeItem('token')
            localStorage.removeItem('auth-method')
            localStorage.removeItem('refresh-token')
            localStorage.removeItem('passport-client-id')
            localStorage.removeItem('passport-client-secret')
        }
    }

    async function refreshAccessToken(): Promise<boolean> {
        if (!passportClientId.value || !passportClientSecret.value || !refreshToken.value) {
            return false
        }

        try {
            const response = await $fetch<{
                access_token: string
                expires_in?: number
                refresh_token?: string
                token_type?: string
            }>('/api/v1/oauth/token', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Auth-Method': authMethod.value,
                },
                body: {
                    grant_type: 'refresh_token',
                    client_id: passportClientId.value,
                    client_secret: passportClientSecret.value,
                    refresh_token: refreshToken.value,
                    scope: '',
                },
            })

            setToken(response.access_token)
            setRefreshToken(response.refresh_token ?? null)
            setPassportClientSecret(response.client_secret ?? passportClientSecret.value)

            if (response.expires_in) {
                // We could compute expiry time, but for simplicity we just store the new token.
                // The TTL is 60 minutes by default in JWT config.
            }

            return true
        } catch {
            // Refresh failed; clear auth and let the caller redirect to login.
            clearAuth()
            return false
        }
    }

    return {
        token,
        authMethod,
        refreshToken,
        passportClientId,
        passportClientSecret,
        setToken,
        setAuthMethod,
        setRefreshToken,
        setPassportClientId,
        setPassportClientSecret,
        getToken,
        logout,
        clearAuth,
        refreshAccessToken,
    }
}

export default useAuth
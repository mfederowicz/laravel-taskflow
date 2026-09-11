import type { User } from '~/types/user'

type RefreshResult = { ok: boolean; locked: boolean; retryable: boolean }

let refreshPromise: Promise<RefreshResult> | null = null

function useAuth() {
    const token = useState<string | null>('auth-token', () => null)
    const authMethod = useState<'sanctum' | 'jwt' | 'passport'>('auth-method', () => 'sanctum')
    const refreshToken = useState<string | null>('auth-refresh-token', () => null)
    const passportClientId = useState<string | null>('auth-passport-client-id', () => null)
    const passportClientSecret = useState<string | null>('auth-passport-client-secret', () => null)
    const profile = useState<User | null>('auth-profile', () => null)

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
            // Accepted trade-off (B40): persisted so the Passport auto-refresh
            // keeps working across page reloads. XSS on this demo app can read it.
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

    function setProfile(value: User) {
        profile.value = value

        if (import.meta.client) {
            localStorage.setItem('profile', JSON.stringify(value))
        }
    }

    function getProfile(): User | null {
        if (!import.meta.client) {
            return null
        }

        if (profile.value) {
            return profile.value
        }

        const raw = localStorage.getItem('profile')

        if (raw) {
            profile.value = JSON.parse(raw) as User
        }

        return profile.value
    }

    async function ensureProfile(options: {
        refresh?: boolean
    } = {}): Promise<User | null> {
        const local = getProfile()

        if (local && !options.refresh) {
            return local
        }

        const currentToken = getToken()

        if (!currentToken) {
            return local
        }

        try {
            const response = await $fetch<{ data: User }>('/api/v1/user', {
                headers: {
                    Accept: 'application/json',
                    'X-Auth-Method': authMethod.value,
                    Authorization: `Bearer ${currentToken}`,
                },
            })

            setProfile(response.data)

            return response.data
        } catch (error: any) {
            const status = error?.response?.status

            // Transient server or network failure — keep the session; fall back
            // to whatever profile is cached instead of logging the user out.
            if (!status || status >= 500) {
                return getProfile()
            }

            // A genuine auth failure (401/403) — the token is unusable.
            clearAuth()

            return null
        }
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
        profile.value = null

        if (import.meta.client) {
            localStorage.removeItem('token')
            localStorage.removeItem('auth-method')
            localStorage.removeItem('refresh-token')
            localStorage.removeItem('passport-client-id')
            localStorage.removeItem('passport-client-secret')
            localStorage.removeItem('profile')
        }
    }

    function refreshAccessToken(): Promise<RefreshResult> {
        if (!refreshPromise) {
            refreshPromise = performRefresh().finally(() => {
                refreshPromise = null
            })
        }

        return refreshPromise
    }

    async function performRefresh(): Promise<RefreshResult> {
        const method = authMethod.value
        const currentToken = getToken()

        try {
            if (method === 'passport') {
                if (
                    !passportClientId.value ||
                    !passportClientSecret.value ||
                    !refreshToken.value
                ) {
                    return { ok: false, locked: false, retryable: false }
                }

                const response = await $fetch<{
                    access_token: string
                    refresh_token?: string
                    client_secret?: string
                }>('/api/v1/oauth/token', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Auth-Method': method,
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
                setPassportClientSecret(
                    response.client_secret ?? passportClientSecret.value,
                )

                return { ok: true, locked: false, retryable: false }
            }

            // JWT and Sanctum both rotate the (expired) access token itself
            // via a dedicated public refresh endpoint within a sliding window.
            if (!currentToken) {
                return { ok: false, locked: false, retryable: false }
            }

            const endpoint =
                method === 'jwt'
                    ? '/api/v1/jwt/refresh'
                    : '/api/v1/sanctum/refresh'

            const response = await $fetch<{
                data: { user?: unknown; token: string }
            }>(endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Auth-Method': method,
                    Authorization: `Bearer ${currentToken}`,
                },
            })

            setToken(response.data.token)

            return { ok: true, locked: false, retryable: false }
        } catch (error: any) {
            const status = error?.response?.status

            // Server / network / throttle hiccup on the refresh endpoint — keep
            // the session; the caller surfaces the error without logging out.
            if (!status || status >= 500 || status === 429) {
                return { ok: false, locked: false, retryable: true }
            }

            // The token (or Passport refresh token) is genuinely unusable —
            // clear auth and let the caller redirect to login.
            clearAuth()

            return {
                ok: false,
                locked:
                    status === 403 &&
                    error?.data?.message === 'Account is locked.',
                retryable: false,
            }
        }
    }

    return {
        token,
        authMethod,
        refreshToken,
        passportClientId,
        passportClientSecret,
        profile,
        setToken,
        setAuthMethod,
        setRefreshToken,
        setPassportClientId,
        setPassportClientSecret,
        getToken,
        setProfile,
        getProfile,
        ensureProfile,
        logout,
        clearAuth,
        refreshAccessToken,
    }
}

export default useAuth
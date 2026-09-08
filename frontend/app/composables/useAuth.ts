function useAuth() {
    const token = useState<string | null>('auth-token', () => null)
    const authMethod = useState<'sanctum' | 'jwt' | 'passport'>('auth-method', () => 'sanctum')

    function setToken(value: string) {
        token.value = value

        // @ts-ignore
        if (import.meta.client) {
            localStorage.setItem('token', value)
        }
    }

    function setAuthMethod(method: 'sanctum' | 'jwt' | 'passport') {
        authMethod.value = method

        // @ts-ignore
        if (import.meta.client) {
            localStorage.setItem('auth-method', method)
        }
    }

    function getToken(): string | null {
        // @ts-ignore
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

        token.value = null

        // @ts-ignore
        if (import.meta.client) {
            localStorage.removeItem('token')
            localStorage.removeItem('auth-method')
        }
    }

    return {
        token,
        authMethod,
        setToken,
        setAuthMethod,
        getToken,
        logout,
    }
}

export default useAuth
export function useAuth() {
    const token = useState<string | null>('auth-token', () => null)

    function setToken(value: string) {
        token.value = value

        if (import.meta.client) {
            localStorage.setItem('token', value)
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
                await $fetch('/api/logout', {
                    method: 'POST',
                    headers: {
                        Authorization: `Bearer ${currentToken}`,
                        Accept: 'application/json',
                    },
                })
            } catch {
                // Even if the API request fails, clear the local token.
            }
        }

        token.value = null

        if (import.meta.client) {
            localStorage.removeItem('token')
        }
    }

    return {
        token,
        setToken,
        getToken,
        logout,
    }
}
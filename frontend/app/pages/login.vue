<template>
  <main class="flex min-h-screen items-center justify-center">
    <section class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
      <h1 class="mt-0 text-center text-2xl font-bold text-gray-900">TaskFlow</h1>

      <p class="mt-2 text-center text-sm text-gray-500">
        A simple project management application for organizing projects,
        tasks, and comments.
      </p>

      <h2 class="mb-4 mt-8 text-lg font-semibold text-gray-900">Sign in</h2>

      <div class="mb-4">
        <label for="auth-method" class="mb-1 block text-sm font-semibold text-gray-700">Authentication Method</label>
        <select id="auth-method" v-model="authMethod" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
          <option value="sanctum">Sanctum (Personal Access Tokens)</option>
          <option value="jwt">JWT (Bearer Tokens)</option>
          <option value="passport">Passport (OAuth2)</option>
        </select>
      </div>

      <form @submit.prevent="login" class="space-y-4">
        <div>
          <label for="email" class="mb-1 block text-sm font-semibold text-gray-700">Email</label>
          <input
              id="email"
              v-model="email"
              type="email"
              required
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
        </div>

        <div>
          <label for="password" class="mb-1 block text-sm font-semibold text-gray-700">Password</label>
          <input
              id="password"
              v-model="password"
              type="password"
              required
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
        </div>

        <button type="submit" :disabled="pending" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
          {{ pending ? 'Logging in...' : 'Login' }}
        </button>
      </form>

      <p v-if="lockedNotice" class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Your account is locked. Contact a manager to regain access.
      </p>

      <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ error }}
      </p>

      <details class="mt-6">
        <summary class="cursor-pointer text-sm font-semibold text-gray-700">Demo account & developer information</summary>

        <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm">
          <strong class="text-gray-900">Demo account</strong>

          <p class="mt-2 text-gray-600">
            Email: <code class="rounded bg-gray-200 px-1">demo@example.com</code><br>
            Password: <code class="rounded bg-gray-200 px-1">password123</code>
          </p>
        </div>

        <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm">
          <strong class="text-gray-900">Create another user</strong>

          <p class="mt-2 text-gray-600">
            New users can be created from the Laravel application using Artisan:
          </p>

          <pre class="mt-2 overflow-x-auto rounded-md bg-gray-200 p-3 text-xs"><code>./bin/artisan tinker</code></pre>

          <p class="mt-2 text-gray-600">Then:</p>

          <pre class="mt-2 overflow-x-auto rounded-md bg-gray-200 p-3 text-xs"><code>App\Models\User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => Illuminate\Support\Facades\Hash::make('password123'),
]);</code></pre>
        </div>
      </details>

    </section>

  </main>
</template>


<script setup lang="ts">
import { navigateTo } from '#app'
import type { User } from '~/types/user'

const email = ref('')
const password = ref('')
const pending = ref(false)
const error = ref('')

const route = useRoute()
const router = useRouter()
const lockedNotice = ref(route.query.locked === '1' || route.query.locked === 'true')

onMounted(() => {
  if (lockedNotice.value) {
    router.replace({ query: {} })
  }
})

const { authMethod, setToken, setAuthMethod, setRefreshToken, setPassportClientId, setPassportClientSecret, setProfile } = useAuth()

async function login() {
  pending.value = true
  error.value = ''
  
  try {
    if (authMethod.value === 'sanctum') {
      // Sanctum login (current /api/v1/login)
      const response = await $fetch<{
        data: {
          user: User
          token: string
        }
      }>('/api/v1/login', {
        method: 'POST',
        body: {
          email: email.value,
          password: password.value,
        },
      })
      
      setToken(response.data.token)
      setAuthMethod('sanctum')
      setProfile(response.data.user)
    } else if (authMethod.value === 'jwt') {
      // JWT login
      const response = await $fetch<{
        data: {
          user: User
          token: string
        }
      }>('/api/v1/login/jwt', {
        method: 'POST',
        body: {
          email: email.value,
          password: password.value,
        },
        headers: {
          'Accept': 'application/json'
        }
      })

      setToken(response.data.token)
      setAuthMethod('jwt')
      setProfile(response.data.user)
    } else if (authMethod.value === 'passport') {
      // Passport login via OAuth token endpoint
      // First get client credentials
      const clientResponse = await $fetch<{
        client_id: string
        client_secret: string
      }>('/api/v1/oauth/client', {
        method: 'POST',
      })

      const tokenResponse = await $fetch<{
        access_token: string
        token_type: string
        expires_in: number
        refresh_token?: string
      }>('/api/v1/oauth/token', {
        method: 'POST',
        body: {
          grant_type: 'password',
          client_id: clientResponse.client_id,
          client_secret: clientResponse.client_secret,
          username: email.value,
          password: password.value,
          scope: '',
        },
      })

      setToken(tokenResponse.access_token)
      setAuthMethod('passport')
      setRefreshToken(tokenResponse.refresh_token ?? null)
      setPassportClientId(clientResponse.client_id)
      setPassportClientSecret(clientResponse.client_secret)
    }
    
    await navigateTo('/tasks')
  } catch (err: any) {
    error.value =
        err?.data?.error_description ??
        err?.data?.message ??
        'Invalid email or password.'
  } finally {
    pending.value = false
  }
}
</script>
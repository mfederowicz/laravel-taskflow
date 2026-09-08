<template>
  <main class="auth-page">
    <section class="auth-card">
      <h1>TaskFlow</h1>

      <p class="auth-intro">
        A simple project management application for organizing projects,
        tasks, and comments.
      </p>

      <h2>Sign in</h2>
      
      <!-- Auth mode dropdown -->
      <div class="auth-method-select">
        <label for="auth-method">Authentication Method</label>
        <select id="auth-method" v-model="authMethod">
          <option value="sanctum">Sanctum (Personal Access Tokens)</option>
          <option value="jwt">JWT (Bearer Tokens)</option>
          <option value="passport">Passport (OAuth2)</option>
        </select>
      </div>

      <form @submit.prevent="login">
        <div>
          <label for="email">Email</label>
          <input
              id="email"
              v-model="email"
              type="email"
              required
          >
        </div>

        <div>
          <label for="password">Password</label>
          <input
              id="password"
              v-model="password"
              type="password"
              required
          >
        </div>

        <button type="submit" :disabled="pending">
          {{ pending ? 'Logging in...' : 'Login' }}
        </button>
      </form>

      <p v-if="error">
        {{ error }}
      </p>
      <details class="login-help">
        <summary>Demo account & developer information</summary>

        <div class="demo-info">
          <strong>Demo account</strong>

          <p>
            Email: <code>demo@example.com</code><br>
            Password: <code>password123</code>
          </p>
        </div>

        <div class="developer-info">
          <strong>Create another user</strong>

          <p>
            New users can be created from the Laravel application using Artisan:
          </p>

          <pre><code>./bin/artisan tinker</code></pre>

          <p>Then:</p>

          <pre><code>App\Models\User::create([
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
import useAuth from '@/composables/useAuth'
import { navigateTo } from '#app'

const email = ref('')
const password = ref('')
const authMethod = ref<'sanctum' | 'jwt' | 'passport'>('sanctum')
const pending = ref(false)
const error = ref('')

const { setToken, setAuthMethod, getToken } = useAuth()

// Initialize auth method from localStorage on page load
onMounted(() => {
  const stored = localStorage.getItem('auth-method')
  if (stored === 'sanctum' || stored === 'jwt' || stored === 'passport') {
    authMethod.value = stored
  }
})

async function login() {
  pending.value = true
  error.value = ''
  
  try {
    if (authMethod.value === 'sanctum') {
      // Sanctum login (current /api/v1/login)
      const response = await $fetch<{
        data: {
          user: { id: number; name: string; email: string }
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
    } else if (authMethod.value === 'jwt') {
      // JWT login
      const response = await $fetch<{
        data: {
          user: { id: number; name: string; email: string }
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
    } else if (authMethod.value === 'passport') {
      // Passport login via OAuth token endpoint
      // First get client credentials
      const clientResponse = await $fetch<{
        client_id: string
        client_secret: string
      }>('/api/v1/oauth/client')
      
      const tokenResponse = await $fetch<{
        access_token: string
        token_type: string
        expires_in: number
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
    }
    
    await navigateTo('/tasks')
  } catch (err: any) {
    error.value = err?.data?.message ?? 'Invalid email or password.'
  } finally {
    pending.value = false
  }
}
</script>
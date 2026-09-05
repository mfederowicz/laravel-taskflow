<template>
  <main class="auth-page">
    <section class="auth-card">
      <h1>TaskFlow</h1>

      <p class="auth-intro">
        A simple project management application for organizing projects,
        tasks, and comments.
      </p>

      <h2>Sign in</h2>

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
useHead({
  title: 'Sign in',
})
const email = ref('')
const password = ref('')
const pending = ref(false)
const error = ref('')

const { setToken } = useAuth()

async function login() {
  pending.value = true
  error.value = ''

  try {
    const response = await $fetch<{
      data: {
        user: {
          id: number
          name: string
          email: string
        }
        token: string
      }
    }>('/api/login', {
      method: 'POST',
      body: {
        email: email.value,
        password: password.value,
      },
    })

    setToken(response.data.token)

    await navigateTo('/tasks')
  } catch (err: any) {
    error.value =
        err?.data?.message ?? 'Invalid email or password.'
  } finally {
    pending.value = false
  }
}
</script>
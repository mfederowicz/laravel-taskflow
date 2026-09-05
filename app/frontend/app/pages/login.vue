<template>
  <main class="auth-page">
    <section class="auth-card">
      <h1>Login</h1>

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
    </section>
  </main>
</template>


<script setup lang="ts">
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
<template>
  <AppNav />
  <main>
    <h1 class="mt-0 text-2xl font-bold text-gray-900">Profile</h1>
    <p class="mt-1 text-sm text-gray-500">Update your name and email address.</p>

    <p v-if="loading" class="mt-8 text-sm text-gray-500">Loading…</p>

    <form v-else @submit.prevent="saveProfile" class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
      <div class="space-y-4">
        <div>
          <label for="profile-name" class="mb-1 block text-sm font-semibold text-gray-700">Name</label>
          <input
              id="profile-name"
              v-model="form.name"
              type="text"
              maxlength="255"
              required
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
          <p v-if="validationErrors.name" class="mt-1 text-xs text-red-600">
            {{ validationErrors.name[0] }}
          </p>
        </div>

        <div>
          <label for="profile-email" class="mb-1 block text-sm font-semibold text-gray-700">Email</label>
          <input
              id="profile-email"
              v-model="form.email"
              type="email"
              maxlength="255"
              required
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
          <p v-if="validationErrors.email" class="mt-1 text-xs text-red-600">
            {{ validationErrors.email[0] }}
          </p>
        </div>

        <div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-4">
          <h2 class="mt-0 text-sm font-semibold text-gray-900">Security question</h2>
          <p class="mt-1 text-xs text-gray-500">
            Used to reset your password when you forget it. Pick a question only you can answer.
          </p>

          <div class="mt-3">
            <label for="profile-security-question" class="mb-1 block text-sm font-semibold text-gray-700">Question</label>
            <input
                id="profile-security-question"
                v-model="form.security_question"
                type="text"
                maxlength="255"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            >
            <p v-if="validationErrors.security_question" class="mt-1 text-xs text-red-600">
              {{ validationErrors.security_question[0] }}
            </p>
          </div>

          <div class="mt-3">
            <label for="profile-security-answer" class="mb-1 block text-sm font-semibold text-gray-700">Answer</label>
            <input
                id="profile-security-answer"
                v-model="form.security_answer"
                type="password"
                autocomplete="new-password"
                placeholder="Answer to the question above"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            >
            <p v-if="validationErrors.security_answer" class="mt-1 text-xs text-red-600">
              {{ validationErrors.security_answer[0] }}
            </p>
          </div>

          <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
            <input
                v-model="form.clear_security_question"
                type="checkbox"
                class="rounded border-gray-300"
            >
            Remove the security question
          </label>
        </div>
      </div>

      <p v-if="successMessage" class="mt-4 text-sm text-green-600">
        {{ successMessage }}
      </p>

      <p v-if="errorMessage" class="mt-4 text-sm text-red-600">
        {{ errorMessage }}
      </p>

      <button type="submit" :disabled="saving" class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
        {{ saving ? 'Saving...' : 'Save profile' }}
      </button>
    </form>
  </main>
</template>

<script setup lang="ts">
import type { UserResponse } from '~/types/user'

definePageMeta({
  middleware: 'auth',
})

useHead({
  title: 'Profile',
})

const { apiFetch } = useApi()
const { ensureProfile, setProfile } = useAuth()

const loading = ref(true)
const saving = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
const validationErrors = ref<Record<string, string[]>>({})

const form = reactive({
  name: '',
  email: '',
  security_question: '',
  security_answer: '',
  clear_security_question: false,
})

onMounted(async () => {
  const profile = await ensureProfile({ refresh: true })

  if (profile) {
    form.name = profile.name
    form.email = profile.email
  }

  loading.value = false
})

async function saveProfile() {
  saving.value = true
  successMessage.value = ''
  errorMessage.value = ''
  validationErrors.value = {}

  try {
    const response = await apiFetch<UserResponse>('/api/v1/user/profile', {
      method: 'PUT',
      body: {
        name: form.name,
        email: form.email,
        security_question: form.security_question || null,
        security_answer: form.security_answer || null,
        clear_security_question: form.clear_security_question,
      },
    })

    setProfile(response.data)
    successMessage.value = 'Profile updated.'
    form.security_question = ''
    form.security_answer = ''
    form.clear_security_question = false
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      validationErrors.value = err.data.errors
    } else {
      errorMessage.value = err?.data?.message ?? 'Failed to update profile.'
    }
  } finally {
    saving.value = false
  }
}
</script>
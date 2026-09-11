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
      body: { ...form },
    })

    setProfile(response.data)
    successMessage.value = 'Profile updated.'
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
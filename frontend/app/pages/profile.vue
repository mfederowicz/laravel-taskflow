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

    <!-- Personal API Tokens -->
    <section class="mt-8">
      <h2 class="text-lg font-bold text-gray-900">Personal API Tokens</h2>
      <p class="mt-1 text-sm text-gray-500">
        Tokens for scripts and API clients. Bound to your account — set an expiration date when possible.
      </p>

      <!-- Create form -->
      <form @submit.prevent="createToken" class="mt-4 space-y-3">
        <div class="flex gap-2">
          <input
            v-model="newTokenName"
            type="text"
            placeholder="e.g. My CI pipeline, Home server, Work laptop"
            maxlength="255"
            required
            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
          <select
            v-model="newTokenExpiry"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
            <option value="7">7 days</option>
            <option value="30">30 days</option>
            <option value="60">60 days</option>
            <option value="90">90 days</option>
            <option value="365">1 year</option>
            <option value="">No expiration</option>
          </select>
          <button
            type="submit"
            :disabled="creatingToken"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
          >
            {{ creatingToken ? 'Creating…' : 'Create token' }}
          </button>
        </div>
        <p v-if="!newTokenExpiry" class="text-xs text-amber-600">
          We recommend setting an expiration date. Tokens with no expiration remain valid until manually revoked.
        </p>
      </form>
      <p v-if="createTokenError" class="mt-2 text-xs text-red-600">{{ createTokenError }}</p>

      <!-- One-time plain token display -->
      <div v-if="newPlainToken" class="mt-4 rounded-lg border border-green-300 bg-green-50 p-4">
        <p class="mb-1 text-sm font-semibold text-green-800">Save this token to your password manager now.</p>
        <p class="mb-2 text-xs text-green-700">This is the only time it will be shown. Each "Create token" generates a new one — the name above is just a label to recognise it in the list.</p>

        <p class="mb-1 text-xs font-semibold text-gray-700">Your token</p>
        <div class="flex items-center gap-2">
          <code class="flex-1 break-all rounded bg-white px-2 py-1 text-xs text-gray-800 ring-1 ring-gray-200">{{ newPlainToken }}</code>
          <button
            type="button"
            @click="copyToken"
            class="shrink-0 rounded-lg border border-green-400 bg-white px-3 py-1 text-xs font-semibold text-green-700 hover:bg-green-50"
          >
            {{ copied ? 'Copied!' : 'Copy' }}
          </button>
        </div>

        <p class="mb-1 mt-4 text-xs font-semibold text-gray-700">How to use it</p>
        <pre class="overflow-x-auto rounded bg-white px-3 py-2 text-xs text-gray-800 ring-1 ring-gray-200">Authorization: Bearer {{ newPlainToken }}
X-Auth-Method: sanctum</pre>

        <button
          type="button"
          @click="newPlainToken = ''"
          class="mt-3 text-xs text-gray-500 underline hover:text-gray-700"
        >
          Dismiss
        </button>
      </div>

      <!-- Token list -->
      <div v-if="tokens.length" class="mt-4 divide-y divide-gray-100 rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div v-for="token in tokens" :key="token.id" class="flex items-center justify-between px-4 py-3">
          <div>
            <p class="text-sm font-medium text-gray-900">{{ token.name }}</p>
            <p class="mt-0.5 text-xs text-gray-500">
              Created {{ formatDate(token.created_at) }}
              <span v-if="token.last_used_at"> · Last used {{ formatDate(token.last_used_at) }}</span>
              <span v-else> · Never used</span>
              <span v-if="token.expires_at" :class="isExpired(token.expires_at) ? 'text-red-500' : 'text-gray-500'">
                · {{ isExpired(token.expires_at) ? 'Expired' : 'Expires' }} {{ formatDate(token.expires_at) }}
              </span>
              <span v-else class="text-amber-500"> · No expiration</span>
            </p>
          </div>
          <button
            type="button"
            @click="revokeToken(token.id)"
            class="ml-4 rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50"
          >
            Revoke
          </button>
        </div>
      </div>
      <p v-else-if="!loading" class="mt-4 text-sm text-gray-400">No personal API tokens yet.</p>
    </section>
  </main>
</template>

<script setup lang="ts">
import type { UserResponse } from '~/types/user'
import type { PersonalToken, PersonalTokensResponse, CreateTokenResponse } from '~/types/token'

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

  await loadTokens()
  loading.value = false
})

// ── Personal API Tokens ──────────────────────────────────────────────────────

function generateTokenName(): string {
  const bytes = new Uint8Array(4)
  crypto.getRandomValues(bytes)
  const hex = Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('')
  return `taskflow-${hex}`
}

const tokens = ref<PersonalToken[]>([])
const newTokenName = ref(generateTokenName())
const newTokenExpiry = ref('30')
const newPlainToken = ref('')
const creatingToken = ref(false)
const createTokenError = ref('')
const copied = ref(false)

async function loadTokens() {
  try {
    const res = await apiFetch<PersonalTokensResponse>('/api/v1/user/tokens')
    tokens.value = res.data
  } catch {
    // non-fatal — list stays empty
  }
}

async function createToken() {
  creatingToken.value = true
  createTokenError.value = ''
  newPlainToken.value = ''

  try {
    const expiresAt = newTokenExpiry.value
      ? new Date(Date.now() + parseInt(newTokenExpiry.value) * 86400 * 1000).toISOString().slice(0, 10)
      : null

    const res = await apiFetch<CreateTokenResponse>('/api/v1/user/tokens', {
      method: 'POST',
      body: { name: newTokenName.value, expires_at: expiresAt },
    })
    tokens.value.unshift(res.data.token)
    newPlainToken.value = res.data.plain_token
    newTokenName.value = generateTokenName()
  } catch (err: any) {
    createTokenError.value = err?.data?.errors?.name?.[0] ?? err?.data?.message ?? 'Failed to create token.'
  } finally {
    creatingToken.value = false
  }
}

async function revokeToken(id: number) {
  try {
    await apiFetch(`/api/v1/user/tokens/${id}`, { method: 'DELETE' })
    tokens.value = tokens.value.filter(t => t.id !== id)
  } catch {
    // ignore — UI keeps token in list
  }
}

async function copyToken() {
  await navigator.clipboard.writeText(newPlainToken.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

function isExpired(iso: string): boolean {
  return new Date(iso) < new Date()
}

// ─────────────────────────────────────────────────────────────────────────────

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
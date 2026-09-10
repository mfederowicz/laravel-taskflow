<template>
  <AppNav />
  <main>
    <h1 class="mt-0 text-2xl font-bold text-gray-900">Users</h1>

    <p v-if="pending" class="text-sm text-gray-500">Loading users...</p>

    <p v-else-if="error" class="text-sm text-red-600">
      {{ error }}
    </p>

    <div v-else class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-500">
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Role</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" class="border-b border-gray-100 last:border-0 hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ user.name }}<span v-if="isSelf(user.id)" class="text-gray-400"> (you)</span></td>
            <td class="px-4 py-3 text-gray-600">{{ user.email }}</td>
            <td class="px-4 py-3">
              <select
                  :value="user.role"
                  :disabled="isSelf(user.id) || acting === user.id"
                  @change="changeRole(user, ($event.target as HTMLSelectElement).value)"
                  class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs disabled:opacity-60"
              >
                <option value="user">User</option>
                <option value="manager">Manager</option>
              </select>
            </td>
            <td class="px-4 py-3">
              <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="user.status === 'locked' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
              >
                {{ user.status === 'locked' ? 'Locked' : 'Active' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <template v-if="resetPasswordId === user.id">
                <form @submit.prevent="resetPassword(user)" class="flex items-center gap-2">
                  <input
                      v-model="resetPasswordValue"
                      type="password"
                      placeholder="New password (min 8 chars)"
                      minlength="8"
                      required
                      class="w-48 rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                  >
                  <button type="submit" :disabled="acting === user.id" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    Save
                  </button>
                  <button
                      type="button"
                      :disabled="acting === user.id"
                      @click="resetPasswordId = null"
                      class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                  >
                    Cancel
                  </button>
                </form>
              </template>
              <template v-else>
                <div class="flex items-center gap-2">
                  <button
                      type="button"
                      :disabled="acting === user.id"
                      @click="startResetPassword(user)"
                      class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                  >
                    Reset password
                  </button>
                  <button
                      type="button"
                      :disabled="isSelf(user.id) || acting === user.id"
                      @click="toggleLock(user)"
                      class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60"
                      :class="user.status === 'locked' ? 'bg-blue-600 hover:bg-blue-700' : 'bg-red-600 hover:bg-red-700'"
                  >
                    {{ user.status === 'locked' ? 'Unlock' : 'Lock' }}
                  </button>
                </div>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="actionError" class="mt-4 text-sm text-red-600">
      {{ actionError }}
    </p>

    <div v-if="lastPage > 1" class="mt-6 flex items-center gap-4">
      <button
          type="button"
          :disabled="currentPage === 1"
          @click="previousPage"
          class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
      >
        Previous
      </button>

      <span class="text-sm text-gray-600">
        Page {{ currentPage }} of {{ lastPage }}
      </span>

      <button
          type="button"
          :disabled="currentPage === lastPage"
          @click="nextPage"
          class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
      >
        Next
      </button>
    </div>
  </main>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'admin',
})

useHead({
  title: 'Users',
})

import type { User, UserResponse, UsersResponse } from '~/types/user'

const { apiFetch } = useApi()

const users = ref<User[]>([])
const pending = ref(true)
const error = ref('')

const currentPage = ref(1)
const lastPage = ref(1)

const acting = ref<number | null>(null)
const actionError = ref('')

const resetPasswordId = ref<number | null>(null)
const resetPasswordValue = ref('')

const { profile } = useAuth()

onMounted(loadUsers)

function isSelf(userId: number): boolean {
  return profile.value?.id === userId
}

async function loadUsers() {
  pending.value = true
  error.value = ''
  actionError.value = ''

  try {
    const params = new URLSearchParams()
    params.set('page', String(currentPage.value))

    const response = await apiFetch<UsersResponse>(
        `/api/v1/users?${params.toString()}`,
    )

    users.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch {
    error.value = 'Failed to load users.'
  } finally {
    pending.value = false
  }
}

async function previousPage() {
  if (currentPage.value <= 1) {
    return
  }

  currentPage.value--
  await loadUsers()
}

async function nextPage() {
  if (currentPage.value >= lastPage.value) {
    return
  }

  currentPage.value++
  await loadUsers()
}

async function toggleLock(user: User) {
  acting.value = user.id
  actionError.value = ''

  try {
    const action = user.status === 'locked' ? 'unlock' : 'lock'
    const response = await apiFetch<UserResponse>(
        `/api/v1/users/${user.id}/${action}`,
        { method: 'POST' },
    )

    replaceUser(response.data)
  } catch (err: any) {
    actionError.value = err?.data?.message ?? 'Failed to update user.'
  } finally {
    acting.value = null
  }
}

async function changeRole(user: User, role: string) {
  acting.value = user.id
  actionError.value = ''

  try {
    const response = await apiFetch<UserResponse>(
        `/api/v1/users/${user.id}/role`,
        {
          method: 'PATCH',
          body: { role },
        },
    )

    replaceUser(response.data)
  } catch (err: any) {
    actionError.value = err?.data?.message ?? 'Failed to update role.'
  } finally {
    acting.value = null
  }
}

function startResetPassword(user: User) {
  resetPasswordId.value = user.id
  resetPasswordValue.value = ''
}

async function resetPassword(user: User) {
  acting.value = user.id
  actionError.value = ''

  try {
    const response = await apiFetch<UserResponse>(
        `/api/v1/users/${user.id}/password`,
        {
          method: 'PUT',
          body: {
            password: resetPasswordValue.value,
            password_confirmation: resetPasswordValue.value,
          },
        },
    )

    replaceUser(response.data)
  } catch (err: any) {
    actionError.value = err?.data?.message ?? 'Failed to reset password.'
  } finally {
    acting.value = null
    resetPasswordId.value = null
    resetPasswordValue.value = ''
  }
}

function replaceUser(updated: User) {
  const index = users.value.findIndex((user: User) => user.id === updated.id)

  if (index !== -1) {
    users.value[index] = updated
  }
}
</script>
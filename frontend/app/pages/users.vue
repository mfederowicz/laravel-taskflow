<template>
  <AppNav />
  <main>
    <h1>Users</h1>

    <p v-if="pending">Loading users...</p>

    <p v-else-if="error">
      {{ error }}
    </p>

    <table v-else>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in users" :key="user.id">
          <td>{{ user.name }}<span v-if="isSelf(user.id)"> (you)</span></td>
          <td>{{ user.email }}</td>
          <td>
            <select
                :value="user.role"
                :disabled="isSelf(user.id) || acting === user.id"
                @change="changeRole(user, ($event.target as HTMLSelectElement).value)"
            >
              <option value="user">User</option>
              <option value="manager">Manager</option>
            </select>
          </td>
          <td>{{ user.status === 'locked' ? 'Locked' : 'Active' }}</td>
          <td>
            <template v-if="resetPasswordId === user.id">
              <form @submit.prevent="resetPassword(user)">
                <input
                    v-model="resetPasswordValue"
                    type="password"
                    placeholder="New password (min 8 chars)"
                    minlength="8"
                    required
                >
                <button type="submit" :disabled="acting === user.id">
                  Save
                </button>
                <button
                    type="button"
                    :disabled="acting === user.id"
                    @click="resetPasswordId = null"
                >
                  Cancel
                </button>
              </form>
            </template>
            <template v-else>
              <button
                  type="button"
                  :disabled="acting === user.id"
                  @click="startResetPassword(user)"
              >
                Reset password
              </button>
              <button
                  type="button"
                  :disabled="isSelf(user.id) || acting === user.id"
                  @click="toggleLock(user)"
              >
                {{ user.status === 'locked' ? 'Unlock' : 'Lock' }}
              </button>
            </template>
          </td>
        </tr>
      </tbody>
    </table>

    <p v-if="actionError">
      {{ actionError }}
    </p>

    <div v-if="lastPage > 1">
      <button
          type="button"
          :disabled="currentPage === 1"
          @click="previousPage"
      >
        Previous
      </button>

      <span>
        Page {{ currentPage }} of {{ lastPage }}
      </span>

      <button
          type="button"
          :disabled="currentPage === lastPage"
          @click="nextPage"
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
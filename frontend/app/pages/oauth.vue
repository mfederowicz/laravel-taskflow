<template>
  <AppNav />
  <main>
    <h1 class="mt-0 text-2xl font-bold text-gray-900">OAuth clients</h1>

    <form @submit.prevent="createClient" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
      <label for="client-name" class="mb-1 block text-sm font-semibold text-gray-700">Name</label>
      <input
          id="client-name"
          v-model="name"
          type="text"
          placeholder="e.g. Mobile app"
          maxlength="255"
          required
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
      >
      <button type="submit" :disabled="creating" class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
        {{ creating ? 'Creating...' : 'Create client' }}
      </button>

      <p v-if="createError" class="mt-2 text-sm text-red-600">
        {{ createError }}
      </p>
    </form>

    <div v-if="createdClient" class="mt-4 rounded-lg border border-amber-400 bg-amber-50 px-4 py-3 text-center text-sm text-amber-900">
      <p>
        Client created. Copy the secret now — it will not be shown again.
      </p>
      <p>
        <strong>Client ID:</strong> {{ createdClient.client_id }}
      </p>
      <p>
        <strong>Client secret:</strong> {{ createdClient.client_secret }}
      </p>
    </div>

    <p v-if="pending" class="text-sm text-gray-500">Loading clients...</p>

    <p v-else-if="error" class="text-sm text-red-600">
      {{ error }}
    </p>

    <div v-else class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-500">
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Client ID</th>
            <th class="px-4 py-3">Created</th>
            <th class="px-4 py-3" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="client in clients" :key="client.id" class="border-b border-gray-100 last:border-0 hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ client.name }}</td>
            <td class="px-4 py-3 text-gray-600">{{ client.id }}</td>
            <td class="px-4 py-3 text-gray-600">{{ new Date(client.created_at).toLocaleDateString() }}</td>
            <td class="px-4 py-3">
              <button
                  type="button"
                  :disabled="acting === client.id"
                  @click="deleteClient(client.id)"
                  class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="actionError" class="mt-4 text-sm text-red-600">
      {{ actionError }}
    </p>
  </main>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'admin',
})

useHead({
  title: 'OAuth clients',
})

import type { OAuthClient, OAuthClientCreated, OAuthClientsResponse } from '~/types/oauth'

const { apiFetch } = useApi()

const clients = ref<OAuthClient[]>([])
const pending = ref(true)
const error = ref('')

const name = ref('')
const creating = ref(false)
const createError = ref('')

const createdClient = ref<OAuthClientCreated['data'] | null>(null)

const acting = ref<string | null>(null)
const actionError = ref('')

onMounted(loadClients)

async function loadClients() {
  pending.value = true
  error.value = ''

  try {
    const response = await apiFetch<OAuthClientsResponse>('/api/v1/oauth/clients')
    clients.value = response.data
  } catch (err: any) {
    error.value = err?.data?.message ?? 'Failed to load OAuth clients.'
  } finally {
    pending.value = false
  }
}

async function createClient() {
  creating.value = true
  createError.value = ''
  createdClient.value = null

  try {
    const response = await apiFetch<OAuthClientCreated>('/api/v1/oauth/clients', {
      method: 'POST',
      body: { name: name.value },
    })

    createdClient.value = response.data
    name.value = ''
    await loadClients()
  } catch (err: any) {
    createError.value = err?.data?.message ?? 'Failed to create client.'
  } finally {
    creating.value = false
  }
}

async function deleteClient(clientId: string) {
  acting.value = clientId
  actionError.value = ''

  try {
    await apiFetch(`/api/v1/oauth/clients/${clientId}`, {
      method: 'DELETE',
    })

    clients.value = clients.value.filter((client: OAuthClient) => client.id !== clientId)

    if (createdClient.value?.client_id === clientId) {
      createdClient.value = null
    }
  } catch (err: any) {
    actionError.value = err?.data?.message ?? 'Failed to delete client.'
  } finally {
    acting.value = null
  }
}
</script>
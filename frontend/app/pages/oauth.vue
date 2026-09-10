<template>
  <AppNav />
  <main>
    <h1>OAuth clients</h1>

    <form @submit.prevent="createClient">
      <label for="client-name">Name</label>
      <input
          id="client-name"
          v-model="name"
          type="text"
          placeholder="e.g. Mobile app"
          maxlength="255"
          required
      >
      <button type="submit" :disabled="creating">
        {{ creating ? 'Creating...' : 'Create client' }}
      </button>

      <p v-if="createError">
        {{ createError }}
      </p>
    </form>

    <div v-if="createdClient" class="secret-box">
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

    <p v-if="pending">Loading clients...</p>

    <p v-else-if="error">
      {{ error }}
    </p>

    <table v-else>
      <thead>
        <tr>
          <th>Name</th>
          <th>Client ID</th>
          <th>Created</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="client in clients" :key="client.id">
          <td>{{ client.name }}</td>
          <td>{{ client.id }}</td>
          <td>{{ new Date(client.created_at).toLocaleDateString() }}</td>
          <td>
            <button
                type="button"
                :disabled="acting === client.id"
                @click="deleteClient(client.id)"
            >
              Delete
            </button>
          </td>
        </tr>
      </tbody>
    </table>
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
  } catch {
    error.value = 'Failed to load OAuth clients.'
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
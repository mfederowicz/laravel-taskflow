<template>
  <AppNav />
  <main>
    <h1 class="mt-0 text-2xl font-bold text-gray-900">Organizations</h1>

    <form @submit.prevent="createOrganization" class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
      <div class="mb-4">
        <label for="name" class="mb-1 block text-sm font-semibold text-gray-700">Name</label>
        <input
            id="name"
            v-model="form.name"
            type="text"
            required
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        >

        <p v-if="validationErrors.name" class="mt-1 text-sm text-red-600">
          {{ validationErrors.name[0] }}
        </p>
      </div>

      <div class="mb-4">
        <label for="description" class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
        <textarea
            id="description"
            v-model="form.description"
            class="min-h-24 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        />

        <p v-if="validationErrors.description" class="mt-1 text-sm text-red-600">
          {{ validationErrors.description[0] }}
        </p>
      </div>

      <button type="submit" :disabled="creating" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
        {{ creating ? 'Creating...' : 'Create organization' }}
      </button>

      <p v-if="createError" class="mt-3 text-sm text-red-600">
        {{ createError }}
      </p>
    </form>

    <p v-if="pending" class="text-sm text-gray-500">
      Loading organizations...
    </p>

    <p v-else-if="error" class="text-sm text-red-600">
      {{ error }}
    </p>

    <ul v-else class="space-y-4">
      <li v-for="org in organizations" :key="org.id" class="flex items-start gap-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <template v-if="editingOrganizationId === org.id">
          <div class="min-w-0 flex-1 space-y-4">
            <div>
              <label for="edit-name" class="mb-1 block text-sm font-semibold text-gray-700">Name</label>
              <input
                  v-model="editForm.name"
                  type="text"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              >
            </div>

            <p v-if="updateValidationErrors.name" class="text-sm text-red-600">
              {{ updateValidationErrors.name[0] }}
            </p>

            <div>
              <label for="edit-description" class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
              <textarea
                  v-model="editForm.description"
                  class="min-h-24 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              />
            </div>

            <p v-if="updateValidationErrors.description" class="text-sm text-red-600">
              {{ updateValidationErrors.description[0] }}
            </p>
          </div>
          <div class="flex shrink-0 flex-col gap-2">
            <button
                type="button"
                :disabled="updating"
                @click="updateOrganization"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
            >
              {{ updating ? 'Saving...' : 'Save' }}
            </button>

            <button
                type="button"
                :disabled="updating"
                @click="cancelEditing"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
            >
              Cancel
            </button>

            <p v-if="updateError" class="text-sm text-red-600">
              {{ updateError }}
            </p>
          </div>
        </template>

        <template v-else>
          <div class="min-w-0 flex-1">
            <strong class="text-gray-900">{{ org.name }}</strong>

            <span
                class="ml-2 rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="roleBadgeClass(org.role)"
            >
              {{ roleLabel(org.role) }}
            </span>

            <span class="ml-2 text-sm text-gray-500">
              — Owned by {{ org.owner.name }}
            </span>

            <NuxtLink
                :to="`/organizations/${org.id}`"
                class="ml-2 text-sm text-blue-600 hover:text-blue-800"
            >
              Manage
            </NuxtLink>

            <p v-if="org.description" class="mt-1 text-sm text-gray-600">
              {{ org.description }}
            </p>
          </div>

          <div class="flex shrink-0 flex-col gap-2">
            <button
                v-if="canManageOrganization(org)"
                type="button"
                @click="startEditing(org)"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              Edit
            </button>

            <button
                v-if="canDeleteOrganization(org)"
                type="button"
                @click="deleteOrganization(org.id)"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
            >
              Delete
            </button>
          </div>
        </template>
      </li>
    </ul>

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
  middleware: 'auth',
})

useHead({
  title: 'Organizations',
})
import type { Organization } from '~/types/organization'

const {
  listOrganizations,
  createOrganization: createOrganizationApi,
  updateOrganization: updateOrganizationApi,
  deleteOrganization: deleteOrganizationApi,
} = useOrganizations()

const organizations = ref<Organization[]>([])
const pending = ref(true)
const error = ref('')

const currentPage = ref(1)
const lastPage = ref(1)

const creating = ref(false)
const createError = ref('')
const validationErrors = ref<Record<string, string[]>>({})

const form = reactive({
  name: '',
  description: '',
})

const editingOrganizationId = ref<number | null>(null)

const editForm = reactive({
  name: '',
  description: '',
})

const updating = ref(false)
const updateError = ref('')
const updateValidationErrors = ref<Record<string, string[]>>({})

onMounted(async () => {
  await loadOrganizations()
})

async function loadOrganizations() {
  pending.value = true
  error.value = ''

  try {
    const result = await listOrganizations(currentPage.value)

    organizations.value = result.organizations
    currentPage.value = result.currentPage
    lastPage.value = result.lastPage
  } catch (err: any) {
    error.value = err?.data?.message ?? 'Failed to load organizations.'
  } finally {
    pending.value = false
  }
}

async function previousPage() {
  if (currentPage.value <= 1) {
    return
  }

  currentPage.value--
  await loadOrganizations()
}

async function nextPage() {
  if (currentPage.value >= lastPage.value) {
    return
  }

  currentPage.value++
  await loadOrganizations()
}

async function createOrganization() {
  creating.value = true
  createError.value = ''
  validationErrors.value = {}

  try {
    const organization = await createOrganizationApi({
      name: form.name,
      description: form.description || null,
    })

    organizations.value.unshift(organization)

    form.name = ''
    form.description = ''
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      validationErrors.value = err.data.errors
    } else {
      createError.value =
          err?.data?.message ?? 'Failed to create organization.'
    }
  } finally {
    creating.value = false
  }
}

function startEditing(org: Organization) {
  editingOrganizationId.value = org.id

  editForm.name = org.name
  editForm.description = org.description ?? ''

  updateError.value = ''
  updateValidationErrors.value = {}
}

function cancelEditing() {
  editingOrganizationId.value = null
  updateError.value = ''
  updateValidationErrors.value = {}
}

function roleLabel(role: string): string {
  return role.charAt(0).toUpperCase() + role.slice(1)
}

function roleBadgeClass(role: string): string {
  if (role === 'owner' || role === 'admin') {
    return 'bg-emerald-100 text-emerald-700'
  }

  if (role === 'editor') {
    return 'bg-blue-100 text-blue-700'
  }

  return 'bg-gray-100 text-gray-600'
}

function canManageOrganization(org: Organization): boolean {
  return org.role === 'owner' || org.role === 'admin'
}

function canDeleteOrganization(org: Organization): boolean {
  return org.role === 'owner'
}

async function updateOrganization() {
  if (editingOrganizationId.value === null) {
    return
  }

  updating.value = true
  updateError.value = ''
  updateValidationErrors.value = {}

  try {
    const organization = await updateOrganizationApi(
        editingOrganizationId.value,
        {
          name: editForm.name,
          description: editForm.description || null,
        }
    )

    const index = organizations.value.findIndex(
        (item: Organization) => item.id === editingOrganizationId.value
    )

    if (index !== -1) {
      organizations.value[index] = organization
    }

    editingOrganizationId.value = null
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      updateValidationErrors.value = err.data.errors
    } else {
      updateError.value =
          err?.data?.message ?? 'Failed to update organization.'
    }
  } finally {
    updating.value = false
  }
}

async function deleteOrganization(orgId: number) {
  try {
    await deleteOrganizationApi(orgId)

    organizations.value = organizations.value.filter(
        (org: { id: number }) => org.id !== orgId
    )

    if (organizations.value.length === 0 && currentPage.value > 1) {
      currentPage.value--
      await loadOrganizations()
    }
  } catch {
    error.value = 'Failed to delete organization.'
  }
}
</script>
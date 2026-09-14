<template>
  <AppNav />
  <main>
    <p v-if="pending" class="mt-4 text-sm text-gray-500">
      Loading organization...
    </p>

    <p v-else-if="error" class="mt-4 text-sm text-red-600">
      {{ error }}
    </p>

    <template v-else-if="organization">
      <div class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div v-if="editing" class="space-y-4">
          <div>
            <label for="edit-name" class="mb-1 block text-sm font-semibold text-gray-700">Name</label>
            <input
                id="edit-name"
                v-model="editForm.name"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            >

            <p v-if="updateValidationErrors.name" class="mt-1 text-sm text-red-600">
              {{ updateValidationErrors.name[0] }}
            </p>
          </div>

          <div>
            <label for="edit-description" class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
            <textarea
                id="edit-description"
                v-model="editForm.description"
                class="min-h-24 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            />

            <p v-if="updateValidationErrors.description" class="mt-1 text-sm text-red-600">
              {{ updateValidationErrors.description[0] }}
            </p>
          </div>

          <div class="flex items-center gap-3">
            <button
                type="button"
                :disabled="updating"
                @click="submitUpdate"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
            >
              {{ updating ? 'Saving...' : 'Save' }}
            </button>

            <button
                type="button"
                :disabled="updating"
                @click="cancelEdit"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
            >
              Cancel
            </button>

            <p v-if="updateError" class="text-sm text-red-600">
              {{ updateError }}
            </p>
          </div>
        </div>

        <template v-else>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="mt-0 text-2xl font-bold text-gray-900">{{ organization.name }}</h1>
            <span
                class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                :class="roleBadgeClass(organization.role)"
            >
              {{ roleLabel(organization.role) }}
            </span>

            <button
                v-if="canManage"
                type="button"
                @click="startEditing"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              Edit
            </button>
          </div>

          <p v-if="organization.description" class="mt-2 text-sm text-gray-600">
            {{ organization.description }}
          </p>

          <p v-else class="mt-2 text-sm text-gray-400">
            No description.
          </p>

          <p class="mt-3 flex flex-wrap gap-x-4 text-sm text-gray-500">
            <span>Owner: {{ organization.owner.name }}</span>
            <span>Created: {{ formatDate(organization.created_at) }}</span>
          </p>

          <p class="mt-3">
            <NuxtLink to="/organizations" class="text-sm font-semibold text-blue-600 hover:text-blue-700">
              ← Back to organizations
            </NuxtLink>
          </p>
        </template>
      </div>

      <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div class="flex items-center justify-between">
          <h2 class="mt-0 text-lg font-bold text-gray-900">Members</h2>

          <span v-if="canManage" class="text-sm text-gray-500">
            Owner and admins manage the roster.
          </span>
        </div>

        <p v-if="membersLoading" class="mt-3 text-sm text-gray-500">
          Loading members...
        </p>

        <p v-else-if="membersError" class="mt-3 text-sm text-red-600">
          {{ membersError }}
        </p>

        <ul v-else class="mt-4 space-y-3">
          <li
              v-for="member in members"
              :key="member.id"
              class="flex flex-wrap items-center gap-4 rounded-lg bg-gray-50 px-4 py-3"
          >
            <div class="min-w-0 flex-1">
              <strong class="block text-sm text-gray-900">{{ member.user.name }}</strong>
              <span class="block truncate text-sm text-gray-500">{{ member.user.email }}</span>
            </div>

            <span
                v-if="!canManage"
                class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700"
            >
              {{ roleLabel(member.role) }}
            </span>

            <template v-else>
              <select
                  :value="member.role"
                  :disabled="updatingRole === member.id"
                  class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                  @change="changeRole(member, $event)"
              >
                <option value="admin">Admin</option>
                <option value="editor">Editor</option>
                <option value="viewer">Viewer</option>
              </select>

              <button
                  type="button"
                  :disabled="removing === member.id"
                  class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                  @click="handleRemoveMember(member)"
              >
                {{ removing === member.id ? 'Removing...' : 'Remove' }}
              </button>
            </template>

            <p v-if="memberErrors[member.id]" class="w-full text-sm text-red-600">
              {{ memberErrors[member.id] }}
            </p>
          </li>
        </ul>

        <form
            v-if="canManage"
            class="mt-6 border-t border-gray-100 pt-5"
            @submit.prevent="submitAddMember"
        >
          <h3 class="text-sm font-semibold text-gray-900">Add member</h3>

          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="sm:col-span-1">
              <label for="member-search" class="mb-1 block text-sm font-semibold text-gray-700">User</label>
              <div class="relative">
                <input
                    id="member-search"
                    v-model="searchQuery"
                    type="search"
                    placeholder="Name or email"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                    @input="onSearchInput"
                    @focus="openResults = true"
                >

                <ul
                    v-if="openResults && searchResults.length"
                    class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-lg bg-white shadow-lg ring-1 ring-gray-200"
                >
                  <li v-for="user in searchResults" :key="user.id">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-gray-50"
                        @click="selectUser(user)"
                    >
                      <span class="min-w-0">
                        <strong class="block text-gray-900">{{ user.name }}</strong>
                        <span class="block truncate text-gray-500">{{ user.email }}</span>
                      </span>
                      <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Add</span>
                    </button>
                  </li>
                </ul>
              </div>

              <p v-if="addErrors.user_id" class="mt-1 text-sm text-red-600">
                {{ addErrors.user_id[0] }}
              </p>
            </div>

            <div>
              <label for="member-role" class="mb-1 block text-sm font-semibold text-gray-700">Role</label>
              <select
                  id="member-role"
                  v-model="addRole"
                  class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              >
                <option value="admin">Admin</option>
                <option value="editor">Editor</option>
                <option value="viewer">Viewer</option>
              </select>
              <p v-if="addErrors.role" class="mt-1 text-sm text-red-600">
                {{ addErrors.role[0] }}
              </p>
            </div>

            <div class="flex items-end">
              <button
                  type="submit"
                  :disabled="adding"
                  class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
              >
                {{ adding ? 'Adding...' : 'Add member' }}
              </button>
            </div>
          </div>

          <p v-if="addError" class="mt-3 text-sm text-red-600">
            {{ addError }}
          </p>
        </form>
      </div>

      <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div class="flex items-center justify-between">
          <h2 class="mt-0 text-lg font-bold text-gray-900">Projects</h2>
        </div>

        <p v-if="projectsLoading" class="mt-3 text-sm text-gray-500">
          Loading projects...
        </p>

        <p v-else-if="projectsError" class="mt-3 text-sm text-red-600">
          {{ projectsError }}
        </p>

        <p v-else-if="!projects.length" class="mt-3 text-sm text-gray-500">
          No projects in this organization yet.
        </p>

        <ul v-else class="mt-3 space-y-2">
          <li
              v-for="project in projects"
              :key="project.id"
              class="flex items-center justify-between gap-4 rounded-lg bg-gray-50 px-4 py-2.5"
          >
            <div class="min-w-0">
              <strong class="block truncate text-sm text-gray-900">{{ project.name }}</strong>
              <span
                  class="mt-0.5 inline-block rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="roleBadgeClass(project.role)"
              >
                {{ roleLabel(project.role) }}
              </span>
            </div>
            <NuxtLink
                :to="`/projects/${project.id}`"
                class="shrink-0 text-sm text-blue-600 hover:text-blue-700"
            >
              Open
            </NuxtLink>
          </li>
        </ul>

        <div v-if="projectsLastPage > 1" class="mt-4 flex items-center gap-4">
          <button
              type="button"
              :disabled="projectsPage === 1"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
              @click="projectsPreviousPage"
          >
            Previous
          </button>

          <span class="text-sm text-gray-600">Page {{ projectsPage }} of {{ projectsLastPage }}</span>

          <button
              type="button"
              :disabled="projectsPage === projectsLastPage"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
              @click="projectsNextPage"
          >
            Next
          </button>
        </div>

        <form
            v-if="canManage"
            class="mt-6 border-t border-gray-100 pt-5"
            @submit.prevent="submitCreateProject"
        >
          <h3 class="text-sm font-semibold text-gray-900">Create project</h3>

          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="project-name" class="mb-1 block text-sm font-semibold text-gray-700">Name</label>
              <input
                  id="project-name"
                  v-model="projectForm.name"
                  type="text"
                  required
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              >
              <p v-if="projectErrors.name" class="mt-1 text-sm text-red-600">
                {{ projectErrors.name[0] }}
              </p>
            </div>

            <div class="flex items-end">
              <button
                  type="submit"
                  :disabled="creatingProject"
                  class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
              >
                {{ creatingProject ? 'Creating...' : 'Create project' }}
              </button>
            </div>
          </div>

          <p v-if="projectError" class="mt-3 text-sm text-red-600">
            {{ projectError }}
          </p>
        </form>
      </div>
    </template>
  </main>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

useHead({
  title: 'Organization',
})
import type { Organization, OrganizationMember } from '~/types/organization'
import type { Project, ProjectsResponse } from '~/types/project'
import type { User } from '~/types/user'

const route = useRoute()
const { apiFetch } = useApi()
const {
    getOrganization,
    updateOrganization,
    listMembers,
    addMember,
    updateMemberRole,
    removeMember: removeMemberApi,
    searchUsers,
} = useOrganizations()

const organizationId = computed(() => Number(route.params.id))

const organization = ref<Organization | null>(null)
const pending = ref(true)
const error = ref('')

const editing = ref(false)
const editForm = reactive({
  name: '',
  description: '',
})
const updating = ref(false)
const updateError = ref('')
const updateValidationErrors = ref<Record<string, string[]>>({})

const members = ref<OrganizationMember[]>([])
const membersLoading = ref(true)
const membersError = ref('')

const adding = ref(false)
const addError = ref('')
const addErrors = ref<Record<string, string[]>>({})
const addRole = ref('editor')

const searchQuery = ref('')
const searchResults = ref<User[]>([])
const openResults = ref(false)
const selectedUserId = ref<number | null>(null)
let searchTimer: ReturnType<typeof setTimeout> | null = null

const updatingRole = ref<number | null>(null)
const removing = ref<number | null>(null)
const memberErrors = ref<Record<number, string>>({})

const projects = ref<Project[]>([])
const projectsLoading = ref(true)
const projectsError = ref('')
const projectsPage = ref(1)
const projectsLastPage = ref(1)

const creatingProject = ref(false)
const projectError = ref('')
const projectErrors = ref<Record<string, string[]>>({})
const projectForm = reactive({
  name: '',
})

const canManage = computed(
    () => organization.value?.role === 'owner' || organization.value?.role === 'admin'
)

onMounted(async () => {
  if (Number.isNaN(organizationId.value)) {
    error.value = 'Invalid organization.'
    pending.value = false
    return
  }

  try {
    organization.value = await getOrganization(organizationId.value)
  } catch (err: any) {
    error.value =
        err?.status === 403
            ? 'You do not have access to this organization.'
            : err?.data?.message ?? 'Failed to load organization.'
    pending.value = false
    return
  }

  pending.value = false

  await loadOrganizationDetails()
})

async function loadOrganizationDetails() {
  await Promise.all([loadMembers(), loadProjects()])
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

function formatDate(value: string): string {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleDateString()
}

function startEditing() {
  if (!organization.value) {
    return
  }

  editing.value = true

  editForm.name = organization.value.name
  editForm.description = organization.value.description ?? ''

  updateError.value = ''
  updateValidationErrors.value = {}
}

function cancelEdit() {
  editing.value = false
  updateError.value = ''
  updateValidationErrors.value = {}
}

async function submitUpdate() {
  updating.value = true
  updateError.value = ''
  updateValidationErrors.value = {}

  try {
    organization.value = await updateOrganization(organizationId.value, {
      name: editForm.name,
      description: editForm.description || null,
    })
    editing.value = false
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      updateValidationErrors.value = err.data.errors
    } else {
      updateError.value = err?.data?.message ?? 'Failed to update organization.'
    }
  } finally {
    updating.value = false
  }
}

async function loadMembers() {
  membersLoading.value = true
  membersError.value = ''

  try {
    members.value = await listMembers(organizationId.value)
  } catch (err: any) {
    membersError.value = err?.data?.message ?? 'Failed to load members.'
  } finally {
    membersLoading.value = false
  }
}

function onSearchInput() {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(async () => {
    try {
      searchResults.value = await searchUsers(searchQuery.value)
    } catch {
      searchResults.value = []
    }
  }, 250)
}

function selectUser(user: User) {
  selectedUserId.value = user.id
  searchQuery.value = user.name
  openResults.value = false
  addErrors.value = {}
}

async function submitAddMember() {
  if (selectedUserId.value === null) {
    addErrors.value = {
      user_id: ['Select a user to add.'],
    }
    return
  }

  adding.value = true
  addError.value = ''
  addErrors.value = {}

  try {
    await addMember(organizationId.value, selectedUserId.value, addRole.value)

    selectedUserId.value = null
    searchQuery.value = ''
    searchResults.value = []
    addRole.value = 'editor'

    await loadMembers()
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      addErrors.value = err.data.errors
    } else {
      addError.value = err?.data?.message ?? 'Failed to add member.'
    }
  } finally {
    adding.value = false
  }
}

async function changeRole(member: OrganizationMember, event: Event) {
  const role = (event.target as HTMLSelectElement).value
  updatingRole.value = member.id
  memberErrors.value[member.id] = ''

  try {
    await updateMemberRole(organizationId.value, member.id, role)

    member.role = role as OrganizationMember['role']
  } catch (err: any) {
    memberErrors.value[member.id] =
        err?.data?.message ?? 'Failed to update role.'
  } finally {
    updatingRole.value = null
  }
}

async function handleRemoveMember(member: OrganizationMember) {
  removing.value = member.id
  memberErrors.value[member.id] = ''

  try {
    await removeMemberApi(organizationId.value, member.id)
    members.value = members.value.filter(
        (item: OrganizationMember) => item.id !== member.id
    )
  } catch (err: any) {
    memberErrors.value[member.id] =
        err?.data?.message ?? 'Failed to remove member.'
  } finally {
    removing.value = null
  }
}

async function loadProjects() {
  projectsLoading.value = true
  projectsError.value = ''

  try {
    const params = new URLSearchParams()
    params.set('organization_id', String(organizationId.value))
    params.set('page', String(projectsPage.value))

    const response = await apiFetch<ProjectsResponse>(
        `/api/v1/projects?${params.toString()}`,
    )

    projects.value = response.data
    projectsPage.value = response.meta?.current_page ?? 1
    projectsLastPage.value = response.meta?.last_page ?? 1
  } catch (err: any) {
    projectsError.value = err?.data?.message ?? 'Failed to load projects.'
  } finally {
    projectsLoading.value = false
  }
}

async function projectsPreviousPage() {
  if (projectsPage.value <= 1) {
    return
  }

  projectsPage.value--
  await loadProjects()
}

async function projectsNextPage() {
  if (projectsPage.value >= projectsLastPage.value) {
    return
  }

  projectsPage.value++
  await loadProjects()
}

async function submitCreateProject() {
  creatingProject.value = true
  projectError.value = ''
  projectErrors.value = {}

  try {
    const response = await apiFetch<{ data: Project }>(
        '/api/v1/projects',
        {
          method: 'POST',
          body: {
            name: projectForm.name,
            description: null,
            organization_id: organizationId.value,
          },
        }
    )

    projects.value.unshift(response.data)
    projectForm.name = ''
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      projectErrors.value = err.data.errors
    } else {
      projectError.value = err?.data?.message ?? 'Failed to create project.'
    }
  } finally {
    creatingProject.value = false
  }
}
</script>
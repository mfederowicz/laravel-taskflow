<template>
  <AppNav />
  <main>
    <NuxtLink to="/projects" class="text-sm text-blue-600 hover:text-blue-700">
      ← Back to projects
    </NuxtLink>

    <p v-if="pending" class="mt-4 text-sm text-gray-500">
      Loading project...
    </p>

    <p v-else-if="error" class="mt-4 text-sm text-red-600">
      {{ error }}
    </p>

    <template v-else-if="project">
      <div class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="mt-0 text-2xl font-bold text-gray-900">{{ project.name }}</h1>
          <span
              class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
              :class="roleBadgeClass(project.role)"
          >
            {{ roleLabel(project.role) }}
          </span>
        </div>

        <p v-if="project.description" class="mt-2 text-sm text-gray-600">
          {{ project.description }}
        </p>

        <p v-else class="mt-2 text-sm text-gray-400">
          No description.
        </p>

        <p class="mt-3 flex flex-wrap gap-x-4 text-sm text-gray-500">
          <span>Owner: {{ project.user.name }}</span>
          <span>Created: {{ formatDate(project.created_at) }}</span>
        </p>
      </div>

      <div
          class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
      >
        <div class="flex items-center justify-between">
          <h2 class="mt-0 text-lg font-bold text-gray-900">Members</h2>

          <span v-if="canManageMembers" class="text-sm text-gray-500">
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
                v-if="!canManageMembers"
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
            v-if="canManageMembers"
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

      <div
          class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
      >
        <div class="flex items-center justify-between">
          <h2 class="mt-0 text-lg font-bold text-gray-900">Tasks</h2>

          <NuxtLink to="/tasks" class="text-sm text-blue-600 hover:text-blue-700">
            Open tasks page
          </NuxtLink>
        </div>

        <p v-if="tasksLoading" class="mt-3 text-sm text-gray-500">
          Loading tasks...
        </p>

        <p v-else-if="tasksError" class="mt-3 text-sm text-red-600">
          {{ tasksError }}
        </p>

        <p v-else-if="!tasks.length" class="mt-3 text-sm text-gray-500">
          No tasks in this project yet.
        </p>

        <ul v-else class="mt-3 space-y-2">
          <li
              v-for="task in tasks"
              :key="task.id"
              class="flex items-center justify-between gap-4 rounded-lg bg-gray-50 px-4 py-2.5"
          >
            <div class="min-w-0">
              <strong class="block truncate text-sm text-gray-900">{{ task.title }}</strong>
              <span class="text-xs text-gray-500">
                {{ task.status }} · {{ task.priority }}
              </span>
            </div>
            <NuxtLink
                to="/tasks"
                class="shrink-0 text-sm text-blue-600 hover:text-blue-700"
            >
              View
            </NuxtLink>
          </li>
        </ul>
      </div>
    </template>
  </main>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
})

useHead({
  title: 'Project',
})

import type { Project, ProjectMember } from '~/types/project'
import type { Task } from '~/types/task'
import type { User } from '~/types/user'

const route = useRoute()
const { apiFetch } = useApi()
const {
    listMembers,
    addMember,
    updateMemberRole,
    removeMember: removeMemberApi,
    searchUsers,
} = useProjectMembers()

const projectId = computed(() => Number(route.params.id))

const project = ref<Project | null>(null)
const pending = ref(true)
const error = ref('')

const members = ref<ProjectMember[]>([])
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

const tasks = ref<Task[]>([])
const tasksLoading = ref(true)
const tasksError = ref('')

const canManageMembers = computed(() =>
    project.value?.role === 'owner' || project.value?.role === 'admin'
)

onMounted(async () => {
  if (Number.isNaN(projectId.value)) {
    error.value = 'Invalid project.'
    pending.value = false
    return
  }

  try {
    const response = await apiFetch<{ data: Project }>(
        `/api/v1/projects/${projectId.value}`,
    )

    project.value = response.data
  } catch (err: any) {
    error.value =
        err?.status === 403
            ? 'You do not have access to this project.'
            : err?.data?.message ?? 'Failed to load project.'
    pending.value = false
    return
  }

  pending.value = false

  await Promise.all([loadMembers(), loadTasks()])
})

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

async function loadMembers() {
  membersLoading.value = true
  membersError.value = ''

  try {
    members.value = await listMembers(projectId.value)
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
    await addMember(projectId.value, selectedUserId.value, addRole.value)

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

async function changeRole(member: ProjectMember, event: Event) {
  const role = (event.target as HTMLSelectElement).value
  updatingRole.value = member.id
  memberErrors.value[member.id] = ''

  try {
    await updateMemberRole(projectId.value, member.id, role)

    member.role = role as ProjectMember['role']
  } catch (err: any) {
    memberErrors.value[member.id] =
        err?.data?.message ?? 'Failed to update role.'
  } finally {
    updatingRole.value = null
  }
}

async function handleRemoveMember(member: ProjectMember) {
  removing.value = member.id
  memberErrors.value[member.id] = ''

  try {
    await removeMemberApi(projectId.value, member.id)
    members.value = members.value.filter(
        (item: ProjectMember) => item.id !== member.id
    )
  } catch (err: any) {
    memberErrors.value[member.id] =
        err?.data?.message ?? 'Failed to remove member.'
  } finally {
    removing.value = null
  }
}

async function loadTasks() {
  tasksLoading.value = true
  tasksError.value = ''

  try {
    const params = new URLSearchParams()
    params.set('project_id', String(projectId.value))

    const response = await apiFetch<{ data: Task[] }>(
        `/api/v1/tasks?${params.toString()}`,
    )

    tasks.value = response.data
  } catch (err: any) {
    tasksError.value = err?.data?.message ?? 'Failed to load tasks.'
  } finally {
    tasksLoading.value = false
  }
}
</script>
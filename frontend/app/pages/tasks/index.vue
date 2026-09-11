<template>
  <AppNav />
  <main>
    <h1 class="mt-0 text-2xl font-bold text-gray-900">{{ isManager ? 'All Tasks' : 'My Tasks' }}</h1>

    <form @submit.prevent="createTask" class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
      <div class="mb-4">
        <label for="project" class="mb-1 block text-sm font-semibold text-gray-700">Project</label>

        <select
            id="project"
            v-model="form.project_id"
            :disabled="projectsLoading"
            required
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        >
          <option value="" disabled>
            {{ projectsLoading ? 'Loading projects...' : 'Select a project' }}
          </option>

<option
                  v-for="project in creatableProjects"
                  :key="project.id"
                  :value="String(project.id)"
              >
                {{ project.name }}
              </option>
            </select>

              <p v-if="validationErrors.project_id" class="mt-1 text-sm text-red-600">
          {{ validationErrors.project_id[0] }}
        </p>

        <p v-if="projectsError" class="mt-1 text-sm text-red-600">
          {{ projectsError }}
        </p>
      </div>
      <div class="mb-4">
        <label for="title" class="mb-1 block text-sm font-semibold text-gray-700">Title</label>
        <input
            id="title"
            v-model="form.title"
            type="text"
            required
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        >
        <p v-if="validationErrors.title" class="mt-1 text-sm text-red-600">
          {{ validationErrors.title[0] }}
        </p>
      </div>

      <div class="mb-4">
        <label for="description" class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
        <textarea
            id="description"
            v-model="form.description"
            class="min-h-24 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        />
      </div>

      <div class="mb-4">
        <label for="status" class="mb-1 block text-sm font-semibold text-gray-700">Status</label>
        <select id="status" v-model="form.status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
          <option value="pending">Pending</option>
          <option value="in_progress">In progress</option>
          <option value="completed">Completed</option>
        </select>
        <p v-if="validationErrors.status" class="mt-1 text-sm text-red-600">
          {{ validationErrors.status[0] }}
        </p>
      </div>

      <div class="mb-4">
        <label for="priority" class="mb-1 block text-sm font-semibold text-gray-700">Priority</label>
        <select id="priority" v-model="form.priority" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
        </select>
        <p v-if="validationErrors.priority" class="mt-1 text-sm text-red-600">
          {{ validationErrors.priority[0] }}
        </p>
      </div>

      <div class="mb-4">
        <label for="due_date" class="mb-1 block text-sm font-semibold text-gray-700">Due date</label>
        <input
            id="due_date"
            v-model="form.due_date"
            type="date"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        >
        <p v-if="validationErrors.due_date" class="mt-1 text-sm text-red-600">
          {{ validationErrors.due_date[0] }}
        </p>
      </div>

      <button type="submit" :disabled="creating" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
        {{ creating ? 'Creating...' : 'Create task' }}
      </button>

      <p v-if="createError" class="mt-3 text-sm text-red-600">
        {{ createError }}
      </p>
    </form>

    <div class="mb-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" :class="isManager ? 'lg:grid-cols-6' : 'lg:grid-cols-5'">
        <div>
          <label for="filter-search" class="mb-1 block text-sm font-semibold text-gray-700">Search</label>
          <input
              id="filter-search"
              v-model="filters.search"
              type="search"
              placeholder="Search title or description"
              @keyup.enter="applyFilters"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
        </div>

        <div>
          <label for="filter-status" class="mb-1 block text-sm font-semibold text-gray-700">Status</label>
          <select id="filter-status" v-model="filters.status" @change="applyFilters" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In progress</option>
            <option value="completed">Completed</option>
          </select>
        </div>

        <div>
          <label for="filter-priority" class="mb-1 block text-sm font-semibold text-gray-700">Priority</label>
          <select id="filter-priority" v-model="filters.priority" @change="applyFilters" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            <option value="">All</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>

        <div v-if="isManager">
          <label for="filter-owner" class="mb-1 block text-sm font-semibold text-gray-700">Owner</label>
          <select
              id="filter-owner"
              v-model="filters.user_id"
              @change="applyFilters"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
            <option value="">Everyone</option>
            <option v-for="user in allUsers" :key="user.id" :value="String(user.id)">
              {{ user.name }}
            </option>
          </select>
          <p v-if="usersError" class="mt-1 text-sm text-red-600">
            {{ usersError }}
          </p>
        </div>

        <div>
          <label for="filter-due-from" class="mb-1 block text-sm font-semibold text-gray-700">Due from</label>
          <input
              id="filter-due-from"
              v-model="filters.due_from"
              type="date"
              @change="applyFilters"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
        </div>

        <div>
          <label for="filter-due-to" class="mb-1 block text-sm font-semibold text-gray-700">Due to</label>
          <input
              id="filter-due-to"
              v-model="filters.due_to"
              type="date"
              @change="applyFilters"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
        </div>
      </div>
    </div>

    <div class="mb-4 flex items-center gap-3">
      <span class="text-sm font-semibold text-gray-700">Export:</span>
      <button
          type="button"
          :disabled="exporting"
          @click="exportTasks('csv')"
          class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
      >
        Export CSV
      </button>
      <button
          type="button"
          :disabled="exporting"
          @click="exportTasks('json')"
          class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
      >
        Export JSON
      </button>

      <p v-if="exportError" class="text-sm text-red-600">
        {{ exportError }}
      </p>
    </div>

    <p v-if="pending" class="text-sm text-gray-500">Loading tasks...</p>

    <p v-else-if="error" class="text-sm text-red-600">
      {{ error }}
    </p>

    <p v-else-if="tasks.length === 0" class="text-sm text-gray-500">
      No tasks match your filters.
    </p>

    <ul v-else class="space-y-4">
      <li v-for="task in tasks" :key="task.id" class="flex items-start gap-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" :class="{ 'opacity-60': task.status === 'completed' }">
        <div class="min-w-0 flex-1">
          <div>
            <strong class="text-gray-900">{{ task.title }}</strong>

            <span class="ml-2 inline-flex items-center gap-1.5 align-middle">
              <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="statusChipClass(task.status)"
              >
                {{ statusLabel(task.status) }}
              </span>
              <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="priorityChipClass(task.priority)"
              >
                {{ priorityLabel(task.priority) }}
              </span>
            </span>

            <span v-if="task.project" class="text-gray-500">
              — {{ task.project.name }}
            </span>
            <span
                v-if="task.shared"
                class="ml-2 rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-700"
            >
              Shared
            </span>
            <span v-if="isManager" class="text-gray-500">
              — <span class="text-gray-400">owner: {{ task.user.name }}</span>
            </span>
            <span
                v-if="dueBadge(task)"
                class="ml-2 rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="badgeClass(task)"
            >
              {{ dueBadge(task).label }}
            </span>
          </div>
          <p v-if="task.description" class="mt-1 text-sm text-gray-600">
            {{ task.description }}
          </p>
        </div>

        <div class="flex shrink-0 flex-col gap-2">
          <NuxtLink
              :to="`/tasks/${task.id}`"
              class="rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700"
          >
            Open
          </NuxtLink>

          <button
              type="button"
              v-if="canDeleteTask(task)"
              @click="deleteTask(task.id)"
              class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
          >
            Delete
          </button>
        </div>
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
  title: 'Tasks',
})
import type {
  Task,
  TaskResponse,
  TasksResponse,
} from '~/types/task'
import type {
  Project,
  ProjectsResponse,
} from '~/types/project'
import type {
  User,
  UsersResponse,
} from '~/types/user'
import {
  badgeClass,
  dueBadge,
  priorityChipClass,
  priorityLabel,
  statusChipClass,
  statusLabel,
} from '~/utils/taskDisplay'

const { apiFetch, apiDownload } = useApi()
const { profile, ensureProfile } = useAuth()

const isManager = computed(() => profile.value?.role === 'manager')

const projects = ref<Project[]>([])
const projectsLoading = ref(true)
const projectsError = ref('')

const creatableProjects = computed(() =>
    projects.value.filter((project) =>
        project.role === 'owner' ||
        project.role === 'admin' ||
        project.role === 'editor'
    )
)

function taskProjectRole(task: Task): string | null {
  if (!task.project) {
    return null
  }

  return projects.value.find(
      (project: Project) => project.id === task.project!.id
  )?.role ?? null
}

function canDeleteTask(task: Task): boolean {
  if (isManager.value) {
    return true
  }

  if (task.user.id === profile.value?.id) {
    return true
  }

  return taskProjectRole(task) === 'admin'
}

const tasks = ref<Task[]>([])
const pending = ref(true)
const error = ref('')

const creating = ref(false)
const createError = ref('')
const validationErrors = ref<Record<string, string[]>>({})

const currentPage = ref(1)
const lastPage = ref(1)

const form = reactive({
  project_id: '',
  title: '',
  description: '',
  status: 'pending',
  priority: 'medium',
  due_date: '',
})

const filters = reactive({
  status: '',
  priority: '',
  search: '',
  due_from: '',
  due_to: '',
  user_id: '',
})

const allUsers = ref<User[]>([])
const usersError = ref('')

const exporting = ref(false)
const exportError = ref('')

onMounted(async () => {
  await ensureProfile()

  await Promise.all([
    loadTasksWithFilters(),
    loadProjects(),
  ])

  if (isManager.value) {
    await loadAllUsers()
  }
})

async function exportTasks(format: 'csv' | 'json') {
  exporting.value = true
  exportError.value = ''

  try {
    const params = new URLSearchParams()

    if (filters.status) {
      params.set('status', filters.status)
    }

    if (filters.priority) {
      params.set('priority', filters.priority)
    }

    if (filters.user_id) {
      params.set('user_id', filters.user_id)
    }

    if (filters.search.trim()) {
      params.set('search', filters.search.trim())
    }

    if (filters.due_from) {
      params.set('due_from', filters.due_from)
    }

    if (filters.due_to) {
      params.set('due_to', filters.due_to)
    }

    params.set('format', format)

    await apiDownload(
        `/api/v1/tasks/export?${params.toString()}`,
        `tasks.${format}`,
    )
  } catch (err: any) {
    exportError.value = err?.data?.message ?? 'Failed to export tasks.'
  } finally {
    exporting.value = false
  }
}

async function loadAllUsers() {
  usersError.value = ''
  allUsers.value = []

  try {
    let page = 1
    let lastPage = 1

    do {
      const response = await apiFetch<UsersResponse>(
          `/api/v1/users?page=${page}`,
      )

      allUsers.value.push(...response.data)
      lastPage = response.meta.last_page
      page++
    } while (page <= lastPage)
  } catch (err: any) {
    usersError.value = err?.data?.message ?? 'Failed to load users.'
  }
}

async function loadProjects() {
  try {
    const response = await apiFetch<ProjectsResponse>(
        '/api/v1/projects',
    )

    projects.value = response.data
  } catch {
    projectsError.value = 'Failed to load projects.'
  } finally {
    projectsLoading.value = false
  }
}

async function loadTasksWithFilters() {
  pending.value = true
  error.value = ''

  try {
    const params = new URLSearchParams()

    if (filters.status) {
      params.set('status', filters.status)
    }

    if (filters.priority) {
      params.set('priority', filters.priority)
    }

    if (filters.user_id) {
      params.set('user_id', filters.user_id)
    }

    if (filters.search.trim()) {
      params.set('search', filters.search.trim())
    }

    if (filters.due_from) {
      params.set('due_from', filters.due_from)
    }

    if (filters.due_to) {
      params.set('due_to', filters.due_to)
    }

    params.set('page', String(currentPage.value))

    const query = params.toString()

    const response = await apiFetch<TasksResponse>(
        query ? `/api/v1/tasks?${query}` : '/api/v1/tasks',
    )

    tasks.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (err: any) {
    error.value = err?.data?.message ?? 'Failed to load tasks.'
  } finally {
    pending.value = false
  }
}

async function applyFilters() {
  currentPage.value = 1
  await loadTasksWithFilters()
}

async function createTask() {
  creating.value = true
  createError.value = ''
  validationErrors.value = {}

  try {
    const response = await apiFetch<TaskResponse>('/api/v1/tasks', {
      method: 'POST',
      body: {
        project_id: Number(form.project_id),
        title: form.title,
        description: form.description || null,
        status: form.status,
        priority: form.priority,
        due_date: form.due_date || null,
      },
    })

    tasks.value.unshift(response.data)

    form.project_id = ''
    form.title = ''
    form.description = ''
    form.status = 'pending'
    form.priority = 'medium'
    form.due_date = ''
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      validationErrors.value = err.data.errors
    } else {
      createError.value =
          err?.data?.message ?? 'Failed to create task.'
    }
  } finally {
    creating.value = false
  }
}

async function deleteTask(taskId: number) {
  try {
    await apiFetch(`/api/v1/tasks/${taskId}`, {
      method: 'DELETE',
    })

    tasks.value = tasks.value.filter(
        (task: { id: number }) => task.id !== taskId
    )

    if (tasks.value.length === 0 && currentPage.value > 1) {
      currentPage.value--
      await loadTasksWithFilters()
    }
  } catch {
    error.value = 'Failed to delete task.'
  }
}

async function previousPage() {
  if (currentPage.value <= 1) {
    return
  }

  currentPage.value--
  await loadTasksWithFilters()
}

async function nextPage() {
  if (currentPage.value >= lastPage.value) {
    return
  }

  currentPage.value++
  await loadTasksWithFilters()
}
</script>
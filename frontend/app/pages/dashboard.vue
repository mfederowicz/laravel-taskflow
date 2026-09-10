<template>
  <AppNav />
  <main>
    <div>
      <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
      <p class="mt-1 text-sm text-gray-500">Your tasks and projects at a glance.</p>
    </div>

    <p v-if="loading" class="mt-8 text-sm text-gray-500">Loading…</p>

    <p v-else-if="error" class="mt-8 text-sm text-red-600">{{ error }}</p>

    <template v-else>
      <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="stat in stats" :key="stat.label" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
          <p class="text-sm font-medium text-gray-500">{{ stat.label }}</p>
          <p class="mt-1 text-3xl font-semibold text-gray-900">{{ stat.value }}</p>
        </div>
      </div>

      <div class="mt-8 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">Coming up</h2>
          <NuxtLink to="/tasks" class="text-sm text-blue-600 hover:underline">
            View all tasks
          </NuxtLink>
        </div>

        <ul v-if="comingUp.length" class="mt-4 divide-y divide-gray-100">
          <li v-for="task in comingUp" :key="task.id" class="flex items-center justify-between gap-4 py-3">
            <div class="min-w-0">
              <p class="truncate font-medium text-gray-900">{{ task.title }}</p>
              <p class="mt-0.5 truncate text-sm text-gray-500">
                {{ task.project ? task.project.name : 'No project' }}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
              <span
                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="badgeClass(task)"
              >
                {{ dueBadge(task)!.label }}
              </span>
              <span class="text-sm text-gray-500">{{ task.due_date }}</span>
            </div>
          </li>
        </ul>

        <p v-else class="mt-4 text-sm text-gray-500">Nothing overdue or due in the next few days. Nice work!</p>
      </div>
    </template>
  </main>
</template>

<script setup lang="ts">
import type { Task, TasksResponse } from '~/types/task'
import type { ProjectsResponse } from '~/types/project'

definePageMeta({
  middleware: 'auth',
})

const { apiFetch } = useApi()

const loading = ref(true)
const error = ref('')
const tasks = ref<Task[]>([])
const projectCount = ref(0)

const DUE_SOON_DAYS = 3

function todayISO(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${now.getFullYear()}-${month}-${day}`
}

function addDaysToISO(iso: string, days: number): string {
  const date = new Date(`${iso}T00:00:00`)
  date.setDate(date.getDate() + days)
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

function dueBadge(task: Task): { label: string; type: string } | null {
  if (!task.due_date || task.status === 'completed') {
    return null
  }

  const today = todayISO()

  if (task.due_date < today) {
    return { label: 'Overdue', type: 'overdue' }
  }

  if (task.due_date === today) {
    return { label: 'Due today', type: 'due-today' }
  }

  if (task.due_date <= addDaysToISO(today, DUE_SOON_DAYS)) {
    return { label: 'Due soon', type: 'due-soon' }
  }

  return null
}

function badgeClass(task: Task): string {
  const type = dueBadge(task)?.type

  if (type === 'overdue') {
    return 'bg-red-100 text-red-700'
  }

  if (type === 'due-today') {
    return 'bg-amber-100 text-amber-700'
  }

  return 'bg-blue-100 text-blue-700'
}

async function fetchAllTasks(): Promise<Task[]> {
  const all: Task[] = []
  let page = 1
  let lastPage = 1

  do {
    const response = await apiFetch<TasksResponse>(`/api/v1/tasks?page=${page}`)
    all.push(...response.data)
    lastPage = response.meta.last_page
    page++
  } while (page <= lastPage && all.length < 500)

  return all
}

async function fetchProjectCount(): Promise<number> {
  let count = 0
  let page = 1
  let lastPage = 1

  do {
    const response = await apiFetch<ProjectsResponse>(`/api/v1/projects?page=${page}`)
    count += response.data.length
    lastPage = response.meta?.last_page ?? 1
    page++
  } while (page <= lastPage && count < 500)

  return count
}

onMounted(async () => {
  try {
    const [allTasks, projects] = await Promise.all([fetchAllTasks(), fetchProjectCount()])
    tasks.value = allTasks
    projectCount.value = projects
  } catch (err: any) {
    error.value = err?.data?.message ?? 'Failed to load the dashboard.'
  } finally {
    loading.value = false
  }
})

const stats = computed(() => {
  const open = tasks.value.filter((task) => task.status !== 'completed').length
  const overdue = tasks.value.filter((task) => dueBadge(task)?.type === 'overdue').length
  const dueToday = tasks.value.filter((task) => dueBadge(task)?.type === 'due-today').length

  return [
    { label: 'Open tasks', value: open },
    { label: 'Overdue', value: overdue },
    { label: 'Due today', value: dueToday },
    { label: 'Projects', value: projectCount.value },
  ]
})

const comingUp = computed(() =>
  tasks.value
    .filter((task) => dueBadge(task) !== null)
    .sort((a, b) => (a.due_date! < b.due_date! ? -1 : 1))
    .slice(0, 8),
)
</script>
<template>
  <AppNav />
  <main>
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Calendar</h1>
        <p class="mt-1 text-sm text-gray-500">Tasks grouped by their due date.</p>
      </div>

      <div class="flex items-center gap-2">
        <button
            type="button"
            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="shiftMonth(-1)"
        >
          ‹ Prev
        </button>
        <button
            type="button"
            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="goToday"
        >
          Today
        </button>
        <button
            type="button"
            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            @click="shiftMonth(1)"
        >
          Next ›
        </button>
      </div>
    </div>

    <h2 class="mt-5 text-lg font-semibold text-gray-900">{{ monthLabel(year, month) }}</h2>

    <p v-if="loading" class="mt-4 text-sm text-gray-500">Loading tasks…</p>

    <p v-else-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>

    <p v-else-if="loadingMonth" class="mt-4 text-sm text-gray-500">Loading tasks for this month…</p>

    <div v-else class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 lg:col-span-2">
        <CalendarGrid
            :year="year"
            :month="month"
            :tasks="tasks"
            :selected-date="selectedDate"
            @select="selectedDate = $event"
        />
      </div>

      <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-gray-900">Agenda</h3>
          <span v-if="isSelectedToday" class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">
            Today
          </span>
        </div>

        <p class="mt-1 text-sm text-gray-500">{{ dayLabel(selectedDate) }}</p>

        <p v-if="isSelectedToday && overdue.length" class="mt-4 text-xs font-semibold uppercase tracking-wide text-red-600">
          Overdue
        </p>

        <ul v-if="isSelectedToday && overdue.length" class="mt-2 space-y-2">
          <li v-for="task in overdue" :key="task.id" class="rounded-lg bg-red-50 px-3 py-2">
            <AgendaItem :task="task" />
          </li>
        </ul>

        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
          {{ isSelectedToday && overdue.length ? 'Due today' : 'Due' }}
        </p>

        <ul v-if="dayTasks.length" class="mt-2 space-y-2">
          <li v-for="task in dayTasks" :key="task.id" class="rounded-lg bg-gray-50 px-3 py-2">
            <AgendaItem :task="task" />
          </li>
        </ul>

        <p v-else class="mt-4 text-sm text-gray-500">No tasks due on this date.</p>
      </div>
    </div>
  </main>
</template>

<script setup lang="ts">
import type { Task, TasksResponse } from '~/types/task'
import { dayLabel, isToday, isoDate, monthLabel, parseISODate, shiftMonth as shiftMonthRange } from '~/utils/calendar'

definePageMeta({
  middleware: 'auth',
})

useHead({
  title: 'Calendar',
})

const route = useRoute()
const { apiFetch } = useApi()

const now = new Date()

const initial = typeof route.query.date === 'string'
  ? parseISODate(route.query.date)
  : null

const year = ref(initial?.year ?? now.getFullYear())
const month = ref(initial?.month ?? now.getMonth())
const selectedDate = ref(
    initial
        ? (route.query.date as string)
        : isoDate(now.getFullYear(), now.getMonth(), now.getDate()),
)

const tasks = ref<Task[]>([])
const loading = ref(true)
const loadingMonth = ref(false)
const error = ref('')

const TASK_CAP = 1000

async function loadMonthTasks() {
  loadingMonth.value = true
  error.value = ''

  const dueFrom = isoDate(year.value, month.value, 1)
  const dueTo = isoDate(year.value, month.value, new Date(year.value, month.value + 1, 0).getDate())

  try {
    const all: Task[] = []
    let page = 1
    let lastPage = 1

    do {
      const response = await apiFetch<TasksResponse>(
          `/api/v1/tasks?due_from=${dueFrom}&due_to=${dueTo}&page=${page}`,
      )

      all.push(...response.data)
      lastPage = response.meta.last_page
      page++
    } while (page <= lastPage && all.length < TASK_CAP)

    tasks.value = all
  } catch (err: any) {
    error.value = err?.data?.message ?? 'Failed to load calendar tasks.'
  } finally {
    loading.value = false
    loadingMonth.value = false
  }
}

onMounted(() => {
  loadMonthTasks()
})

function shiftMonth(delta: number) {
  const shifted = shiftMonthRange(year.value, month.value, delta)

  year.value = shifted.year
  month.value = shifted.month
  selectedDate.value = isoDate(shifted.year, shifted.month, 1)
  loadMonthTasks()
}

function goToday() {
  const today = new Date()

  year.value = today.getFullYear()
  month.value = today.getMonth()
  selectedDate.value = isoDate(today.getFullYear(), today.getMonth(), today.getDate())
  loadMonthTasks()
}

const isSelectedToday = computed(() => isToday(selectedDate.value))

const dayTasks = computed(() =>
    tasks.value
        .filter((task) => task.due_date === selectedDate.value)
        .sort((a, b) => priorityRank(a.priority) - priorityRank(b.priority) || a.title.localeCompare(b.title)),
)

const overdue = computed(() =>
    tasks.value
        .filter((task) => task.status !== 'completed' && task.due_date && task.due_date < selectedDate.value)
        .sort((a, b) => (a.due_date! < b.due_date! ? -1 : 1)),
)

function priorityRank(priority: string): number {
  if (priority === 'high') {
    return 0
  }

  if (priority === 'medium') {
    return 1
  }

  return 2
}
</script>
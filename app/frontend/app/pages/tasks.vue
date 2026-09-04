<template>
  <nav>
    <strong>TaskFlow</strong>

    <button type="button" @click="handleLogout">
      Logout
    </button>
  </nav>
  <main>
    <h1>My Tasks</h1>

    <form @submit.prevent="createTask">
      <div>
        <label for="title">Title</label>
        <input
            id="title"
            v-model="form.title"
            type="text"
            required
        >
      </div>

      <div>
        <label for="description">Description</label>
        <textarea
            id="description"
            v-model="form.description"
        />
      </div>

      <div>
        <label for="status">Status</label>
        <select id="status" v-model="form.status">
          <option value="pending">Pending</option>
          <option value="in_progress">In progress</option>
          <option value="completed">Completed</option>
        </select>
      </div>

      <div>
        <label for="priority">Priority</label>
        <select id="priority" v-model="form.priority">
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
        </select>
      </div>

      <div>
        <label for="due_date">Due date</label>
        <input
            id="due_date"
            v-model="form.due_date"
            type="date"
        >
      </div>

      <button type="submit" :disabled="creating">
        {{ creating ? 'Creating...' : 'Create task' }}
      </button>

      <p v-if="createError">
        {{ createError }}
      </p>
    </form>
    <div>
      <label for="filter-status">Status</label>
      <select id="filter-status" v-model="filters.status" @change="applyFilters">
        <option value="">All</option>
        <option value="pending">Pending</option>
        <option value="in_progress">In progress</option>
        <option value="completed">Completed</option>
      </select>

      <label for="filter-priority">Priority</label>
      <select id="filter-priority" v-model="filters.priority" @change="applyFilters">
        <option value="">All</option>
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
      </select>
    </div>
    <hr>

    <p v-if="pending">Loading tasks...</p>

    <p v-else-if="error">
      Failed to load tasks.
    </p>

    <ul v-else>
      <li v-for="task in tasks" :key="task.id">
        <strong>{{ task.title }}</strong>
        — {{ task.status }}
        — {{ task.priority }}

        <button type="button" @click="updateTask(task)">
          Complete
        </button>

        <button type="button" @click="deleteTask(task.id)">
          Delete
        </button>
      </li>
    </ul>
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
interface Task {
  id: number
  title: string
  description: string | null
  status: string
  priority: string
  due_date: string | null
}

interface TasksResponse {
  data: Task[]
  meta: {
    current_page: number
    last_page: number
  }
}

interface TaskResponse {
  data: Task
}

const { getToken, logout } = useAuth()

const tasks = ref<Task[]>([])
const pending = ref(true)
const error = ref('')

const creating = ref(false)
const createError = ref('')

const currentPage = ref(1)
const lastPage = ref(1)

const form = reactive({
  title: '',
  description: '',
  status: 'pending',
  priority: 'medium',
  due_date: '',
})

const filters = reactive({
  status: '',
  priority: '',
})

onMounted(async () => {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

  await loadTasksWithFilters()
})

async function loadTasksWithFilters() {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

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

    params.set('page', String(currentPage.value))

    const query = params.toString()

    const response = await $fetch<TasksResponse>(
        query ? `/api/tasks?${query}` : '/api/tasks',
        {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        }
    )

    tasks.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch {
    error.value = 'Failed to load tasks.'
  } finally {
    pending.value = false
  }
}

async function applyFilters() {
  currentPage.value = 1
  await loadTasksWithFilters()
}

async function createTask() {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

  creating.value = true
  createError.value = ''

  try {
    const response = await $fetch<TaskResponse>('/api/tasks', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
      body: {
        title: form.title,
        description: form.description || null,
        status: form.status,
        priority: form.priority,
        due_date: form.due_date || null,
      },
    })

    tasks.value.unshift(response.data)

    form.title = ''
    form.description = ''
    form.status = 'pending'
    form.priority = 'medium'
    form.due_date = ''
  } catch (err: any) {
    createError.value =
        err?.data?.message ?? 'Failed to create task.'
  } finally {
    creating.value = false
  }
}

async function updateTask(task: Task) {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

  try {
    const response = await $fetch<TaskResponse>(
        `/api/tasks/${task.id}`,
        {
          method: 'PUT',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
          body: {
            status: 'completed',
          },
        }
    )

    const index = tasks.value.findIndex(
        (item: Task) => item.id === task.id
    )

    if (index !== -1) {
      tasks.value[index] = response.data
    }
  } catch {
    error.value = 'Failed to update task.'
  }
}

async function deleteTask(taskId: number) {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

  try {
    await $fetch(`/api/tasks/${taskId}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    })

    tasks.value = tasks.value.filter(
        (task: { id: number }) => task.id !== taskId
    )
  } catch {
    error.value = 'Failed to delete task.'
  }
}

async function handleLogout() {
  await logout()
  await navigateTo('/login')
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
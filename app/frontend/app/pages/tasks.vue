<template>
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
}

interface TaskResponse {
  data: Task
}

const { getToken } = useAuth()

const tasks = ref<Task[]>([])
const pending = ref(true)
const error = ref('')

const creating = ref(false)
const createError = ref('')

const form = reactive({
  title: '',
  description: '',
  status: 'pending',
  priority: 'medium',
  due_date: '',
})

onMounted(async () => {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

  await loadTasks(token)
})

async function loadTasks(token: string) {
  try {
    const response = await $fetch<TasksResponse>('/api/tasks', {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    })

    tasks.value = response.data
  } catch {
    error.value = 'Failed to load tasks.'
  } finally {
    pending.value = false
  }
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
</script>
<template>
  <main>
    <h1>My Tasks</h1>

    <p v-if="pending">Loading tasks...</p>

    <p v-else-if="error">
      Failed to load tasks.
    </p>

    <ul v-else>
      <li v-for="task in tasks" :key="task.id">
        <strong>{{ task.title }}</strong>
        — {{ task.status }}
        — {{ task.priority }}
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

const { getToken } = useAuth()

const tasks = ref<Task[]>([])
const pending = ref(true)
const error = ref('')

onMounted(async () => {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

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
})
</script>
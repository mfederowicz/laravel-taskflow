<template>
  <nav>
    <strong>TaskFlow</strong>
    <NuxtLink to="/projects">
      Projects
    </NuxtLink>
    <button type="button" @click="handleLogout">
      Logout
    </button>

  </nav>
  <main>
    <h1>My Tasks</h1>

    <form @submit.prevent="createTask">
      <div>
        <label for="project">Project</label>

        <select
            id="project"
            v-model="form.project_id"
            :disabled="projectsLoading"
            required
        >
          <option value="" disabled>
            {{ projectsLoading ? 'Loading projects...' : 'Select a project' }}
          </option>

          <option
              v-for="project in projects"
              :key="project.id"
              :value="String(project.id)"
          >
            {{ project.name }}
          </option>
        </select>

        <p v-if="validationErrors.project_id">
          {{ validationErrors.project_id[0] }}
        </p>

        <p v-if="projectsError">
          {{ projectsError }}
        </p>
      </div>
      <div>
        <label for="title">Title</label>
        <input
            id="title"
            v-model="form.title"
            type="text"
            required
        >
        <p v-if="validationErrors.title">
          {{ validationErrors.title[0] }}
        </p>
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
        <p v-if="validationErrors.status">
          {{ validationErrors.status[0] }}
        </p>
      </div>

      <div>
        <label for="priority">Priority</label>
        <select id="priority" v-model="form.priority">
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
        </select>
        <p v-if="validationErrors.priority">
          {{ validationErrors.priority[0] }}
        </p>
      </div>

      <div>
        <label for="due_date">Due date</label>
        <input
            id="due_date"
            v-model="form.due_date"
            type="date"
        >
        <p v-if="validationErrors.due_date">
          {{ validationErrors.due_date[0] }}
        </p>
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
        <template v-if="editingTaskId === task.id">
          <div>
            <label for="edit-project">Project</label>

            <select
                id="edit-project"
                v-model="editForm.project_id"
                required
            >
              <option value="" disabled>
                Select a project
              </option>

              <option
                  v-for="project in projects"
                  :key="project.id"
                  :value="String(project.id)"
              >
                {{ project.name }}
              </option>
            </select>

            <p v-if="updateValidationErrors.project_id">
              {{ updateValidationErrors.project_id[0] }}
            </p>
          </div>
          <input
              v-model="editForm.title"
              type="text"
          >
          <p v-if="updateValidationErrors.title">
            {{ updateValidationErrors.title[0] }}
          </p>

          <textarea
              v-model="editForm.description"
          />
          <p v-if="updateValidationErrors.description">
            {{ updateValidationErrors.description[0] }}
          </p>

          <select v-model="editForm.status">
            <option value="pending">Pending</option>
            <option value="in_progress">In progress</option>
            <option value="completed">Completed</option>
          </select>
          <p v-if="updateValidationErrors.status">
            {{ updateValidationErrors.status[0] }}
          </p>

          <select v-model="editForm.priority">
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
          <p v-if="updateValidationErrors.priority">
            {{ updateValidationErrors.priority[0] }}
          </p>

          <input
              v-model="editForm.due_date"
              type="date"
          >
          <p v-if="updateValidationErrors.due_date">
            {{ updateValidationErrors.due_date[0] }}
          </p>

          <button
              type="button"
              :disabled="updating"
              @click="updateTask"
          >
            {{ updating ? 'Saving...' : 'Save' }}
          </button>

          <button
              type="button"
              :disabled="updating"
              @click="cancelEditing"
          >
            Cancel
          </button>

          <p v-if="updateError">
            {{ updateError }}
          </p>
        </template>

        <template v-else>
          <strong>{{ task.title }}</strong>
          — {{ task.status }}
          — {{ task.priority }}
          <span v-if="task.project">
            — {{ task.project.name }}
          </span>

          <button
              type="button"
              @click="startEditing(task)"
          >
            Edit
          </button>

          <button
              type="button"
              @click="deleteTask(task.id)"
          >
            Delete
          </button>
        </template>
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
import type {
  Task,
  TaskResponse,
  TasksResponse,
} from '~/types/task'
import type {
  Project,
  ProjectsResponse,
} from '~/types/project'

const { getToken, logout } = useAuth()

const projects = ref<Project[]>([])
const projectsLoading = ref(true)
const projectsError = ref('')

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
})

const editingTaskId = ref<number | null>(null)

const editForm = reactive({
  project_id: '',
  title: '',
  description: '',
  status: 'pending',
  priority: 'medium',
  due_date: '',
})

const updating = ref(false)
const updateError = ref('')
const updateValidationErrors = ref<Record<string, string[]>>({})

onMounted(async () => {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

  await Promise.all([
    loadTasksWithFilters(),
    loadProjects(token),
  ])
})

async function loadProjects(token: string) {
  try {
    const response = await $fetch<ProjectsResponse>(
        '/api/projects',
        {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        }
    )

    projects.value = response.data
  } catch {
    projectsError.value = 'Failed to load projects.'
  } finally {
    projectsLoading.value = false
  }
}

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
  validationErrors.value = {}

  try {
    const response = await $fetch<TaskResponse>('/api/tasks', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
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

async function updateTask() {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

  if (editingTaskId.value === null) {
    return
  }

  updating.value = true
  updateError.value = ''
  updateValidationErrors.value = {}

  try {
    const response = await $fetch<TaskResponse>(
        `/api/tasks/${editingTaskId.value}`,
        {
          method: 'PUT',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
          body: {
            project_id: Number(editForm.project_id),
            title: editForm.title,
            description: editForm.description || null,
            status: editForm.status,
            priority: editForm.priority,
            due_date: editForm.due_date || null,
          },
        }
    )

    const index = tasks.value.findIndex(
        (item: Task) => item.id === editingTaskId.value
    )

    if (index !== -1) {
      tasks.value[index] = response.data
    }

    editingTaskId.value = null
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      updateValidationErrors.value = err.data.errors
    } else {
      updateError.value =
          err?.data?.message ?? 'Failed to update task.'
    }
  } finally {
    updating.value = false
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
function startEditing(task: Task) {
  editingTaskId.value = task.id

  editForm.project_id = task.project ? String(task.project.id) : ''
  editForm.title = task.title
  editForm.description = task.description ?? ''
  editForm.status = task.status
  editForm.priority = task.priority
  editForm.due_date = task.due_date ?? ''

  updateError.value = ''
  updateValidationErrors.value = {}
}
function cancelEditing() {
  editingTaskId.value = null
  updateError.value = ''
}
</script>
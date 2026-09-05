<template>
  <main>
    <nav>
      <strong>TaskFlow</strong>

      <NuxtLink to="/tasks">
        Tasks
      </NuxtLink>

      <button type="button" @click="handleLogout">
        Logout
      </button>
    </nav>

    <h1>My Projects</h1>

    <form @submit.prevent="createProject">
      <div>
        <label for="name">Name</label>
        <input
            id="name"
            v-model="form.name"
            type="text"
            required
        >

        <p v-if="validationErrors.name">
          {{ validationErrors.name[0] }}
        </p>
      </div>

      <div>
        <label for="description">Description</label>
        <textarea
            id="description"
            v-model="form.description"
        />

        <p v-if="validationErrors.description">
          {{ validationErrors.description[0] }}
        </p>
      </div>

      <button type="submit" :disabled="creating">
        {{ creating ? 'Creating...' : 'Create project' }}
      </button>

      <p v-if="createError">
        {{ createError }}
      </p>
    </form>

    <hr>

    <p v-if="pending">
      Loading projects...
    </p>

    <p v-else-if="error">
      Failed to load projects.
    </p>

    <ul v-else>
      <li v-for="project in projects" :key="project.id">
        <template v-if="editingProjectId === project.id">
          <input
              v-model="editForm.name"
              type="text"
          >

          <p v-if="updateValidationErrors.name">
            {{ updateValidationErrors.name[0] }}
          </p>

          <textarea
              v-model="editForm.description"
          />

          <p v-if="updateValidationErrors.description">
            {{ updateValidationErrors.description[0] }}
          </p>

          <button
              type="button"
              :disabled="updating"
              @click="updateProject"
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
          <strong>{{ project.name }}</strong>

          <span v-if="project.description">
      — {{ project.description }}
    </span>

          <button
              type="button"
              @click="startEditing(project)"
          >
            Edit
          </button>

          <button
              type="button"
              @click="deleteProject(project.id)"
          >
            Delete
          </button>
        </template>
      </li>
    </ul>
  </main>
</template>

<script setup lang="ts">
import type {
  Project,
  ProjectResponse,
  ProjectsResponse,
} from '~/types/project'

const { getToken, logout } = useAuth()

const projects = ref<Project[]>([])
const pending = ref(true)
const error = ref('')

const creating = ref(false)
const createError = ref('')
const validationErrors = ref<Record<string, string[]>>({})

const form = reactive({
  name: '',
  description: '',
})

const editingProjectId = ref<number | null>(null)

const editForm = reactive({
  name: '',
  description: '',
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

  await loadProjects(token)
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
    error.value = 'Failed to load projects.'
  } finally {
    pending.value = false
  }
}

async function createProject() {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

  creating.value = true
  createError.value = ''
  validationErrors.value = {}

  try {
    const response = await $fetch<ProjectResponse>(
        '/api/projects',
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
          body: {
            name: form.name,
            description: form.description || null,
          },
        }
    )

    projects.value.unshift(response.data)

    form.name = ''
    form.description = ''
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      validationErrors.value = err.data.errors
    } else {
      createError.value =
          err?.data?.message ?? 'Failed to create project.'
    }
  } finally {
    creating.value = false
  }
}

async function handleLogout() {
  await logout()
  await navigateTo('/login')
}

function startEditing(project: Project) {
  editingProjectId.value = project.id

  editForm.name = project.name
  editForm.description = project.description ?? ''

  updateError.value = ''
  updateValidationErrors.value = {}
}

function cancelEditing() {
  editingProjectId.value = null
  updateError.value = ''
  updateValidationErrors.value = {}
}

async function updateProject() {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

  if (editingProjectId.value === null) {
    return
  }

  updating.value = true
  updateError.value = ''
  updateValidationErrors.value = {}

  try {
    const response = await $fetch<ProjectResponse>(
        `/api/projects/${editingProjectId.value}`,
        {
          method: 'PUT',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
          body: {
            name: editForm.name,
            description: editForm.description || null,
          },
        }
    )

    const index = projects.value.findIndex(
        (item: Project) => item.id === editingProjectId.value
    )

    if (index !== -1) {
      projects.value[index] = response.data
    }

    editingProjectId.value = null
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      updateValidationErrors.value = err.data.errors
    } else {
      updateError.value =
          err?.data?.message ?? 'Failed to update project.'
    }
  } finally {
    updating.value = false
  }
}

async function deleteProject(projectId: number) {
  const token = getToken()

  if (!token) {
    await navigateTo('/login')
    return
  }

  try {
    await $fetch(`/api/projects/${projectId}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    })

    projects.value = projects.value.filter(
        (project: { id: number }) => project.id !== projectId
    )
  } catch {
    error.value = 'Failed to delete project.'
  }
}
</script>
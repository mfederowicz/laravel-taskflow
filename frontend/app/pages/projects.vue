<template>
  <AppNav />
  <main>


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
          <div class="list-item-content">
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
          </div>
          <div class="list-item-actions">
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
          </div>

        </template>

        <template v-else>
          <div class="list-item-content">
            <strong>{{ project.name }}</strong>

            <span v-if="project.description">
            — {{ project.description }}
          </span>
          </div>


          <div class="list-item-actions">
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
          </div>

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
definePageMeta({
  middleware: 'auth',
})

useHead({
  title: 'Projects',
})
import type {
  Project,
  ProjectResponse,
  ProjectsResponse,
} from '~/types/project'

const { apiFetch } = useApi()

const projects = ref<Project[]>([])
const pending = ref(true)
const error = ref('')

const currentPage = ref(1)
const lastPage = ref(1)

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
  await loadProjects()
})

async function loadProjects() {
  pending.value = true
  error.value = ''

  try {
    const params = new URLSearchParams()
    params.set('page', String(currentPage.value))
    const query = params.toString()

    const response = await apiFetch<ProjectsResponse>(
        query ? `/api/v1/projects?${query}` : '/api/v1/projects',
    )

    projects.value = response.data
    currentPage.value = response.meta?.current_page ?? 1
    lastPage.value = response.meta?.last_page ?? 1
  } catch {
    error.value = 'Failed to load projects.'
  } finally {
    pending.value = false
  }
}

async function previousPage() {
  if (currentPage.value <= 1) {
    return
  }

  currentPage.value--
  await loadProjects()
}

async function nextPage() {
  if (currentPage.value >= lastPage.value) {
    return
  }

  currentPage.value++
  await loadProjects()
}

async function createProject() {
  creating.value = true
  createError.value = ''
  validationErrors.value = {}

  try {
    const response = await apiFetch<ProjectResponse>(
        '/api/v1/projects',
        {
          method: 'POST',
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
  if (editingProjectId.value === null) {
    return
  }

  updating.value = true
  updateError.value = ''
  updateValidationErrors.value = {}

  try {
    const response = await apiFetch<ProjectResponse>(
        `/api/v1/projects/${editingProjectId.value}`,
        {
          method: 'PUT',
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
  try {
    await apiFetch(`/api/v1/projects/${projectId}`, {
      method: 'DELETE',
    })

    projects.value = projects.value.filter(
        (project: { id: number }) => project.id !== projectId
    )
  } catch {
    error.value = 'Failed to delete project.'
  }
}
</script>
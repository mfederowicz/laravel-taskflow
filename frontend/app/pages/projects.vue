<template>
  <AppNav />
  <main>
    <h1 class="mt-0 text-2xl font-bold text-gray-900">My Projects</h1>

    <form @submit.prevent="createProject" class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
      <div class="mb-4">
        <label for="name" class="mb-1 block text-sm font-semibold text-gray-700">Name</label>
        <input
            id="name"
            v-model="form.name"
            type="text"
            required
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        >

        <p v-if="validationErrors.name" class="mt-1 text-sm text-red-600">
          {{ validationErrors.name[0] }}
        </p>
      </div>

      <div class="mb-4">
        <label for="description" class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
        <textarea
            id="description"
            v-model="form.description"
            class="min-h-24 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        />

        <p v-if="validationErrors.description" class="mt-1 text-sm text-red-600">
          {{ validationErrors.description[0] }}
        </p>
      </div>

      <button type="submit" :disabled="creating" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
        {{ creating ? 'Creating...' : 'Create project' }}
      </button>

      <p v-if="createError" class="mt-3 text-sm text-red-600">
        {{ createError }}
      </p>
    </form>

    <p v-if="pending" class="text-sm text-gray-500">
      Loading projects...
    </p>

    <p v-else-if="error" class="text-sm text-red-600">
      {{ error }}
    </p>

    <ul v-else class="space-y-4">
      <li v-for="project in projects" :key="project.id" class="flex items-start gap-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <template v-if="editingProjectId === project.id">
          <div class="min-w-0 flex-1 space-y-4">
            <div>
              <label for="edit-name" class="mb-1 block text-sm font-semibold text-gray-700">Name</label>
              <input
                  v-model="editForm.name"
                  type="text"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              >
            </div>

            <p v-if="updateValidationErrors.name" class="text-sm text-red-600">
              {{ updateValidationErrors.name[0] }}
            </p>

            <div>
              <label for="edit-description" class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
              <textarea
                  v-model="editForm.description"
                  class="min-h-24 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              />
            </div>

            <p v-if="updateValidationErrors.description" class="text-sm text-red-600">
              {{ updateValidationErrors.description[0] }}
            </p>
          </div>
          <div class="flex shrink-0 flex-col gap-2">
            <button
                type="button"
                :disabled="updating"
                @click="updateProject"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
            >
              {{ updating ? 'Saving...' : 'Save' }}
            </button>

            <button
                type="button"
                :disabled="updating"
                @click="cancelEditing"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
            >
              Cancel
            </button>

            <p v-if="updateError" class="text-sm text-red-600">
              {{ updateError }}
            </p>
          </div>

        </template>

        <template v-else>
          <div class="min-w-0 flex-1">
            <strong class="text-gray-900">{{ project.name }}</strong>

            <span v-if="project.description" class="text-gray-600">
            — {{ project.description }}
          </span>
          </div>

          <div class="flex shrink-0 flex-col gap-2">
            <button
                type="button"
                @click="startEditing(project)"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              Edit
            </button>

            <button
                type="button"
                @click="deleteProject(project.id)"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
            >
              Delete
            </button>
          </div>

        </template>
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
  } catch (err: any) {
    error.value = err?.data?.message ?? 'Failed to load projects.'
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

    if (projects.value.length === 0 && currentPage.value > 1) {
      currentPage.value--
      await loadProjects()
    }
  } catch {
    error.value = 'Failed to delete project.'
  }
}
</script>
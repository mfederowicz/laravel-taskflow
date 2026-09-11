<template>
  <AppNav />
  <main>
    <p v-if="pending" class="mt-4 text-sm text-gray-500">
      Loading task...
    </p>

    <p v-else-if="error" class="mt-4 text-sm text-red-600">
      {{ error }}

      <NuxtLink to="/tasks" class="ml-1 font-semibold text-blue-600 hover:underline">
        ← Back to tasks
      </NuxtLink>
    </p>

    <template v-else-if="task">
      <div class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <p class="text-sm">
          <NuxtLink to="/tasks" class="font-semibold text-blue-600 hover:underline">
            ← Back to tasks
          </NuxtLink>
        </p>

        <form v-if="editing" @submit.prevent="updateTask" class="mt-4 space-y-4">
          <div>
            <label for="edit-project" class="mb-1 block text-sm font-semibold text-gray-700">Project</label>
            <select
                id="edit-project"
                v-model="editForm.project_id"
                required
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            >
              <option value="" disabled>Select a project</option>
              <option v-for="project in editableProjectOptions" :key="project.id" :value="String(project.id)">
                {{ project.name }}
              </option>
            </select>
            <p v-if="updateValidationErrors.project_id" class="mt-1 text-sm text-red-600">
              {{ updateValidationErrors.project_id[0] }}
            </p>
          </div>

          <div>
            <label for="edit-title" class="mb-1 block text-sm font-semibold text-gray-700">Title</label>
            <input
                id="edit-title"
                v-model="editForm.title"
                type="text"
                required
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            >
            <p v-if="updateValidationErrors.title" class="mt-1 text-sm text-red-600">
              {{ updateValidationErrors.title[0] }}
            </p>
          </div>

          <div>
            <label for="edit-description" class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
            <textarea
                id="edit-description"
                v-model="editForm.description"
                class="min-h-24 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            />
            <p v-if="updateValidationErrors.description" class="mt-1 text-sm text-red-600">
              {{ updateValidationErrors.description[0] }}
            </p>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
              <label for="edit-status" class="mb-1 block text-sm font-semibold text-gray-700">Status</label>
              <select id="edit-status" v-model="editForm.status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="pending">Pending</option>
                <option value="in_progress">In progress</option>
                <option value="completed">Completed</option>
              </select>
              <p v-if="updateValidationErrors.status" class="mt-1 text-sm text-red-600">
                {{ updateValidationErrors.status[0] }}
              </p>
            </div>

            <div>
              <label for="edit-priority" class="mb-1 block text-sm font-semibold text-gray-700">Priority</label>
              <select id="edit-priority" v-model="editForm.priority" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </select>
              <p v-if="updateValidationErrors.priority" class="mt-1 text-sm text-red-600">
                {{ updateValidationErrors.priority[0] }}
              </p>
            </div>

            <div>
              <label for="edit-due-date" class="mb-1 block text-sm font-semibold text-gray-700">Due date</label>
              <input
                  id="edit-due-date"
                  v-model="editForm.due_date"
                  type="date"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              >
              <p v-if="updateValidationErrors.due_date" class="mt-1 text-sm text-red-600">
                {{ updateValidationErrors.due_date[0] }}
              </p>
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-semibold text-gray-700">Tags</label>
            <div class="flex flex-wrap gap-2">
              <label
                  v-for="tag in tags"
                  :key="tag.id"
                  class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium"
                  :class="editForm.tag_ids.includes(tag.id) ? 'ring-2 ring-offset-1' : 'opacity-70'"
                  :style="tagChipStyle(tag)"
              >
                <input v-model="editForm.tag_ids" type="checkbox" :value="tag.id" class="sr-only">
                {{ tag.name }}
              </label>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <button
                type="submit"
                :disabled="updating"
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
          </div>

          <p v-if="updateError" class="text-sm text-red-600">
            {{ updateError }}
          </p>
        </form>

        <template v-else>
          <div class="mt-3 flex flex-wrap items-center gap-3">
            <h1 class="mt-0 text-2xl font-bold text-gray-900">{{ task.title }}</h1>

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
            <span
                v-if="dueBadge(task)"
                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="badgeClass(task)"
            >
              {{ dueBadge(task).label }}
            </span>
            <span
                v-if="task.shared"
                class="rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-700"
            >
              Shared
            </span>
          </div>

          <p class="mt-3 whitespace-pre-line text-sm text-gray-600">
            {{ task.description ?? 'No description.' }}
          </p>

          <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">
            <span>Owner: {{ task.user.name }}</span>
            <span v-if="task.project">Project: {{ task.project.name }}</span>
            <span v-if="task.due_date">Due: {{ task.due_date }}</span>
            <span>Created: {{ formatDate(task.created_at) }}</span>
            <span>Updated: {{ formatDate(task.updated_at) }}</span>
          </p>

          <div v-if="task.tags.length" class="mt-3 flex flex-wrap items-center gap-2">
            <span
                v-for="tag in task.tags"
                :key="tag.id"
                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                :style="tagChipStyle(tag)"
            >
              {{ tag.name }}

              <button
                  v-if="canEdit"
                  type="button"
                  :disabled="tagRemoving === tag.id"
                  class="ml-0.5 font-semibold leading-none hover:opacity-60 disabled:opacity-40"
                  title="Remove tag"
                  @click="removeTag(tag.id)"
              >
                ✕
              </button>
            </span>

            <span v-if="tagRemoveError" class="text-sm text-red-600">
              {{ tagRemoveError }}
            </span>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <button
                type="button"
                v-if="canTransfer"
                @click="startTransfer"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              Transfer
            </button>

            <button
                type="button"
                v-if="canEdit"
                @click="startEditing"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              Edit
            </button>

            <button
                type="button"
                v-if="canDelete"
                @click="deleteTask"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
            >
              Delete
            </button>
          </div>

          <p v-if="deleteError" class="mt-3 text-sm text-red-600">
            {{ deleteError }}
          </p>
        </template>
      </div>

      <form v-if="canTransfer && transferOpen" @submit.prevent="submitTransfer" class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
        <h2 class="mt-0 text-lg font-bold text-gray-900">Transfer ownership</h2>

<div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label for="transfer-user" class="mb-1 block text-sm font-semibold text-gray-700">Transfer to</label>
              <select
                  id="transfer-user"
                  v-model="transferForm.to_user_id"
                  required
                  class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              >
                <option value="" disabled>Select a user</option>
                <option v-for="user in transferUsers" :key="user.id" :value="String(user.id)">
                  {{ user.name }}
                </option>
              </select>
              <p v-if="transferValidationErrors.to_user_id" class="mt-1 text-sm text-red-600">
                {{ transferValidationErrors.to_user_id[0] }}
              </p>
              <p v-if="usersError" class="mt-1 text-sm text-red-600">
                {{ usersError }}
              </p>
            </div>

          <div>
            <label for="transfer-note" class="mb-1 block text-sm font-semibold text-gray-700">Note (optional)</label>
            <input
                id="transfer-note"
                v-model="transferForm.note"
                type="text"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            >
          </div>
        </div>

        <div class="mt-3 flex items-center gap-3">
          <button
              type="submit"
              :disabled="transferring"
              class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
          >
            {{ transferring ? 'Transferring...' : 'Transfer' }}
          </button>

          <button
              type="button"
              :disabled="transferring"
              @click="cancelTransfer"
              class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
          >
            Cancel
          </button>
        </div>

        <p v-if="transferError" class="mt-3 text-sm text-red-600">
          {{ transferError }}
        </p>
      </form>

      <div class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <h2 class="mt-0 text-lg font-bold text-gray-900">Comments</h2>

        <p v-if="commentsLoading" class="mt-3 text-sm text-gray-500">
          Loading comments...
        </p>

        <p v-else-if="commentsError" class="mt-3 text-sm text-red-600">
          {{ commentsError }}
        </p>

        <ul v-else-if="comments.length" class="mt-4 space-y-3">
          <li v-for="comment in comments" :key="comment.id" class="rounded-lg bg-gray-50 px-4 py-2">
            <div class="flex items-center justify-between gap-4">
              <p class="min-w-0 text-sm text-gray-900">
                <strong class="text-gray-900">{{ comment.user.name }}</strong>
                <span class="text-gray-600"> — {{ comment.body }}</span>
              </p>

              <button
                  type="button"
                  v-if="canDeleteComment(comment)"
                  :disabled="commentDeleting === comment.id"
                  class="shrink-0 rounded-lg bg-red-600 px-3 py-1 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                  @click="deleteComment(comment)"
              >
                {{ commentDeleting === comment.id ? 'Deleting...' : 'Delete' }}
              </button>
            </div>
          </li>
        </ul>

        <p v-else class="mt-3 text-sm text-gray-500">
          No comments yet.
        </p>

        <div v-if="commentLastPage > 1" class="mt-4 flex items-center gap-3">
          <button
              type="button"
              :disabled="commentCurrentPage === 1"
              @click="previousCommentsPage"
              class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
          >
            Previous
          </button>

          <span class="text-sm text-gray-600">
            Page {{ commentCurrentPage }} of {{ commentLastPage }}
          </span>

          <button
              type="button"
              :disabled="commentCurrentPage >= commentLastPage"
              @click="nextCommentsPage"
              class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
          >
            Next
          </button>
        </div>

        <form v-if="canComment" @submit.prevent="createComment" class="mt-6 flex flex-col gap-2">
          <textarea
              v-model="commentBody"
              placeholder="Write a comment..."
              required
              class="min-h-16 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          />

          <button
              type="submit"
              :disabled="commentCreating"
              class="self-start rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
          >
            {{ commentCreating ? 'Adding...' : 'Add comment' }}
          </button>
        </form>

        <p v-if="commentCreateError" class="mt-2 text-sm text-red-600">
          {{ commentCreateError }}
        </p>
      </div>

      <div class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
        <h2 class="mt-0 text-lg font-bold text-gray-900">Ownership history</h2>

        <ul v-if="task.ownership_history?.length" class="mt-4 space-y-1">
          <li v-for="entry in task.ownership_history" :key="entry.id" class="text-xs text-gray-600">
            {{ historyLine(entry) }}
          </li>
        </ul>

        <p v-else class="mt-3 text-sm text-gray-500">
          No recorded ownership history.
        </p>
      </div>
    </template>
  </main>
</template>

<script setup lang="ts">
import type {
  Comment,
  CommentsResponse,
  Task,
  TaskOwnershipHistoryEntry,
  TaskResponse,
} from '~/types/task'
import type { Project, ProjectsResponse } from '~/types/project'
import type { User, UsersResponse } from '~/types/user'
import type { Tag, TagsResponse } from '~/types/tag'
import {
  badgeClass,
  dueBadge,
  formatDate,
  priorityChipClass,
  priorityLabel,
  statusChipClass,
  statusLabel,
  tagChipStyle,
} from '~/utils/taskDisplay'

definePageMeta({
  middleware: 'auth',
})

useHead({
  title: 'Task',
})

const route = useRoute()
const { apiFetch } = useApi()
const { profile } = useAuth()

const taskId = Number(route.params.id)

const task = ref<Task | null>(null)
const pending = ref(true)
const error = ref('')

const projects = ref<Project[]>([])
const tags = ref<Tag[]>([])

const users = ref<User[]>([])
const usersError = ref('')

const editing = ref(false)
const updating = ref(false)
const updateError = ref('')
const updateValidationErrors = ref<Record<string, string[]>>({})

const tagRemoving = ref<number | null>(null)
const tagRemoveError = ref('')

const editForm = reactive({
  project_id: '',
  title: '',
  description: '',
  status: 'pending',
  priority: 'medium',
  due_date: '',
  tag_ids: [] as number[],
})

const transferOpen = ref(false)
const transferring = ref(false)
const transferError = ref('')
const transferValidationErrors = ref<Record<string, string[]>>({})
const transferForm = reactive({
  to_user_id: '',
  note: '',
})

const deleteError = ref('')

const comments = ref<Comment[]>([])
const commentsLoading = ref(false)
const commentsError = ref('')
const commentCurrentPage = ref(1)
const commentLastPage = ref(1)
const commentBody = ref('')
const commentCreating = ref(false)
const commentCreateError = ref('')
const commentDeleting = ref<number | null>(null)

const isManager = computed(() => profile.value?.role === 'manager')

const editableProjectOptions = computed<Project[]>(() => {
  const options = new Map<number, Project>()

  for (const project of projects.value) {
    if (
      project.role === 'owner' ||
      project.role === 'admin' ||
      project.role === 'editor'
    ) {
      options.set(project.id, project)
    }
  }

  if (task.value?.project && !options.has(task.value.project.id)) {
    options.set(task.value.project.id, {
      id: task.value.project.id,
      name: task.value.project.name,
      description: null,
      user: { id: 0, name: '' },
      role: 'owner',
      created_at: '',
      updated_at: '',
    })
  }

  return Array.from(options.values())
})

const projectRole = computed<string | null>(() => {
  if (!task.value?.project) {
    return null
  }

  return projects.value.find(
    (project: Project) => project.id === task.value!.project!.id,
  )?.role ?? null
})

const canEdit = computed(() => {
  if (!task.value) {
    return false
  }

  if (isManager.value) {
    return true
  }

  if (task.value.user.id === profile.value?.id) {
    return true
  }

  return projectRole.value === 'admin' || projectRole.value === 'editor'
})

const canDelete = computed(() => {
  if (!task.value) {
    return false
  }

  if (isManager.value) {
    return true
  }

  if (task.value.user.id === profile.value?.id) {
    return true
  }

  return projectRole.value === 'admin'
})

const canComment = computed(() => {
  if (!task.value) {
    return false
  }

  if (task.value.user.id === profile.value?.id) {
    return true
  }

  return projectRole.value === 'admin' || projectRole.value === 'editor'
})

const canTransfer = computed(() => isManager.value)

const transferUsers = computed(() =>
  users.value.filter(
    (user) => user.role === 'user' && user.status === 'active',
  ),
)

function canDeleteComment(comment: Comment): boolean {
  if (comment.user.id === profile.value?.id) {
    return true
  }

  if (task.value?.user.id === profile.value?.id) {
    return true
  }

  return projectRole.value === 'admin'
}

onMounted(async () => {
  await Promise.all([
    loadTask(),
    loadProjects(),
    loadTags(),
    loadComments(),
  ])

  if (isManager.value) {
    await loadAllUsers()
  }
})

async function loadTags() {
  try {
    const response = await apiFetch<TagsResponse>('/api/v1/tags')
    tags.value = response.data
  } catch {
    tags.value = []
  }
}

async function loadTask() {
  pending.value = true
  error.value = ''

  try {
    const response = await apiFetch<TaskResponse>(
      `/api/v1/tasks/${taskId}?with=history`,
    )

    task.value = response.data

    useHead({
      title: `Task: ${task.value.title}`,
    })
  } catch (err: any) {
    error.value = err?.data?.message ?? 'Failed to load task.'
  } finally {
    pending.value = false
  }
}

async function loadProjects() {
  try {
    const response = await apiFetch<ProjectsResponse>('/api/v1/projects')

    projects.value = response.data
  } catch {
    projects.value = []
  }
}

async function loadAllUsers() {
  usersError.value = ''
  users.value = []

  try {
    let page = 1
    let lastPage = 1

    do {
      const response = await apiFetch<UsersResponse>(
        `/api/v1/users?page=${page}`,
      )

      users.value.push(...response.data)
      lastPage = response.meta.last_page
      page++
    } while (page <= lastPage)
  } catch (err: any) {
    usersError.value = err?.data?.message ?? 'Failed to load users.'
  }
}

function startEditing() {
  if (!task.value) {
    return
  }

  editing.value = true

  editForm.project_id = task.value.project ? String(task.value.project.id) : ''
  editForm.title = task.value.title
  editForm.description = task.value.description ?? ''
  editForm.status = task.value.status
  editForm.priority = task.value.priority
  editForm.due_date = task.value.due_date ?? ''
  editForm.tag_ids = task.value.tags.map((tag) => tag.id)

  updateError.value = ''
  updateValidationErrors.value = {}
}

function cancelEditing() {
  editing.value = false
  updateError.value = ''
  updateValidationErrors.value = {}
}

async function updateTask() {
  if (!task.value) {
    return
  }

  updating.value = true
  updateError.value = ''
  updateValidationErrors.value = {}

  try {
    const response = await apiFetch<TaskResponse>(
      `/api/v1/tasks/${task.value.id}`,
      {
        method: 'PUT',
        body: {
          project_id: Number(editForm.project_id),
          title: editForm.title,
          description: editForm.description || null,
          status: editForm.status,
          priority: editForm.priority,
          due_date: editForm.due_date || null,
          tag_ids: editForm.tag_ids,
        },
      },
    )

    task.value = response.data
    editing.value = false
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      updateValidationErrors.value = err.data.errors
    } else {
      updateError.value = err?.data?.message ?? 'Failed to update task.'
    }
  } finally {
    updating.value = false
  }
}

async function removeTag(tagId: number) {
  if (!task.value) {
    return
  }

  tagRemoving.value = tagId
  tagRemoveError.value = ''

  const tagIds = task.value.tags
    .filter((tag) => tag.id !== tagId)
    .map((tag) => tag.id)

  try {
    const response = await apiFetch<TaskResponse>(
      `/api/v1/tasks/${task.value.id}`,
      {
        method: 'PUT',
        body: { tag_ids: tagIds },
      },
    )

    task.value = response.data
  } catch (err: any) {
    tagRemoveError.value = err?.data?.message ?? 'Failed to remove tag.'
  } finally {
    tagRemoving.value = null
  }
}

async function deleteTask() {
  if (!task.value) {
    return
  }

  deleteError.value = ''

  try {
    await apiFetch(`/api/v1/tasks/${task.value.id}`, {
      method: 'DELETE',
    })

    await navigateTo('/tasks')
  } catch {
    deleteError.value = 'Failed to delete task.'
  }
}

async function startTransfer() {
  transferOpen.value = true
  transferForm.to_user_id = ''
  transferForm.note = ''
  transferError.value = ''
  transferValidationErrors.value = {}
}

function cancelTransfer() {
  transferOpen.value = false
  transferError.value = ''
  transferValidationErrors.value = {}
}

async function submitTransfer() {
  if (!task.value) {
    return
  }

  transferring.value = true
  transferError.value = ''
  transferValidationErrors.value = {}

  try {
    const taskIdToTransfer = task.value.id

    const response = await apiFetch<TaskResponse>(
      `/api/v1/tasks/${taskIdToTransfer}/transfer`,
      {
        method: 'POST',
        body: {
          to_user_id: Number(transferForm.to_user_id),
          note: transferForm.note || null,
        },
      },
    )

    task.value = response.data
    transferOpen.value = false
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      transferValidationErrors.value = err.data.errors
    } else {
      transferError.value = err?.data?.message ?? 'Failed to transfer task.'
    }
  } finally {
    transferring.value = false
  }
}

async function loadComments(page = 1) {
  if (!task.value) {
    return
  }

  commentsLoading.value = true
  commentsError.value = ''

  try {
    const params = new URLSearchParams()
    params.set('page', String(page))

    const response = await apiFetch<CommentsResponse>(
      `/api/v1/tasks/${task.value.id}/comments?${params.toString()}`,
    )

    comments.value = response.data
    commentCurrentPage.value = response.meta?.current_page ?? 1
    commentLastPage.value = response.meta?.last_page ?? 1
  } catch (err: any) {
    commentsError.value = err?.data?.message ?? 'Failed to load comments.'
  } finally {
    commentsLoading.value = false
  }
}

async function previousCommentsPage() {
  if (commentCurrentPage.value <= 1) {
    return
  }

  await loadComments(commentCurrentPage.value - 1)
}

async function nextCommentsPage() {
  if (commentCurrentPage.value >= commentLastPage.value) {
    return
  }

  await loadComments(commentCurrentPage.value + 1)
}

async function createComment() {
  if (!task.value) {
    return
  }

  const body = commentBody.value.trim()

  if (!body) {
    return
  }

  commentCreating.value = true
  commentCreateError.value = ''

  try {
    const response = await apiFetch<{ data: Comment }>(
      `/api/v1/tasks/${task.value.id}/comments`,
      {
        method: 'POST',
        body: { body },
      },
    )

    comments.value.unshift(response.data)
    commentBody.value = ''
  } catch (err: any) {
    commentCreateError.value = err?.data?.message ?? 'Failed to create comment.'
  } finally {
    commentCreating.value = false
  }
}

async function deleteComment(comment: Comment) {
  if (!task.value) {
    return
  }

  commentDeleting.value = comment.id

  try {
    await apiFetch(
      `/api/v1/tasks/${task.value.id}/comments/${comment.id}`,
      { method: 'DELETE' },
    )

    comments.value = comments.value.filter(
      (item: Comment) => item.id !== comment.id,
    )
  } catch (err: any) {
    commentsError.value = err?.data?.message ?? 'Failed to delete comment.'
  } finally {
    commentDeleting.value = null
  }
}

function historyLine(entry: TaskOwnershipHistoryEntry): string {
  if (entry.from_user_id === null) {
    return `Created by ${entry.performed_by_name} on ${formatDate(entry.created_at)}`
  }

  const suffix = entry.note ? ` (${entry.note})` : ''

  return `Transferred from ${entry.from_user_name} to ${entry.to_user_name} by ${entry.performed_by_name}${suffix} on ${formatDate(entry.created_at)}`
}
</script>
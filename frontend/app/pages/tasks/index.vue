<template>
  <AppNav />
  <main>
    <h1 class="mt-0 text-2xl font-bold text-gray-900">{{ isManager ? 'All Tasks' : 'My Tasks' }}</h1>

    <form @submit.prevent="createTask" class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
      <div class="mb-4">
        <label for="project" class="mb-1 block text-sm font-semibold text-gray-700">Project</label>

        <select
            id="project"
            v-model="form.project_id"
            :disabled="projectsLoading"
            required
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        >
          <option value="" disabled>
            {{ projectsLoading ? 'Loading projects...' : 'Select a project' }}
          </option>

<option
                  v-for="project in creatableProjects"
                  :key="project.id"
                  :value="String(project.id)"
              >
                {{ project.name }}
              </option>
            </select>

              <p v-if="validationErrors.project_id" class="mt-1 text-sm text-red-600">
          {{ validationErrors.project_id[0] }}
        </p>

        <p v-if="projectsError" class="mt-1 text-sm text-red-600">
          {{ projectsError }}
        </p>
      </div>
      <div class="mb-4">
        <label for="title" class="mb-1 block text-sm font-semibold text-gray-700">Title</label>
        <input
            id="title"
            v-model="form.title"
            type="text"
            required
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        >
        <p v-if="validationErrors.title" class="mt-1 text-sm text-red-600">
          {{ validationErrors.title[0] }}
        </p>
      </div>

      <div class="mb-4">
        <label for="description" class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
        <textarea
            id="description"
            v-model="form.description"
            class="min-h-24 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        />
      </div>

      <div class="mb-4">
        <label for="status" class="mb-1 block text-sm font-semibold text-gray-700">Status</label>
        <select id="status" v-model="form.status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
          <option value="pending">Pending</option>
          <option value="in_progress">In progress</option>
          <option value="completed">Completed</option>
        </select>
        <p v-if="validationErrors.status" class="mt-1 text-sm text-red-600">
          {{ validationErrors.status[0] }}
        </p>
      </div>

      <div class="mb-4">
        <label for="priority" class="mb-1 block text-sm font-semibold text-gray-700">Priority</label>
        <select id="priority" v-model="form.priority" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
        </select>
        <p v-if="validationErrors.priority" class="mt-1 text-sm text-red-600">
          {{ validationErrors.priority[0] }}
        </p>
      </div>

      <div class="mb-4">
        <label for="due_date" class="mb-1 block text-sm font-semibold text-gray-700">Due date</label>
        <input
            id="due_date"
            v-model="form.due_date"
            type="date"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        >
        <p v-if="validationErrors.due_date" class="mt-1 text-sm text-red-600">
          {{ validationErrors.due_date[0] }}
        </p>
      </div>

      <button type="submit" :disabled="creating" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
        {{ creating ? 'Creating...' : 'Create task' }}
      </button>

      <p v-if="createError" class="mt-3 text-sm text-red-600">
        {{ createError }}
      </p>
    </form>

    <div class="mb-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" :class="isManager ? 'lg:grid-cols-6' : 'lg:grid-cols-5'">
        <div>
          <label for="filter-search" class="mb-1 block text-sm font-semibold text-gray-700">Search</label>
          <input
              id="filter-search"
              v-model="filters.search"
              type="search"
              placeholder="Search title or description"
              @keyup.enter="applyFilters"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
        </div>

        <div>
          <label for="filter-status" class="mb-1 block text-sm font-semibold text-gray-700">Status</label>
          <select id="filter-status" v-model="filters.status" @change="applyFilters" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In progress</option>
            <option value="completed">Completed</option>
          </select>
        </div>

        <div>
          <label for="filter-priority" class="mb-1 block text-sm font-semibold text-gray-700">Priority</label>
          <select id="filter-priority" v-model="filters.priority" @change="applyFilters" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            <option value="">All</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>

        <div v-if="isManager">
          <label for="filter-owner" class="mb-1 block text-sm font-semibold text-gray-700">Owner</label>
          <select
              id="filter-owner"
              v-model="filters.user_id"
              @change="applyFilters"
              class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
            <option value="">Everyone</option>
            <option v-for="user in allUsers" :key="user.id" :value="String(user.id)">
              {{ user.name }}
            </option>
          </select>
          <p v-if="usersError" class="mt-1 text-sm text-red-600">
            {{ usersError }}
          </p>
        </div>

        <div>
          <label for="filter-due-from" class="mb-1 block text-sm font-semibold text-gray-700">Due from</label>
          <input
              id="filter-due-from"
              v-model="filters.due_from"
              type="date"
              @change="applyFilters"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
        </div>

        <div>
          <label for="filter-due-to" class="mb-1 block text-sm font-semibold text-gray-700">Due to</label>
          <input
              id="filter-due-to"
              v-model="filters.due_to"
              type="date"
              @change="applyFilters"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
          >
        </div>
      </div>
    </div>

    <p v-if="pending" class="text-sm text-gray-500">Loading tasks...</p>

    <p v-else-if="error" class="text-sm text-red-600">
      {{ error }}
    </p>

    <p v-else-if="tasks.length === 0" class="text-sm text-gray-500">
      No tasks match your filters.
    </p>

    <ul v-else class="space-y-4">
      <li v-for="task in tasks" :key="task.id" class="flex items-start gap-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200" :class="{ 'opacity-60': task.status === 'completed' }">
        <template v-if="editingTaskId === task.id">
          <div class="min-w-0 flex-1 space-y-4">
            <div>
              <label for="edit-project" class="mb-1 block text-sm font-semibold text-gray-700">Project</label>

              <select
                  id="edit-project"
                  v-model="editForm.project_id"
                  required
                  class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
              >
                <option value="" disabled>
                  Select a project
                </option>

<option
                  v-for="project in allProjectOptions"
                  :key="project.id"
                  :value="String(project.id)"
              >
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

          <div class="flex shrink-0 flex-col gap-2">
            <button
                type="button"
                :disabled="updating"
                @click="updateTask"
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
        </template>

        <template v-else>
          <div class="min-w-0 flex-1">
            <div>
              <strong class="text-gray-900">{{ task.title }}</strong>

              <span class="ml-2 inline-flex items-center gap-1.5 align-middle">
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
              </span>

              <span v-if="task.project" class="text-gray-500">
                — {{ task.project.name }}
              </span>
              <span
                  v-if="task.shared"
                  class="ml-2 rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-700"
              >
                Shared
              </span>
              <span v-if="isManager" class="text-gray-500">
                — <span class="text-gray-400">owner: {{ task.user.name }}</span>
              </span>
              <span
                  v-if="dueBadge(task)"
                  class="ml-2 rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="badgeClass(task)"
              >
                {{ dueBadge(task).label }}
              </span>
            </div>
            <p v-if="task.description" class="mt-1 text-sm text-gray-600">
              {{ task.description }}
            </p>

            <form v-if="isManager && transferTaskId === task.id" @submit.prevent="submitTransfer" class="mt-3 rounded-lg bg-gray-50 px-4 py-3">
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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

            <div class="mt-3">
              <button
                  type="button"
                  @click="loadComments(task.id)"
                  class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
              >
                Load comments
              </button>

              <p v-if="commentLoading[task.id]" class="mt-2 text-sm text-gray-500">
                Loading comments...
              </p>

              <div v-if="comments[task.id]" class="mt-3 space-y-2">
                <div
                    v-for="comment in comments[task.id]"
                    :key="comment.id"
                    class="rounded-lg bg-gray-50 px-4 py-2"
                >
                  <strong class="text-sm text-gray-900">{{ comment.user.name }}</strong>
                  <span class="text-sm text-gray-600"> — {{ comment.body }}</span>
                </div>

                <div v-if="(commentLastPage[task.id] ?? 1) > 1" class="flex items-center gap-3">
                  <button
                      type="button"
                      :disabled="(commentCurrentPage[task.id] ?? 1) === 1"
                      @click="previousCommentsPage(task.id)"
                      class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                  >
                    Previous
                  </button>

                  <span class="text-sm text-gray-600">
                    Page {{ commentCurrentPage[task.id] ?? 1 }} of {{ commentLastPage[task.id] ?? 1 }}
                  </span>

                  <button
                      type="button"
                      :disabled="(commentCurrentPage[task.id] ?? 1) >= (commentLastPage[task.id] ?? 1)"
                      @click="nextCommentsPage(task.id)"
                      class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                  >
                    Next
                  </button>
                </div>

                <form v-if="canCommentTask(task)" @submit.prevent="createComment(task.id)" class="flex flex-col gap-2">
                  <textarea
                      v-model="commentBodies[task.id]"
                      placeholder="Write a comment..."
                      required
                      class="min-h-16 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                  />

                  <button
                      type="submit"
                      :disabled="commentCreating[task.id]"
                      class="self-start rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                  >
                    {{ commentCreating[task.id] ? 'Adding...' : 'Add comment' }}
                  </button>
                </form>

                <p v-if="commentErrors[task.id]" class="text-sm text-red-600">
                  {{ commentErrors[task.id] }}
                </p>
              </div>
            </div>

            <div v-if="historyOpen[task.id]" class="mt-3 rounded-lg bg-gray-50 px-4 py-3">
              <h4 class="text-sm font-semibold text-gray-900">Ownership history</h4>

              <p v-if="historyLoading[task.id]" class="mt-1 text-sm text-gray-500">
                Loading history...
              </p>

              <ul v-else-if="taskHistories[task.id]?.length" class="mt-1 space-y-1">
                <li
                    v-for="entry in taskHistories[task.id]"
                    :key="entry.id"
                    class="text-xs text-gray-600"
                >
                  {{ historyLine(entry) }}
                </li>
              </ul>

              <p v-else class="mt-1 text-sm text-gray-500">
                No recorded ownership history.
              </p>
            </div>

          </div>

          <div class="flex shrink-0 flex-col gap-2">
            <NuxtLink
                :to="`/tasks/${task.id}`"
                class="rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700"
            >
              Open
            </NuxtLink>

            <button
                type="button"
                v-if="isManager"
                @click="startTransfer(task)"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              Transfer
            </button>

            <button
                type="button"
                v-if="canEditTask(task)"
                @click="startEditing(task)"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              Edit
            </button>

            <button
                type="button"
                v-if="canDeleteTask(task)"
                @click="deleteTask(task.id)"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
            >
              Delete
            </button>

            <button
                type="button"
                @click="toggleHistory(task.id)"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              {{ historyOpen[task.id] ? 'Hide history' : 'History' }}
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
  title: 'Tasks',
})
import type {
  Task,
  TaskOwnershipHistoryEntry,
  TaskResponse,
  TasksResponse,
  Comment,
  CommentsResponse,
} from '~/types/task'
import type {
  Project,
  ProjectsResponse,
} from '~/types/project'
import type {
  User,
  UsersResponse,
} from '~/types/user'
import {
  badgeClass,
  dueBadge,
  formatDate,
  priorityChipClass,
  priorityLabel,
  statusChipClass,
  statusLabel,
} from '~/utils/taskDisplay'

const { apiFetch } = useApi()
const { profile, ensureProfile } = useAuth()

const isManager = computed(() => profile.value?.role === 'manager')

const comments = ref<Record<number, Comment[]>>({})
const commentBodies = ref<Record<number, string>>({})
const commentLoading = ref<Record<number, boolean>>({})
const commentCreating = ref<Record<number, boolean>>({})
const commentErrors = ref<Record<number, string>>({})
const commentCurrentPage = ref<Record<number, number>>({})
const commentLastPage = ref<Record<number, number>>({})

const projects = ref<Project[]>([])
const projectsLoading = ref(true)
const projectsError = ref('')

const creatableProjects = computed(() =>
    projects.value.filter((project) =>
        project.role === 'owner' ||
        project.role === 'admin' ||
        project.role === 'editor'
    )
)

const allProjectOptions = computed<Project[]>(() => {
  const options = new Map<number, Project>()

  for (const project of projects.value) {
    options.set(project.id, project)
  }

  for (const task of tasks.value) {
    if (task.project && !options.has(task.project.id)) {
      options.set(task.project.id, {
        id: task.project.id,
        name: task.project.name,
        description: null,
        user: { id: 0, name: '' },
        role: 'owner',
        created_at: '',
        updated_at: '',
      })
    }
  }

  return Array.from(options.values())
})

function taskProjectRole(task: Task): string | null {
  if (!task.project) {
    return null
  }

  return projects.value.find(
      (project: Project) => project.id === task.project!.id
  )?.role ?? null
}

function canEditTask(task: Task): boolean {
  if (isManager.value) {
    return true
  }

  if (task.user.id === profile.value?.id) {
    return true
  }

  const role = taskProjectRole(task)

  return role === 'admin' || role === 'editor'
}

function canDeleteTask(task: Task): boolean {
  if (isManager.value) {
    return true
  }

  if (task.user.id === profile.value?.id) {
    return true
  }

  return taskProjectRole(task) === 'admin'
}

function canCommentTask(task: Task): boolean {
  if (task.user.id === profile.value?.id) {
    return true
  }

  const role = taskProjectRole(task)

  return role === 'admin' || role === 'editor'
}

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
  search: '',
  due_from: '',
  due_to: '',
  user_id: '',
})

const allUsers = ref<User[]>([])
const transferUsers = computed(() =>
    allUsers.value.filter(
        (user) => user.role === 'user' && user.status === 'active'
    )
)
const usersError = ref('')

const transferTaskId = ref<number | null>(null)
const transferForm = reactive({
  to_user_id: '',
  note: '',
})
const transferring = ref(false)
const transferError = ref('')
const transferValidationErrors = ref<Record<string, string[]>>({})

const historyOpen = ref<Record<number, boolean>>({})
const historyLoading = ref<Record<number, boolean>>({})
const taskHistories = ref<Record<number, TaskOwnershipHistoryEntry[]>>({})

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
  await ensureProfile()

  await Promise.all([
    loadTasksWithFilters(),
    loadProjects(),
  ])

  if (isManager.value) {
    await loadAllUsers()
  }
})

async function loadAllUsers() {
  usersError.value = ''
  allUsers.value = []

  try {
    let page = 1
    let lastPage = 1

    do {
      const response = await apiFetch<UsersResponse>(
          `/api/v1/users?page=${page}`,
      )

      allUsers.value.push(...response.data)
      lastPage = response.meta.last_page
      page++
    } while (page <= lastPage)
  } catch (err: any) {
    usersError.value = err?.data?.message ?? 'Failed to load users.'
  }
}

async function loadProjects() {
  try {
    const response = await apiFetch<ProjectsResponse>(
        '/api/v1/projects',
    )

    projects.value = response.data
  } catch {
    projectsError.value = 'Failed to load projects.'
  } finally {
    projectsLoading.value = false
  }
}

async function loadTasksWithFilters() {
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

    if (filters.user_id) {
      params.set('user_id', filters.user_id)
    }

    if (filters.search.trim()) {
      params.set('search', filters.search.trim())
    }

    if (filters.due_from) {
      params.set('due_from', filters.due_from)
    }

    if (filters.due_to) {
      params.set('due_to', filters.due_to)
    }

    params.set('page', String(currentPage.value))

    const query = params.toString()

    const response = await apiFetch<TasksResponse>(
        query ? `/api/v1/tasks?${query}` : '/api/v1/tasks',
    )

    tasks.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (err: any) {
    error.value = err?.data?.message ?? 'Failed to load tasks.'
  } finally {
    pending.value = false
  }
}

async function applyFilters() {
  currentPage.value = 1
  await loadTasksWithFilters()
}

async function createTask() {
  creating.value = true
  createError.value = ''
  validationErrors.value = {}

  try {
    const response = await apiFetch<TaskResponse>('/api/v1/tasks', {
      method: 'POST',
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
  if (editingTaskId.value === null) {
    return
  }

  updating.value = true
  updateError.value = ''
  updateValidationErrors.value = {}

  try {
    const response = await apiFetch<TaskResponse>(
        `/api/v1/tasks/${editingTaskId.value}`,
        {
          method: 'PUT',
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
  try {
    await apiFetch(`/api/v1/tasks/${taskId}`, {
      method: 'DELETE',
    })

    tasks.value = tasks.value.filter(
        (task: { id: number }) => task.id !== taskId
    )

    delete comments.value[taskId]
    delete commentBodies.value[taskId]
    delete commentLoading.value[taskId]
    delete commentCreating.value[taskId]
    delete commentErrors.value[taskId]
    delete commentCurrentPage.value[taskId]
    delete commentLastPage.value[taskId]
    delete historyOpen.value[taskId]
    delete historyLoading.value[taskId]
    delete taskHistories.value[taskId]

    if (tasks.value.length === 0 && currentPage.value > 1) {
      currentPage.value--
      await loadTasksWithFilters()
    }
  } catch {
    error.value = 'Failed to delete task.'
  }
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

function startTransfer(task: Task) {
  transferTaskId.value = task.id
  transferForm.to_user_id = ''
  transferForm.note = ''
  transferError.value = ''
  transferValidationErrors.value = {}
}

function cancelTransfer() {
  transferTaskId.value = null
  transferError.value = ''
  transferValidationErrors.value = {}
}

async function submitTransfer() {
  if (transferTaskId.value === null) {
    return
  }

  transferring.value = true
  transferError.value = ''
  transferValidationErrors.value = {}

  try {
    const taskId = transferTaskId.value

    const response = await apiFetch<TaskResponse>(
        `/api/v1/tasks/${taskId}/transfer`,
        {
          method: 'POST',
          body: {
            to_user_id: Number(transferForm.to_user_id),
            note: transferForm.note || null,
          },
        }
    )

    const index = tasks.value.findIndex((item: Task) => item.id === taskId)

    if (index !== -1) {
      tasks.value[index] = response.data
    }

    transferTaskId.value = null
  } catch (err: any) {
    if (err?.status === 422 && err?.data?.errors) {
      transferValidationErrors.value = err.data.errors
    } else {
      transferError.value =
          err?.data?.message ?? 'Failed to transfer task.'
    }
  } finally {
    transferring.value = false
  }
}

async function toggleHistory(taskId: number) {
  historyOpen.value[taskId] = !(historyOpen.value[taskId] ?? false)

  if (historyOpen.value[taskId] && !taskHistories.value[taskId]) {
    await loadHistory(taskId)
  }
}

async function loadHistory(taskId: number) {
  historyLoading.value[taskId] = true

  try {
    const response = await apiFetch<TaskResponse>(
        `/api/v1/tasks/${taskId}?with=history`,
    )

    taskHistories.value[taskId] = response.data.ownership_history ?? []
  } catch (err: any) {
    taskHistories.value[taskId] = []
  } finally {
    historyLoading.value[taskId] = false
  }
}

function historyLine(entry: TaskOwnershipHistoryEntry): string {
  if (entry.from_user_id === null) {
    return `Created by ${entry.performed_by_name} on ${formatDate(entry.created_at)}`
  }

  const suffix = entry.note ? ` (${entry.note})` : ''

  return `Transferred from ${entry.from_user_name} to ${entry.to_user_name} by ${entry.performed_by_name}${suffix} on ${formatDate(entry.created_at)}`
}

async function loadComments(taskId: number) {
  await loadCommentsPage(taskId, 1)
}

async function loadCommentsPage(taskId: number, page: number) {
  commentLoading.value[taskId] = true
  commentErrors.value[taskId] = ''

  try {
    const params = new URLSearchParams()
    params.set('page', String(page))

    const response = await apiFetch<CommentsResponse>(
        `/api/v1/tasks/${taskId}/comments?${params.toString()}`,
    )

    comments.value[taskId] = response.data
    commentCurrentPage.value[taskId] = response.meta?.current_page ?? 1
    commentLastPage.value[taskId] = response.meta?.last_page ?? 1
  } catch (err: any) {
    commentErrors.value[taskId] = err?.data?.message ?? 'Failed to load comments.'
  } finally {
    commentLoading.value[taskId] = false
  }
}

async function previousCommentsPage(taskId: number) {
  const current = commentCurrentPage.value[taskId] ?? 1
  if (current <= 1) {
    return
  }
  await loadCommentsPage(taskId, current - 1)
}

async function nextCommentsPage(taskId: number) {
  const current = commentCurrentPage.value[taskId] ?? 1
  const last = commentLastPage.value[taskId] ?? 1
  if (current >= last) {
    return
  }
  await loadCommentsPage(taskId, current + 1)
}
async function createComment(taskId: number) {
  const body = commentBodies.value[taskId]?.trim()

  if (!body) {
    return
  }

  commentCreating.value[taskId] = true
  commentErrors.value[taskId] = ''

  try {
    const response = await apiFetch<{ data: Comment }>(
        `/api/v1/tasks/${taskId}/comments`,
        {
          method: 'POST',
          body: {
            body,
          },
        }
    )

    if (!comments.value[taskId]) {
      comments.value[taskId] = []
    }

    comments.value[taskId].unshift(response.data)
    commentBodies.value[taskId] = ''
  } catch (err: any) {
    commentErrors.value[taskId] =
        err?.data?.message ?? 'Failed to create comment.'
  } finally {
    commentCreating.value[taskId] = false
  }
}
</script>
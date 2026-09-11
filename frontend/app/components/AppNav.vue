<template>
  <nav class="flex items-center gap-4 bg-white px-6 py-3 shadow-sm">
    <strong class="mr-2">TaskFlow</strong>

    <NuxtLink class="text-sm hover:text-blue-600" to="/dashboard">
      Dashboard
    </NuxtLink>

    <NuxtLink class="text-sm hover:text-blue-600" to="/tasks">
      Tasks
    </NuxtLink>

    <NuxtLink class="text-sm hover:text-blue-600" to="/projects">
      Projects
    </NuxtLink>

    <NuxtLink v-if="isManager" class="text-sm hover:text-blue-600" to="/users">
      Users
    </NuxtLink>

    <NuxtLink v-if="isManager" class="text-sm hover:text-blue-600" to="/oauth">
      OAuth clients
    </NuxtLink>

    <a href="/api/docs" target="_blank" rel="noopener">
      API docs
    </a>

    <div class="relative ml-auto">
      <button
          type="button"
          class="relative rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
          aria-label="Notifications"
          @click="notificationOpen = !notificationOpen"
      >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
          />
        </svg>

        <span
            v-if="unread > 0"
            class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-bold text-white"
        >
          {{ unread > 99 ? '99+' : unread }}
        </span>
      </button>

      <div v-if="notificationOpen" class="absolute right-0 z-50 mt-2 w-96 rounded-xl bg-white shadow-lg ring-1 ring-gray-200">
        <p class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-900">
          Notifications
        </p>

        <div class="max-h-96 overflow-y-auto">
          <p v-if="loading" class="px-4 py-3 text-sm text-gray-500">
            Loading...
          </p>

          <ul v-else-if="notifications.length" class="divide-y divide-gray-100">
            <li v-for="notification in notifications" :key="notification.id" class="group relative">
              <button
                  type="button"
                  class="flex w-full items-start gap-3 px-4 py-3 pr-10 text-left hover:bg-gray-50"
                  @click="openNotification(notification)"
              >
                <span
                    :class="notification.read_at ? 'bg-gray-200' : 'bg-blue-600'"
                    class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                />
                <span class="min-w-0">
                  <span class="block text-sm font-semibold text-gray-900">
                    {{ notification.title }}
                  </span>
                  <span class="block text-sm text-gray-600">
                    {{ notification.body }}
                  </span>
                  <span class="mt-0.5 block text-xs text-gray-400">
                    {{ relativeTime(notification.created_at) }}
                  </span>
                </span>
              </button>

              <button
                  type="button"
                  class="absolute right-2 top-2.5 rounded p-1 text-gray-300 hover:text-red-600"
                  aria-label="Dismiss notification"
                  @click="handleRemove(notification.id)"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </li>
          </ul>

          <p v-else class="px-4 py-3 text-sm text-gray-500">
            No notifications yet.
          </p>
        </div>

        <div class="flex gap-3 border-t border-gray-100 px-4 py-3">
          <button
              type="button"
              class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
              :disabled="unread === 0"
              @click="handleMarkAllRead"
          >
            Mark all as read
          </button>

          <button
              type="button"
              class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              :disabled="hasReadNotifications === false"
              @click="handleClearRead"
          >
            Clear read
          </button>
        </div>
      </div>
    </div>

    <button type="button" @click="handleLogout">
      Logout
    </button>
  </nav>
</template>

<script setup lang="ts">
import type { NotificationItem } from '~/types/notification'

const { logout, getToken, ensureProfile, profile } = useAuth()
const {
    notifications,
    unread,
    loading,
    loadNotifications,
    loadUnread,
    markRead,
    markAllRead,
    remove,
    clearRead,
    startPolling,
    stopPolling,
} = useNotifications()

const isManager = computed(() => profile.value?.role === 'manager')
const notificationOpen = ref(false)
const hasReadNotifications = computed(() =>
    notifications.value.some((n) => n.read_at)
)

onMounted(async () => {
  if (getToken()) {
    await ensureProfile()
    await loadNotifications()
    await loadUnread()
    startPolling()
  }
})

onBeforeUnmount(() => {
  stopPolling()
})

async function openNotification(notification: NotificationItem) {
  if (!notification.read_at) {
    await markRead(notification.id)
  }

  notificationOpen.value = false
  await navigateTo('/tasks')
}

async function handleMarkAllRead() {
  await markAllRead()
}

async function handleClearRead() {
  await clearRead()
}

async function handleRemove(id: number) {
  await remove(id)
}

async function handleLogout() {
  stopPolling()
  await logout()
  await navigateTo('/login')
}

function relativeTime(value: string): string {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  const seconds = Math.floor((Date.now() - date.getTime()) / 1000)

  if (seconds < 60) {
    return 'just now'
  }

  const minutes = Math.floor(seconds / 60)

  if (minutes < 60) {
    return `${minutes}m ago`
  }

  const hours = Math.floor(minutes / 60)

  if (hours < 24) {
    return `${hours}h ago`
  }

  const days = Math.floor(hours / 24)

  if (days === 1) {
    return 'yesterday'
  }

  if (days < 7) {
    return `${days}d ago`
  }

  return date.toLocaleDateString()
}
</script>
import type {
    ClearReadResponse,
    NotificationItem,
    NotificationsResponse,
    ReadAllResponse,
    UnreadCountResponse,
} from '~/types/notification'

function useNotifications() {
    const { apiFetch } = useApi()

    const notifications = ref<NotificationItem[]>([])
    const unread = ref(0)
    const loading = ref(false)
    const error = ref('')

    let pollTimer: ReturnType<typeof setInterval> | null = null

    async function loadNotifications() {
        loading.value = true
        error.value = ''

        try {
            const response = await apiFetch<NotificationsResponse>(
                '/api/v1/notifications',
            )

            notifications.value = response.data
        } catch (err: any) {
            error.value =
                err?.data?.message ?? 'Failed to load notifications.'
        } finally {
            loading.value = false
        }
    }

    async function loadUnread() {
        try {
            const response = await apiFetch<UnreadCountResponse>(
                '/api/v1/notifications/unread-count',
            )

            unread.value = response.data.unread
        } catch (err: any) {
            // Silent: polling failures should not disturb the session.
        }
    }

    async function markRead(id: number) {
        try {
            await apiFetch(`/api/v1/notifications/${id}/read`, {
                method: 'PATCH',
            })

            const item = notifications.value.find((n) => n.id === id)

            if (item) {
                item.read_at = new Date().toISOString()
            }

            if (unread.value > 0) {
                unread.value -= 1
            }
        } catch (err: any) {
            error.value = err?.data?.message ?? 'Failed to update notification.'
        }
    }

    async function markAllRead() {
        try {
            const response = await apiFetch<ReadAllResponse>(
                '/api/v1/notifications/read-all',
                { method: 'POST' },
            )

            unread.value = 0

            const now = new Date().toISOString()
            notifications.value.forEach((n) => {
                n.read_at = now
            })

            return response.data.updated
        } catch (err: any) {
            error.value = err?.data?.message ?? 'Failed to update notifications.'
            return 0
        }
    }

    function remove(id: number) {
        return apiFetch(`/api/v1/notifications/${id}`, {
            method: 'DELETE',
        }).then(() => {
            const index = notifications.value.findIndex((n) => n.id === id)

            if (index !== -1) {
                const [removed] = notifications.value.splice(index, 1)

                if (removed && !removed.read_at && unread.value > 0) {
                    unread.value -= 1
                }
            }
        }).catch((err: any) => {
            error.value = err?.data?.message ?? 'Failed to remove notification.'
        })
    }

    async function clearRead() {
        try {
            const response = await apiFetch<ClearReadResponse>(
                '/api/v1/notifications/clear-read',
                { method: 'POST' },
            )

            notifications.value = notifications.value.filter((n) => !n.read_at)

            return response.data.deleted
        } catch (err: any) {
            error.value = err?.data?.message ?? 'Failed to clear notifications.'
            return 0
        }
    }

    function startPolling(intervalMs = 60000) {
        stopPolling()
        pollTimer = setInterval(() => {
            loadUnread()
        }, intervalMs)
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer)
            pollTimer = null
        }
    }

    return {
        notifications,
        unread,
        loading,
        error,
        loadNotifications,
        loadUnread,
        markRead,
        markAllRead,
        remove,
        clearRead,
        startPolling,
        stopPolling,
    }
}

export default useNotifications
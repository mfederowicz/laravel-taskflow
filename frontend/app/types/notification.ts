export interface NotificationItem {
    id: number
    type: 'task_due' | 'task_overdue'
    title: string
    body: string | null
    task_id: number | null
    read_at: string | null
    created_at: string
}

export interface NotificationsResponse {
    data: NotificationItem[]
    meta: {
        current_page: number
        last_page: number
    }
}

export interface UnreadCountResponse {
    data: {
        unread: number
    }
}

export interface ReadAllResponse {
    data: {
        updated: number
    }
}

export interface ClearReadResponse {
    data: {
        deleted: number
    }
}
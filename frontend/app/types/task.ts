export interface Comment {
    id: number
    body: string
    user: {
        id: number
        name: string
    }
    created_at: string
    updated_at: string
}

export interface CommentsResponse {
    data: Comment[]
    meta?: {
        current_page: number
        last_page: number
    }
}

export interface TaskOwnershipHistoryEntry {
    id: number
    performed_by: number
    performed_by_name: string
    from_user_id: number | null
    from_user_name: string | null
    to_user_id: number
    to_user_name: string
    note: string | null
    created_at: string
}

export interface Task {
    id: number
    title: string
    description: string | null
    status: string
    priority: string
    due_date: string | null
    user: {
        id: number
        name: string
    }
    project: {
        id: number
        name: string
    } | null
    shared?: boolean
    ownership_history?: TaskOwnershipHistoryEntry[]
    created_at: string
    updated_at: string
}

export interface TasksResponse {
    data: Task[]
    meta: {
        current_page: number
        last_page: number
    }
}

export interface TaskResponse {
    data: Task
}

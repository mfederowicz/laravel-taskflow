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
}

export interface Task {
    id: number
    title: string
    description: string | null
    status: string
    priority: string
    due_date: string | null
    project: {
        id: number
        name: string
    }
    comments: Comment[]
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

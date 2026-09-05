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
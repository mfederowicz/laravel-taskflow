export interface Project {
    id: number
    name: string
    description: string | null
    user: {
        id: number
        name: string
    }
    created_at: string
    updated_at: string
}

export interface ProjectsResponse {
    data: Project[]
    meta?: {
        current_page: number
        last_page: number
    }
}

export interface ProjectResponse {
    data: Project
}
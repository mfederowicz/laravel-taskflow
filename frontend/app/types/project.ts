export interface Project {
    id: number
    name: string
    description: string | null
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
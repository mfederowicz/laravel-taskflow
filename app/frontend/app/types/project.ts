export interface Project {
    id: number
    name: string
    description: string | null
}

export interface ProjectsResponse {
    data: Project[]
}

export interface ProjectResponse {
    data: Project
}
export type ProjectRole = 'owner' | 'admin' | 'editor' | 'viewer'

export interface Project {
    id: number
    name: string
    description: string | null
    user: {
        id: number
        name: string
    }
    role: ProjectRole
    created_at: string
    updated_at: string
}

export interface ProjectMember {
    id: number
    user: {
        id: number
        name: string
        email: string
    }
    role: Exclude<ProjectRole, 'owner'>
    created_at: string
    updated_at: string
}

export interface MembersResponse {
    data: ProjectMember[]
    meta?: {
        current_page: number
        last_page: number
    }
}

export interface MemberResponse {
    data: ProjectMember
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
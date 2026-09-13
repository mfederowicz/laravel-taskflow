export type OrganizationRole = 'owner' | 'admin' | 'editor' | 'viewer'

export interface Organization {
    id: number
    name: string
    description: string | null
    owner: {
        id: number
        name: string
    }
    role: OrganizationRole
    created_at: string
    updated_at: string
}

export interface OrganizationMember {
    id: number
    user: {
        id: number
        name: string
        email: string
    }
    role: Exclude<OrganizationRole, 'owner'>
    created_at: string
    updated_at: string
}

export interface OrganizationsResponse {
    data: Organization[]
    meta?: {
        current_page: number
        last_page: number
    }
}

export interface OrganizationResponse {
    data: Organization
}

export interface OrgMembersResponse {
    data: OrganizationMember[]
    meta?: {
        current_page: number
        last_page: number
    }
}

export interface OrgMemberResponse {
    data: OrganizationMember
}

export interface OrganizablePayload {
    name: string
    description?: string | null
}
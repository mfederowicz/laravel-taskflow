export interface User {
    id: number
    name: string
    email: string
    role: 'user' | 'manager'
    status: 'active' | 'locked'
    created_at: string
    updated_at: string
}

export interface UsersResponse {
    data: User[]
    meta: {
        current_page: number
        last_page: number
    }
}

export interface UserResponse {
    data: User
}
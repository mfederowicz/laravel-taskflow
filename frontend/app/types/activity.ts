export interface ActivityActor {
    id: number
    name: string
}

export interface Activity {
    id: number
    actor: ActivityActor | null
    task_id: number | null
    type: string
    message: string
    payload: Record<string, unknown>
    created_at: string
}

export interface ActivitiesResponse {
    data: Activity[]
    meta?: {
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
}

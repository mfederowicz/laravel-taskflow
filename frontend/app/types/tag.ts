export interface Tag {
    id: number
    name: string
    color: string | null
}

export interface TagsResponse {
    data: Tag[]
}

export interface TagResponse {
    data: Tag
}
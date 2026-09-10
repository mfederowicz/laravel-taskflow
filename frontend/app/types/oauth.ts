export interface OAuthClient {
    id: string
    name: string
    created_at: string
}

export interface OAuthClientsResponse {
    data: OAuthClient[]
}

export interface OAuthClientCreated {
    data: {
        client_id: string
        client_secret: string
        name: string
    }
}
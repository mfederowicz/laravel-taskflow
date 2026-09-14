export interface PersonalToken {
  id: number
  name: string
  expires_at: string | null
  last_used_at: string | null
  created_at: string
}

export interface PersonalTokensResponse {
  data: PersonalToken[]
}

export interface CreateTokenResponse {
  data: {
    token: PersonalToken
    plain_token: string
  }
}

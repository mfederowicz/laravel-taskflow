import type { User } from '~/types/user'
import type {
    OrganizablePayload,
    OrganizationMember,
    Organization,
    OrganizationResponse,
    OrganizationsResponse,
    OrgMemberResponse,
    OrgMembersResponse,
} from '~/types/organization'

function useOrganizations() {
    const { apiFetch } = useApi()

    async function listOrganizations(
        page = 1,
    ): Promise<{ organizations: Organization[]; currentPage: number; lastPage: number }> {
        const response = await apiFetch<OrganizationsResponse>(
            `/api/v1/organizations?page=${page}`,
        )

        return {
            organizations: response.data,
            currentPage: response.meta?.current_page ?? 1,
            lastPage: response.meta?.last_page ?? 1,
        }
    }

    async function getOrganization(organizationId: number): Promise<Organization> {
        const response = await apiFetch<OrganizationResponse>(
            `/api/v1/organizations/${organizationId}`,
        )

        return response.data
    }

    async function createOrganization(payload: OrganizablePayload): Promise<Organization> {
        const response = await apiFetch<OrganizationResponse>('/api/v1/organizations', {
            method: 'POST',
            body: payload,
        })

        return response.data
    }

    async function updateOrganization(
        organizationId: number,
        payload: OrganizablePayload,
    ): Promise<Organization> {
        const response = await apiFetch<OrganizationResponse>(
            `/api/v1/organizations/${organizationId}`,
            {
                method: 'PUT',
                body: payload,
            },
        )

        return response.data
    }

    async function deleteOrganization(organizationId: number): Promise<void> {
        await apiFetch(`/api/v1/organizations/${organizationId}`, {
            method: 'DELETE',
        })
    }

    async function listMembers(
        organizationId: number,
        page = 1,
    ): Promise<{ members: OrganizationMember[]; currentPage: number; lastPage: number }> {
        const response = await apiFetch<OrgMembersResponse>(
            `/api/v1/organizations/${organizationId}/members?page=${page}`,
        )

        return {
            members: response.data,
            currentPage: response.meta?.current_page ?? 1,
            lastPage: response.meta?.last_page ?? 1,
        }
    }

    async function listAllMembers(organizationId: number): Promise<OrganizationMember[]> {
        const members: OrganizationMember[] = []
        let page = 1
        let lastPage = 1

        do {
            const result = await listMembers(organizationId, page)

            members.push(...result.members)
            lastPage = result.lastPage
            page++
        } while (page <= lastPage)

        return members
    }

    async function addMember(
        organizationId: number,
        userId: number,
        role: string,
    ): Promise<OrganizationMember> {
        const response = await apiFetch<OrgMemberResponse>(
            `/api/v1/organizations/${organizationId}/members`,
            {
                method: 'POST',
                body: {
                    user_id: userId,
                    role,
                },
            },
        )

        return response.data
    }

    async function updateMemberRole(
        organizationId: number,
        memberId: number,
        role: string,
    ): Promise<OrganizationMember> {
        const response = await apiFetch<OrgMemberResponse>(
            `/api/v1/organizations/${organizationId}/members/${memberId}`,
            {
                method: 'PATCH',
                body: {
                    role,
                },
            },
        )

        return response.data
    }

    async function removeMember(
        organizationId: number,
        memberId: number,
    ): Promise<void> {
        await apiFetch(`/api/v1/organizations/${organizationId}/members/${memberId}`, {
            method: 'DELETE',
        })
    }

    async function searchUsers(query: string): Promise<User[]> {
        const params = new URLSearchParams()

        if (query.trim()) {
            params.set('q', query.trim())
        }

        const qs = params.toString()

        const response = await apiFetch<{ data: User[] }>(
            qs ? `/api/v1/users/search?${qs}` : '/api/v1/users/search',
        )

        return response.data
    }

    return {
        listOrganizations,
        getOrganization,
        createOrganization,
        updateOrganization,
        deleteOrganization,
        listMembers,
        listAllMembers,
        addMember,
        updateMemberRole,
        removeMember,
        searchUsers,
    }
}

export default useOrganizations
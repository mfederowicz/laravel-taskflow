import type { User } from '~/types/user'
import type {
    MemberResponse,
    MembersResponse,
    ProjectMember,
} from '~/types/project'

function useProjectMembers() {
    const { apiFetch } = useApi()

    async function listMembers(projectId: number): Promise<ProjectMember[]> {
        const response = await apiFetch<MembersResponse>(
            `/api/v1/projects/${projectId}/members`,
        )

        return response.data
    }

    async function listMembersPaginated(
        projectId: number,
        page: number = 1,
    ): Promise<{
        members: ProjectMember[]
        currentPage: number
        lastPage: number
    }> {
        const response = await apiFetch<MembersResponse>(
            `/api/v1/projects/${projectId}/members?page=${page}`,
        )

        return {
            members: response.data,
            currentPage: response.meta?.current_page ?? page,
            lastPage: response.meta?.last_page ?? 1,
        }
    }

    async function addMember(
        projectId: number,
        userId: number,
        role: string,
    ): Promise<ProjectMember> {
        const response = await apiFetch<MemberResponse>(
            `/api/v1/projects/${projectId}/members`,
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
        projectId: number,
        memberId: number,
        role: string,
    ): Promise<ProjectMember> {
        const response = await apiFetch<MemberResponse>(
            `/api/v1/projects/${projectId}/members/${memberId}`,
            {
                method: 'PATCH',
                body: {
                    role,
                },
            },
        )

        return response.data
    }

    async function removeMember(projectId: number, memberId: number): Promise<void> {
        await apiFetch(`/api/v1/projects/${projectId}/members/${memberId}`, {
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
        listMembers,
        listMembersPaginated,
        addMember,
        updateMemberRole,
        removeMember,
        searchUsers,
    }
}

export default useProjectMembers
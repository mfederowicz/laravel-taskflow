<template>
  <nav class="flex items-center gap-4 bg-white px-6 py-3 shadow-sm">
    <strong class="mr-2">TaskFlow</strong>

    <NuxtLink class="text-sm hover:text-blue-600" to="/tasks">
      Tasks
    </NuxtLink>

    <NuxtLink class="text-sm hover:text-blue-600" to="/projects">
      Projects
    </NuxtLink>

    <NuxtLink v-if="isManager" class="text-sm hover:text-blue-600" to="/users">
      Users
    </NuxtLink>

    <NuxtLink v-if="isManager" class="text-sm hover:text-blue-600" to="/oauth">
      OAuth clients
    </NuxtLink>

    <a href="/api/docs" target="_blank" rel="noopener">
      API docs
    </a>

    <button type="button" @click="handleLogout">
      Logout
    </button>
  </nav>
</template>

<script setup lang="ts">
const { logout, getToken, ensureProfile, profile } = useAuth()

const isManager = computed(() => profile.value?.role === 'manager')

onMounted(async () => {
  if (getToken()) {
    await ensureProfile()
  }
})

async function handleLogout() {
  await logout()
  await navigateTo('/login')
}
</script>
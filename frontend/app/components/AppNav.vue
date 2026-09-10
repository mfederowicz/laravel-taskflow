<template>
  <nav>
    <strong>TaskFlow</strong>

    <NuxtLink to="/tasks">
      Tasks
    </NuxtLink>

    <NuxtLink to="/projects">
      Projects
    </NuxtLink>

    <NuxtLink v-if="isManager" to="/users">
      Users
    </NuxtLink>

    <NuxtLink v-if="isManager" to="/oauth">
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
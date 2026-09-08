<template>
  <nav>
    <strong>TaskFlow</strong>

    <NuxtLink to="/tasks">
      Tasks
    </NuxtLink>

    <NuxtLink to="/projects">
      Projects
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
const { logout } = useAuth()

async function handleLogout() {
  await logout()
  await navigateTo('/login')
}
</script>
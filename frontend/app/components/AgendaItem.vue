<template>
  <div class="min-w-0">
    <NuxtLink :to="`/tasks/${task.id}`" class="block min-w-0">
      <p class="truncate text-sm font-medium text-gray-900 hover:text-blue-600">{{ task.title }}</p>
      <p class="mt-0.5 truncate text-xs text-gray-500">
        {{ task.project ? task.project.name : 'No project' }}
      </p>
    </NuxtLink>

    <div class="mt-1.5 flex flex-wrap gap-1">
      <span
          class="rounded-full px-2 py-0.5 text-[11px] font-medium"
          :class="statusChipClass(task.status)"
      >
        {{ statusLabel(task.status) }}
      </span>
      <span
          class="rounded-full px-2 py-0.5 text-[11px] font-medium"
          :class="priorityChipClass(task.priority)"
      >
        {{ priorityLabel(task.priority) }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Task } from '~/types/task'
import { priorityChipClass, priorityLabel, statusChipClass, statusLabel } from '~/utils/taskDisplay'

defineProps<{
  task: Task
}>()
</script>
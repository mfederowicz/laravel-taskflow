<template>
  <div>
    <div class="grid grid-cols-7 text-center text-xs font-semibold uppercase tracking-wide text-gray-400">
      <div v-for="weekday in WEEKDAYS" :key="weekday">
        {{ weekday }}
      </div>
    </div>

    <div class="mt-1 grid grid-cols-7 gap-1">
      <div
          v-for="(day, index) in cells"
          :key="index"
          class="flex flex-col rounded-lg border px-1.5 py-1.5 transition"
          :class="cellClasses(day)"
          @click="selectDay(day)"
      >
        <template v-if="day !== null">
          <span class="self-start text-xs font-medium" :class="dayNumberClass(day)">
            {{ day }}
          </span>

          <template v-if="compact">
            <div v-if="dayTasks(day).length" class="mt-1 flex gap-0.5">
              <span
                  v-for="(task, dotIndex) in dayTasks(day).slice(0, 5)"
                  :key="task.id"
                  class="inline-block h-1.5 w-1.5 rounded-full"
                  :class="statusDotClass(task)"
                  :title="task.title"
              ></span>
              <span v-if="dayTasks(day).length > 5" class="text-[10px] leading-4 text-gray-400">
                +{{ dayTasks(day).length - 5 }}
              </span>
            </div>
          </template>

          <template v-else>
            <div v-if="dayTasks(day).length" class="mt-1 flex min-w-0 flex-col gap-0.5">
              <NuxtLink
                  v-for="task in dayTasks(day).slice(0, 3)"
                  :key="task.id"
                  :to="`/tasks/${task.id}`"
                  class="truncate rounded px-1 py-0.5 text-[11px] font-medium leading-4"
                  :class="statusChipClass(task.status)"
                  @click.stop
              >
                {{ task.title }}
              </NuxtLink>
              <span v-if="dayTasks(day).length > 3" class="px-1 text-[11px] leading-4 text-gray-400">
                +{{ dayTasks(day).length - 3 }} more
              </span>
            </div>
          </template>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Task } from '~/types/task'
import { buildMonthGrid, isToday, isoDate, WEEKDAYS } from '~/utils/calendar'
import { statusChipClass } from '~/utils/taskDisplay'

const props = defineProps<{
  year: number
  month: number
  tasks: Task[]
  selectedDate: string
  compact?: boolean
}>()

const emit = defineEmits<{ select: [date: string] }>()

const cells = computed<(number | null)[]>(() => buildMonthGrid(props.year, props.month))

const tasksByDate = computed(() => {
  const map = new Map<string, Task[]>()

  for (const task of props.tasks) {
    if (!task.due_date) {
      continue
    }

    const list = map.get(task.due_date)

    if (list) {
      list.push(task)
    } else {
      map.set(task.due_date, [task])
    }
  }

  return map
})

function dayTasks(day: number): Task[] {
  return tasksByDate.value.get(isoDate(props.year, props.month, day)) ?? []
}

function selectDay(day: number | null) {
  if (day === null) {
    return
  }

  emit('select', isoDate(props.year, props.month, day))
}

function cellClasses(day: number | null): string {
  if (day === null) {
    return 'border-transparent'
  }

  const date = isoDate(props.year, props.month, day)
  const base = props.compact ? 'min-h-12 cursor-pointer hover:bg-gray-50' : 'min-h-20 cursor-pointer hover:bg-gray-50'

  if (date === props.selectedDate) {
    return `${base} border-blue-500 bg-blue-50 ring-1 ring-blue-500`
  }

  if (isToday(date)) {
    return `${base} border-blue-200 bg-blue-50/50`
  }

  return `${base} border-gray-100`
}

function dayNumberClass(day: number): string {
  if (isoDate(props.year, props.month, day) === props.selectedDate) {
    return 'text-blue-700'
  }

  if (isToday(isoDate(props.year, props.month, day))) {
    return 'text-blue-600'
  }

  return 'text-gray-700'
}

function statusDotClass(task: Task): string {
  if (task.status === 'completed') {
    return 'bg-green-500'
  }

  if (task.status === 'in_progress') {
    return 'bg-blue-500'
  }

  return 'bg-gray-400'
}
</script>
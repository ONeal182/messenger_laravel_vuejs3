<script setup>
import { computed } from 'vue'

const props = defineProps({
  user: { type: Object, default: null },
})
const emit = defineEmits(['close'])

const fullName = computed(() => {
  if (!props.user) return ''
  const full = [props.user.name, props.user.last_name].filter(Boolean).join(' ').trim()
  if (props.user.name && props.user.last_name) return full
  if (props.user.name) return props.user.name
  if (props.user.nickname) return props.user.nickname
  return props.user.email || ''
})

const birthDate = computed(() => {
  if (!props.user?.birth_date) return ''
  const d = new Date(props.user.birth_date)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
})
</script>

<template>
  <div class="flex-1 min-h-0 bg-[#0f1c3a] text-[#e7efff] flex flex-col">
    <div class="px-6 py-4 border-b border-[#1b2d55] flex items-center bg-[#0d1a35]">
      <button class="text-[#90a7ce] hover:text-[#e7efff]" @click="emit('close')">← Назад</button>
    </div>

    <div class="flex-1 overflow-y-auto p-6">
      <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-col items-center gap-3">
          <div class="h-32 w-32 rounded-full overflow-hidden bg-gradient-to-br from-[#53d3ff] to-[#a66bff] flex items-center justify-center text-3xl font-semibold text-[#0d1a35]">
            <img v-if="user?.avatar_path || user?.avatar_thumb_path" :src="user.avatar_thumb_path || user.avatar_path" alt="avatar" class="h-full w-full object-cover" />
            <span v-else>{{ (fullName || '?').slice(0,1).toUpperCase() }}</span>
          </div>
          <div class="text-center">
            <h3 class="text-2xl font-semibold">{{ fullName }}</h3>
            <p class="text-sm text-[#90a7ce]">{{ user?.nickname ? '@'+user.nickname : '' }}</p>
          </div>
        </div>

        <div class="bg-[#0d1f41] border border-[#1b2d55] rounded-xl p-4 space-y-2">
          <div class="text-xs uppercase tracking-[0.12em] text-[#90a7ce]">Данные</div>
          <div class="flex items-center justify-between py-2 border-b border-[#1b2d55]">
            <span class="text-sm text-[#90a7ce]">Имя</span>
            <span class="text-sm text-[#e7efff]">{{ fullName }}</span>
          </div>
          <div class="flex items-center justify-between py-2 border-b border-[#1b2d55]">
            <span class="text-sm text-[#90a7ce]">Ник</span>
            <span class="text-sm text-[#e7efff]">{{ user?.nickname ? '@'+user.nickname : '—' }}</span>
          </div>
          <div class="flex items-center justify-between py-2">
            <span class="text-sm text-[#90a7ce]">Дата рождения</span>
            <span class="text-sm text-[#e7efff]">{{ birthDate || '—' }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

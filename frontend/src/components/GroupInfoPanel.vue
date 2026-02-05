<script setup>
import { computed } from 'vue'

const props = defineProps({
  chat: { type: Object, default: null },
})
const emit = defineEmits(['close'])

const participants = computed(() => props.chat?.users || [])
const onlineCount = computed(() => {
  const now = Date.now()
  return participants.value.filter((u) => {
    if (!u.last_seen_at) return false
    return new Date(u.last_seen_at).getTime() > now - 5 * 60 * 1000
  }).length
})

function isOnline(u) {
  if (!u?.last_seen_at) return false
  return new Date(u.last_seen_at).getTime() > Date.now() - 5 * 60 * 1000
}

function displayName(u) {
  if (!u) return ''
  const full = [u.name, u.last_name].filter(Boolean).join(' ').trim()
  if (u.name && u.last_name) return full
  if (u.name) return u.name
  if (u.nickname) return u.nickname
  return u.email || ''
}
</script>

<template>
  <div class="flex-1 min-h-0 bg-[#0f1c3a] text-[#e7efff] flex flex-col">
    <div class="px-6 py-4 border-b border-[#1b2d55] flex items-center justify-between bg-[#0d1a35]">
      <div>
        <p class="text-xs uppercase tracking-[0.12em] text-[#90a7ce]">Информация</p>
        <h2 class="text-xl font-semibold truncate max-w-[320px]">
          {{ chat?.title || chat?.name || 'Группа' }}
        </h2>
        <p class="text-sm text-[#90a7ce]">
          {{ participants.length }} участник{{ participants.length === 1 ? '' : 'ов' }} · {{ onlineCount }} онлайн
        </p>
      </div>
      <button class="text-[#90a7ce] hover:text-[#e7efff]" @click="emit('close')">✕</button>
    </div>

    <div class="flex-1 overflow-y-auto p-6">
      <div class="space-y-4 max-w-4xl">
        <div class="rounded-xl border border-[#1b2d55] bg-[#0d1f41]">
          <div class="px-4 py-3 border-b border-[#1b2d55] text-sm uppercase tracking-[0.1em] text-[#90a7ce]">
            Участники
          </div>
          <div class="divide-y divide-[#1b2d55]">
            <div
              v-for="u in participants"
              :key="u.id"
              class="flex items-center gap-3 px-4 py-3"
            >
              <div class="h-10 w-10 rounded-full bg-[#1a3561] flex items-center justify-center overflow-hidden text-sm font-semibold text-[#e7efff]">
                <img v-if="u.avatar_thumb_path || u.avatar_path" :src="u.avatar_thumb_path || u.avatar_path" alt="avatar" class="h-full w-full object-cover" />
                <span v-else>{{ (u.nickname || u.name || '?').slice(0,1).toUpperCase() }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-[#e7efff] font-semibold truncate">{{ displayName(u) }}</p>
                <p class="text-xs text-[#90a7ce] truncate">
                  {{ u.nickname ? '@'+u.nickname : (u.email || '') }}
                </p>
              </div>
              <div class="text-xs text-[#90a7ce]">
                <span v-if="isOnline(u)" class="text-emerald-400">online</span>
                <span v-else>offline</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  show: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'open-profile'])

const auth = useAuthStore()

const displayName = computed(() => {
  const u = auth.user
  if (!u) return ''
  const full = [u.name, u.last_name].filter(Boolean).join(' ').trim()
  if (u.name && u.last_name) return full
  if (u.name) return u.name
  if (u.nickname) return u.nickname
  return u.email || ''
})
</script>

<template>
  <transition name="drawer-slide">
    <div
      v-if="show"
      class="fixed inset-0 z-40 pointer-events-none"
    >
      <div class="absolute inset-0 bg-black/30 backdrop-blur-sm pointer-events-auto" @click="emit('close')"></div>
      <div
        class="absolute left-0 top-0 h-full w-full sm:w-[320px] lg:w-[28%] bg-[#0d1a35] border-r border-slate-800/60 shadow-2xl text-[#e7efff] flex flex-col z-50 transform transition-transform duration-300 pointer-events-auto"
        :class="show ? 'translate-x-0' : '-translate-x-full'"
      >
        <div class="flex items-center justify-between px-5 py-4 border-b border-[#1b2d55]">
          <div class="min-w-0">
            <p class="text-xs uppercase tracking-[0.14em] text-[#90a7ce]">Настройки</p>
            <p class="text-lg font-semibold truncate">{{ displayName || 'Профиль' }}</p>
          </div>
          <button class="text-[#90a7ce] hover:text-[#e7efff]" @click="emit('close')">✕</button>
        </div>

        <div class="flex-1 p-5 space-y-3 overflow-y-auto">
          <button
            class="w-full text-left px-4 py-3 rounded-xl bg-[#13294b] border border-[#1b2d55] hover:bg-[#1a3561] transition"
            @click="emit('open-profile')"
          >
            Профиль
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.drawer-slide-enter-from,
.drawer-slide-leave-to {
  transform: translateX(-100%);
}
.drawer-slide-enter-active,
.drawer-slide-leave-active {
  transition: transform 0.3s ease;
}
.drawer-slide-enter-to,
.drawer-slide-leave-from {
  transform: translateX(0);
}
</style>

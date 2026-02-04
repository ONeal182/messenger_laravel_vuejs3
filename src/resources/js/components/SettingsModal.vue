<script setup>
import { ref } from 'vue'
import SettingsContent from './SettingsContent.vue'
import { watch } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
})
const emit = defineEmits(['close'])

const activeItem = ref(null)

const menu = [
  { key: 'profile', label: 'Профиль' },
]

function selectItem(key) {
  activeItem.value = key
}

watch(
  () => props.show,
  (val) => {
    if (val === true) {
      activeItem.value = null
    }
  }
)
</script>

<template>
  <transition name="drawer-slide">
    <div v-if="show" class="fixed inset-0 z-40 flex">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="emit('close')"></div>
      <div class="relative flex-1 flex z-50">
        <!-- Left menu -->
        <div
          class="w-[300px] max-w-[320px] h-full bg-[#0d1a35] border-r border-slate-800/60 shadow-2xl text-[#e7efff] flex flex-col transform transition-transform duration-300"
          :class="show ? 'translate-x-0' : '-translate-x-full'"
        >
          <div class="flex items-center justify-between px-5 py-4 border-b border-[#1b2d55]">
            <div class="min-w-0">
              <p class="text-xs uppercase tracking-[0.14em] text-[#90a7ce]">Настройки</p>
              <p class="text-lg font-semibold truncate">Messenger</p>
            </div>
            <button class="text-[#90a7ce] hover:text-[#e7efff]" @click="emit('close')">✕</button>
          </div>
          <div class="flex-1 overflow-y-auto p-4 space-y-2">
            <button
              v-for="item in menu"
              :key="item.key"
              class="w-full text-left px-4 py-3 rounded-xl border transition"
              :class="activeItem === item.key ? 'bg-[#1a3561] border-[#1b2d55]' : 'bg-[#13294b] border-[#1b2d55] hover:bg-[#1a3561]'"
              @click="selectItem(item.key)"
            >
              {{ item.label }}
            </button>
          </div>
        </div>

        <!-- Right content -->
        <div class="flex-1 min-w-0 h-full bg-[#0f1c3a]/90 backdrop-blur flex items-center justify-center">
          <SettingsContent v-if="activeItem === 'profile'" />
          <div v-else class="text-center text-[#90a7ce] text-sm">
            Выберите раздел в меню слева
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.drawer-slide-enter-from,
.drawer-slide-leave-to {
  opacity: 0;
}
.drawer-slide-enter-active,
.drawer-slide-leave-active {
  transition: opacity 0.25s ease;
}
.drawer-slide-enter-to,
.drawer-slide-leave-from {
  opacity: 1;
}
</style>

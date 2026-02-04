<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useChatsStore } from '@/stores/chats'
import { useAuthStore } from '@/stores/auth'
import * as messageApi from '@/services/messageApi'

const props = defineProps({
  message: { type: Object, required: true },
  isOwn: { type: Boolean, default: false },
  isRead: { type: Boolean, default: false },
  chatType: { type: String, default: '' },
})

const store = useChatsStore()
const auth = useAuthStore()
const showMenu = ref(false)
const showConfirm = ref(false)
const deleteForAll = ref(false)
const showForward = ref(false)
const forwardSearch = ref('')

const timeLabel = computed(() => {
  if (!props.message?.created_at) return ''
  const date = new Date(props.message.created_at)
  const day = String(date.getDate()).padStart(2, '0')
  const mon = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  const hour = String(date.getHours()).padStart(2, '0')
  const min = String(date.getMinutes()).padStart(2, '0')
  return `${day}.${mon}.${year} ${hour}:${min}`
})

const isDeleted = computed(() => !!props.message?.deleted_for_all_at)

const filteredChats = computed(() => {
  const term = forwardSearch.value.toLowerCase()
  return store.chats.filter((c) => {
    if (!term) return true
    return (
      c.title?.toLowerCase().includes(term) ||
      c.name?.toLowerCase().includes(term) ||
      c.users?.some((u) => u.nickname?.toLowerCase().includes(term) || u.email?.toLowerCase().includes(term))
    )
  })
})

const status = computed(() => {
  if (!props.isOwn) return 'none'
  if (props.message?.pending) return 'pending'
  if (props.message?.failed) return 'failed'
  if (props.isRead) return 'read'
  return 'sent'
})

const forwardTitle = computed(() => {
  return formatUser(props.message?.forward_from_user)
})

const senderAvatar = computed(() => {
  // avatar показываем только в групповых чатах
  const type = props.chatType || props.message?.chat_type
  if (type !== 'group') return null
  return props.message?.sender?.avatar_thumb_path || props.message?.sender?.avatar_path || null
})

function formatUser(user) {
  if (!user) return ''
  const full = [user.name, user.last_name].filter(Boolean).join(' ').trim()
  if (user.name && user.last_name) return full
  if (user.name) return user.name
  if (user.nickname) return user.nickname
  return user.email || ''
}

function onContextMenu(e) {
  e.preventDefault()
  if (!props.message?.id) return
  showMenu.value = true
}

function closeMenu() {
  showMenu.value = false
}

function askDelete() {
  showMenu.value = false
  showConfirm.value = true
  deleteForAll.value = false
}

function startForward() {
  showMenu.value = false
  showForward.value = true
  forwardSearch.value = ''
}

async function confirmDelete() {
  try {
    if (deleteForAll.value && props.isOwn) {
      await messageApi.deleteForAll(props.message.id)
      store.markMessageDeleted(store.activeChat?.id, props.message.id)
      await store.fetchChats()
    } else {
      await messageApi.deleteForMe(props.message.id)
      store.removeMessage(store.activeChat?.id, props.message.id)
      await store.fetchChats()
    }
  } catch (e) {
    console.warn('Delete message failed', e)
  } finally {
    showConfirm.value = false
    deleteForAll.value = false
  }
}

async function forwardToChat(chatId) {
  try {
    const { data } = await messageApi.forwardMessage(props.message.id, chatId)
    await store.fetchChats()
    if (store.activeChat?.id === chatId) {
      store.addMessage(data.message)
    }
  } catch (e) {
    console.warn('Forward failed', e)
  } finally {
    showForward.value = false
  }
}

function handleOutsideClick(e) {
  if (!(e.target.closest && e.target.closest('[data-message-menu]'))) {
    showMenu.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
})

</script>

<template>
  <div class="flex gap-2" :class="isOwn ? 'justify-end' : 'justify-start'">
    <div v-if="!isOwn && senderAvatar" class="h-8 w-8 rounded-full overflow-hidden bg-[#1a3561] flex-shrink-0">
      <img :src="senderAvatar" alt="avatar" class="h-full w-full object-cover" />
    </div>
    <div
      class="relative max-w-[70%] rounded-2xl px-4 py-2.5 text-sm shadow-md border border-transparent"
      :class="[
        isOwn
          ? 'bg-gradient-to-br from-[#53d3ff] to-[#a66bff] text-[#0d1a35]'
          : 'bg-[#13294b] text-[#e7efff] border-[#1b2d55]',
        isOwn ? 'ml-auto' : ''
      ]"
      @contextmenu="onContextMenu"
      data-message-menu
    >
      <div
        class="text-[11px] opacity-80 mb-1 font-semibold"
        :class="isOwn ? 'text-[#0d1a35]' : 'text-[#e7efff]'"
      >
        {{ formatUser(message.sender) || 'You' }}
      </div>

      <div
        v-if="forwardTitle"
        class="text-[11px] mb-1"
        :class="isOwn ? 'text-[#0d1a35]/85' : 'text-[#d1dcf5]/80'"
      >
        Переслано от: <span class="font-semibold">{{ forwardTitle }}</span>
      </div>

      <div class="leading-relaxed" :class="isOwn ? 'text-[#0d1a35]' : 'text-[#e7efff]'">
        {{ message.body }}
      </div>

      <div
        class="mt-2 flex items-center gap-2 text-[11px]"
        :class="isOwn ? 'justify-end text-[#0d1a35]/80' : 'justify-start text-[#90a7ce]'"
      >
        <span>{{ timeLabel }}</span>

        <span v-if="isOwn" class="flex items-center gap-1">
          <svg
            v-if="status === 'pending'"
            class="w-4 h-4 text-slate-500"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="12" cy="12" r="9" opacity="0.35" />
            <path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <svg
            v-else-if="status === 'failed'"
            class="w-4 h-4 text-red-500"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="M6 6l12 12M6 18 18 6" stroke-linecap="round" />
          </svg>
          <svg
            v-else-if="status === 'read'"
            class="w-5 h-5 text-[#0d1a35]"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.3"
          >
            <path d="m5 13 4 4 10-12" stroke-linecap="round" stroke-linejoin="round" />
            <path d="m9 13 4 4 6-9" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <svg
            v-else
            class="w-5 h-5 text-[#90a7ce]"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.3"
          >
            <path d="m5 13 4 4 10-12" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
      </div>

      <div
        v-if="showMenu && !isDeleted"
        class="absolute right-2 top-2 bg-[#0f1c3a] border border-[#1b2d55] shadow-lg shadow-[#53d3ff]/15 rounded-xl py-1 w-40 z-10"
      >
        <button
          class="w-full text-left px-4 py-2 hover:bg-[#13294b] text-sm text-[#e7efff]"
          @click.stop="startForward"
        >
          Переслать
        </button>
        <button
          class="w-full text-left px-4 py-2 hover:bg-[#13294b] text-sm text-[#e7efff]"
          @click.stop="askDelete"
        >
          Удалить
        </button>
      </div>
    </div>
  </div>

  <!-- Forward modal -->
  <div
    v-if="showForward"
    class="fixed inset-0 bg-black/40 flex items-center justify-center z-30"
  >
    <div class="bg-[#0f1c3a] border border-[#1b2d55] rounded-2xl shadow-2xl shadow-[#53d3ff]/20 w-[380px] max-h-[70vh] p-5 flex flex-col gap-3 text-[#e7efff]">
      <div class="flex items-center justify-between">
        <p class="text-base font-semibold">Переслать сообщение</p>
        <button class="text-[#90a7ce]" @click="showForward = false">✕</button>
      </div>
      <input
        v-model="forwardSearch"
        type="text"
        class="w-full border border-[#1b2d55] rounded-lg px-3 py-2 bg-[#13294b] text-[#e7efff] placeholder-[#90a7ce] focus:ring-2 focus:ring-[#53d3ff]/40 focus:border-[#53d3ff]"
        placeholder="Поиск чата"
      />
      <div class="flex-1 overflow-y-auto divide-y divide-[#1b2d55] border border-[#1b2d55] rounded-lg">
        <button
          v-for="chat in filteredChats"
          :key="chat.id"
          class="w-full text-left px-3 py-3 hover:bg-[#13294b] flex items-center gap-3"
          @click="forwardToChat(chat.id)"
        >
          <div class="h-9 w-9 rounded-full bg-[#1a3561] flex items-center justify-center text-xs font-semibold uppercase text-[#e7efff]">
            {{ (chat.title || chat.name || chat.users?.[0]?.nickname || '?').slice(0,1) }}
          </div>
          <div class="min-w-0">
            <div class="text-sm font-semibold truncate">
              {{ chat.title || chat.name || chat.users?.[0]?.nickname || 'Чат' }}
            </div>
            <div class="text-xs text-[#90a7ce] truncate">
              {{ chat.last_message?.body || '' }}
            </div>
          </div>
        </button>
      </div>
    </div>
  </div>

  <!-- Confirm modal -->
  <div
    v-if="showConfirm"
    class="fixed inset-0 bg-black/40 flex items-center justify-center z-30"
  >
    <div class="bg-[#0f1c3a] border border-[#1b2d55] rounded-2xl shadow-2xl shadow-[#53d3ff]/20 w-[320px] p-5 space-y-4 text-[#e7efff]">
      <p class="text-base font-semibold">Удалить сообщение?</p>

      <label class="flex items-center gap-2 text-sm text-[#e7efff]">
        <input
          type="checkbox"
          v-model="deleteForAll"
          :disabled="!isOwn"
          class="h-4 w-4 rounded border-[#1b2d55] text-[#53d3ff] focus:ring-[#53d3ff] disabled:opacity-40 bg-[#13294b]"
        />
        <span>
          Удалить для всех
          <span v-if="!isOwn" class="text-xs text-[#90a7ce]">(доступно только для своих сообщений)</span>
        </span>
      </label>

      <div class="flex justify-end gap-3">
        <button
          class="px-4 py-2 rounded-lg bg-[#13294b] text-[#e7efff] border border-[#1b2d55]"
          @click="showConfirm = false"
        >
          Отмена
        </button>
        <button
          class="px-4 py-2 rounded-lg bg-red-500 text-white"
          @click="confirmDelete"
        >
          Удалить
        </button>
      </div>
    </div>
  </div>
</template>

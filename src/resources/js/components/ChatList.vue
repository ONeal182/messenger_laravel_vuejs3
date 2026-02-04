<script setup>
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import { useChatsStore } from '@/stores/chats'
import { useAuthStore } from '@/stores/auth'
import { subscribeToChat } from '@/services/chatChannels'
import { searchUsers } from '@/services/userApi'
import { createGroup } from '@/services/chatApi'

const store = useChatsStore()
const auth = useAuthStore()
const emit = defineEmits(['open-settings'])
const search = ref('')
const unsubscribers = new Map()
const userResults = ref([])
const searchLoading = ref(false)
const searchError = ref('')
let searchTimeout = null
let userChannel = null
const showGroupModal = ref(false)
const groupTitle = ref('')
const groupSearch = ref('')
const groupResults = ref([])
const groupSelected = ref(new Set())
const groupError = ref('')
const groupLoading = ref(false)
let groupTimeout = null

const contactList = computed(() => {
    const users = []
    const seen = new Set()
    store.chats.forEach((chat) => {
        chat.users?.forEach((u) => {
            if (!auth.user || u.id === auth.user.id) return
            if (seen.has(u.id)) return
            seen.add(u.id)
            users.push(u)
        })
    })
    return users
})

const filteredChats = computed(() => store.chats)

function formatUser(u) {
    if (!u) return ''
    const full = [u.name, u.last_name].filter(Boolean).join(' ').trim()
    if (u.name && u.last_name) return full
    if (u.name) return u.name
    if (u.nickname) return u.nickname
    return u.email || ''
}

function chatTitle(chat) {
    if (chat.type === 'private' && chat.users && auth.user) {
        const other = chat.users.find((u) => u.id !== auth.user.id)
        const label = formatUser(other)
        if (label) return label
    }

    return chat.title || chat.name || `Chat #${chat.id}`
}

function initials(chat) {
    const title = chatTitle(chat)
    return title.slice(0, 1).toUpperCase()
}

function chatAvatar(chat) {
    if (chat.type === 'private' && chat.users && auth.user) {
        const other = chat.users.find((u) => u.id !== auth.user.id)
        return other?.avatar_thumb_path || other?.avatar_path || null
    }
    return null
}

function ensureTypingSubscriptions() {
    store.chats.forEach((chat) => {
        if (unsubscribers.has(chat.id)) return
        const unsub = subscribeToChat(chat.id, {
            onTyping: (e) => {
                if (auth.user && e.user?.id === auth.user.id) return
                store.setTyping(chat.id, e.user.name || e.user.email)
            },
            onPresence: (e) => {
                if (auth.user && e.user?.id === auth.user.id) return
                store.updateUserPresence(chat.id, e.user.id, e.last_seen_at)
            },
        })
        unsubscribers.set(chat.id, unsub)
    })

    unsubscribers.forEach((unsub, id) => {
        const exists = store.chats.find((c) => c.id === id)
        if (!exists) {
            unsub()
            unsubscribers.delete(id)
        }
    })
}

watch(
    () => store.chats.map((c) => c.id),
    () => ensureTypingSubscriptions(),
    { immediate: true }
)

watch(
    () => auth.user?.id,
    (userId) => {
        if (!window.Echo || !userId) return
        if (userChannel) {
            window.Echo.leave(`user.${userId}`)
            userChannel = null
        }
        userChannel = window.Echo.private(`user.${userId}`)
        userChannel.listen('.chat.updated', () => {
            store.fetchChats()
        })
    },
    { immediate: true }
)

watch(
    () => search.value,
    (val) => {
        searchError.value = ''
        userResults.value = []
        if (searchTimeout) clearTimeout(searchTimeout)
        const term = val.trim()
        if (!term) return

        searchTimeout = setTimeout(async () => {
            searchLoading.value = true
            try {
                const { data } = await searchUsers(term)
                userResults.value = data.users || []
            } catch (e) {
                searchError.value = 'Не удалось найти пользователей'
            } finally {
                searchLoading.value = false
            }
        }, 300)
    }
)

watch(
    () => groupSearch.value,
    (val) => {
        groupError.value = ''
        groupResults.value = []
        if (groupTimeout) clearTimeout(groupTimeout)
        const term = val.trim()
        if (!term) return

        groupTimeout = setTimeout(async () => {
            groupLoading.value = true
            try {
                const { data } = await searchUsers(term)
                groupResults.value = data.users || []
            } catch (e) {
                groupError.value = 'Не удалось найти пользователей'
            } finally {
                groupLoading.value = false
            }
        }, 300)
    }
)

async function openUser(user) {
    store.openPendingChat(user, auth.user)
    userResults.value = []
    search.value = ''
}

function toggleSelect(user) {
    const set = new Set(groupSelected.value)
    if (set.has(user.nickname)) {
        set.delete(user.nickname)
    } else {
        set.add(user.nickname)
    }
    groupSelected.value = set
}

async function createGroupChat() {
    groupError.value = ''
    if (!groupTitle.value.trim() || groupSelected.value.size === 0) {
        groupError.value = 'Укажите название и выберите пользователей'
        return
    }
    groupLoading.value = true
    try {
        const { data } = await createGroup(groupTitle.value, Array.from(groupSelected.value))
        await store.fetchChats()
        const chat = store.chats.find(c => c.id === data.chat.id) || data.chat
        store.openChat(chat)
        showGroupModal.value = false
        groupSelected.value = new Set()
        groupResults.value = []
        groupSearch.value = ''
        groupTitle.value = ''
    } catch (e) {
        groupError.value = 'Не удалось создать группу'
    } finally {
        groupLoading.value = false
    }
}

onBeforeUnmount(() => {
    unsubscribers.forEach((unsub) => unsub())
    unsubscribers.clear()
    if (userChannel) {
        userChannel.stopListening('.chat.updated')
        window.Echo?.leave(`user.${auth.user?.id}`)
    }
})
</script>

<template>
    <div class="flex-1 flex flex-col p-5 space-y-4 bg-[#0d1a35] text-[#e7efff]">
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="h-10 w-10 rounded-full bg-[#13294b] flex items-center justify-center text-lg font-semibold text-[#e7efff] border border-[#1b2d55]"
                @click="emit('open-settings')"
                title="Меню"
            >
                ☰
            </button>
            <div>
                <p class="text-xs uppercase tracking-[0.15em] text-[#90a7ce]">Messenger</p>
                <h2 class="text-lg font-semibold text-[#e7efff]">Поиск</h2>
            </div>
            <button
                type="button"
                class="ml-auto h-10 w-10 rounded-full bg-[#13294b] border border-slate-800 text-[#e7efff] hover:bg-[#1a3561] shadow-sm"
                title="Создать"
                @click="showGroupModal = true"
            >
                <svg class="w-5 h-5 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-7-7v14" />
                </svg>
            </button>
        </div>

        <div class="mb-4">
            <div class="relative">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Поиск"
                    class="w-full rounded-2xl bg-[#13294b] border border-slate-800 text-[#e7efff] placeholder-[#90a7ce] px-4 py-2.5 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/30"
                />
                <svg
                    class="w-4 h-4 absolute right-4 top-3.5 text-[#90a7ce]"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    viewBox="0 0 24 24"
                >
                    <circle cx="11" cy="11" r="7" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.5 16.5 3 3" />
                </svg>
            </div>
        </div>

        <div v-if="searchLoading" class="text-[#90a7ce] text-sm">Идет поиск…</div>
        <div v-else-if="searchError" class="text-red-400 text-sm">{{ searchError }}</div>

        <div v-if="search && userResults.length" class="space-y-1 overflow-y-auto pr-1">
            <p class="text-xs uppercase tracking-[0.12em] text-[#90a7ce] px-1">Контакты</p>
            <ul class="space-y-2">
                <li
                    v-for="user in userResults"
                    :key="user.id"
                    @click="openUser(user)"
                    class="p-3 rounded-2xl cursor-pointer flex items-center gap-3 transition-all duration-150 hover:bg-[#13294b] border border-slate-800"
                >
                    <div class="h-11 w-11 rounded-full bg-[#1a3561] flex items-center justify-center text-sm font-semibold text-[#e7efff]">
                        {{ (user.nickname || user.name || user.email || '?').slice(0,1).toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[#e7efff] font-semibold truncate">
                            {{ user.nickname || user.name || user.email }}
                        </p>
                    </div>
                </li>
            </ul>
        </div>

        <div v-else-if="search && !userResults.length && !searchLoading && !searchError" class="text-[#90a7ce] text-sm">
            Ничего не найдено
        </div>

        <div v-else-if="store.loading" class="text-[#90a7ce] text-sm">Loading...</div>

        <ul v-else class="space-y-2 overflow-y-auto pr-1">
            <li
                v-for="chat in filteredChats"
                :key="chat.id"
                @click="store.openChat(chat)"
                class="p-3 rounded-2xl cursor-pointer flex items-center gap-3 transition-all duration-150 hover:bg-[#13294b]"
                :class="(store.activeChat && store.activeChat.id === chat.id) ? 'bg-[#1a3561] shadow-sm border border-slate-700' : 'border border-transparent'"
            >
                <div class="h-11 w-11 rounded-full bg-[#1a3561] flex items-center justify-center text-sm font-semibold text-[#e7efff] overflow-hidden">
                    <img
                        v-if="chatAvatar(chat)"
                        :src="chatAvatar(chat)"
                        alt="avatar"
                        class="h-full w-full object-cover"
                    />
                    <span v-else>{{ initials(chat) }}</span>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-[#e7efff] font-semibold truncate">
                            {{ chatTitle(chat) }}
                        </p>
                    </div>
                    <p class="text-sm text-[#90a7ce] truncate">
                        <span v-if="store.typing[chat.id]">
                            {{ store.typing[chat.id].user || 'Someone' }} печатает…
                        </span>
                        <span v-else>
                            {{ chat.last_message?.body || 'No messages yet' }}
                        </span>
                    </p>
                </div>

                <span
                    v-if="chat.unread_count > 0"
                    class="bg-[#53d3ff] text-[#0d1a35] text-xs px-2 py-0.5 rounded-full font-semibold shadow-sm"
                >
                    {{ chat.unread_count }}
                </span>
            </li>
        </ul>

        <div
        v-if="showGroupModal"
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
    >
            <div class="bg-[#0f1c3a] border border-[#1b2d55] rounded-2xl shadow-2xl shadow-[#53d3ff]/20 w-full max-w-3xl p-6 space-y-4 text-[#e7efff]">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Новая группа</h3>
                    <button class="text-[#90a7ce] hover:text-[#e7efff]" @click="showGroupModal = false">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm text-[#90a7ce] mb-1">Название</label>
                        <input
                            v-model="groupTitle"
                            type="text"
                            class="w-full rounded-lg border border-[#1b2d55] bg-[#13294b] text-[#e7efff] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/30"
                            placeholder="Название группы"
                        />
                    </div>

                    <div>
                        <label class="block text-sm text-[#90a7ce] mb-1">Поиск пользователей</label>
                        <input
                            v-model="groupSearch"
                            type="text"
                            class="w-full rounded-lg border border-[#1b2d55] bg-[#13294b] text-[#e7efff] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/30"
                            placeholder="Никнейм"
                        />
                        <p v-if="groupError" class="text-sm text-red-300 mt-1">{{ groupError }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 max-h-80 overflow-y-auto">
                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] text-[#90a7ce] mb-2">Результаты</p>
                            <div v-if="groupLoading" class="text-sm text-[#90a7ce]">Идет поиск…</div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="user in groupResults"
                                    :key="'res-'+user.id"
                                    class="p-2 rounded-lg border border-[#1b2d55] bg-[#13294b] flex items-center gap-2 cursor-pointer hover:bg-[#1a3561]"
                                    @click="toggleSelect(user)"
                                >
                                    <input type="checkbox" :checked="groupSelected.has(user.nickname)" />
                                    <div>
                                        <p class="text-sm font-semibold">{{ user.nickname || user.name || user.email }}</p>
                                        <p class="text-xs text-[#90a7ce]">{{ user.email }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] text-[#90a7ce] mb-2">Контакты</p>
                            <div class="space-y-2">
                                <div
                                    v-for="user in contactList"
                                    :key="'contact-'+user.id"
                                    class="p-2 rounded-lg border border-[#1b2d55] bg-[#13294b] flex items-center gap-2 cursor-pointer hover:bg-[#1a3561]"
                                    @click="toggleSelect(user)"
                                >
                                    <input type="checkbox" :checked="groupSelected.has(user.nickname)" />
                                    <div>
                                        <p class="text-sm font-semibold">{{ user.nickname || user.name || user.email }}</p>
                                        <p class="text-xs text-[#90a7ce]">{{ user.email }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg border border-[#1b2d55] text-[#e7efff] hover:bg-[#13294b]"
                            @click="showGroupModal = false"
                        >
                            Отмена
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg bg-gradient-to-r from-[#53d3ff] to-[#a66bff] text-[#0d1a35] font-semibold disabled:opacity-60"
                            :disabled="groupLoading"
                            @click="createGroupChat"
                        >
                            {{ groupLoading ? 'Создание...' : 'Создать' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

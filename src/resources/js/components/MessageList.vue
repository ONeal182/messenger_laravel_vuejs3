<script setup>
import { computed, watch, ref, onBeforeUnmount, nextTick, onMounted } from 'vue'
import { useChatsStore } from '@/stores/chats'
import { useAuthStore } from '@/stores/auth'
import MessageItem from './MessageItem.vue'
import * as chatApi from '@/services/chatApi'
import { subscribeToChat } from '@/services/chatChannels'
import { formatDateLabel, formatLastSeen } from '@/utils/time'
import { searchUsers } from '@/services/userApi'
import { searchMessages } from '@/services/messageApi'

const store = useChatsStore()
const auth = useAuthStore()
const typingUser = ref(null)
const messagesContainer = ref(null)
let unsubscribeChannel = null
const showMenu = ref(false)
const showAdd = ref(false)
const inviteNickname = ref('')
const inviteError = ref('')
const inviteLoading = ref(false)
const inviteResults = ref([])
const inviteSearchLoading = ref(false)
let inviteSearchTimeout = null
let markReadTimeout = null
const nowTs = ref(Date.now())
let nowInterval = null
let lastMessageObserver = null
const loadingOlder = ref(false)
const pendingInitialScroll = ref(false)
const allowLoadMore = ref(false)
const topSentinel = ref(null)
let topObserver = null
const searchOpen = ref(false)
const searchQuery = ref('')
const searchResults = ref([])
const searchLoading = ref(false)
const searchError = ref('')
const searchContainer = ref(null)
const searchToggle = ref(null)
let searchTimeout = null
const showGroupInfo = ref(false)
const showDownButton = ref(false)
const lastReadRequested = ref(null)
const emit = defineEmits(['open-group-info', 'open-user-profile'])
function tryLoadIfAtTop() {
    const el = messagesContainer.value
    if (!el || !allowLoadMore.value) return
    if (el.scrollTop <= 4 && store.messagesHasMore && !store.messagesLoadingMore) {
        loadOlderMessages()
    }
}
function handleVisibilityChange() {
    if (document.visibilityState === 'visible' && store.activeChat) {
        scheduleMarkRead()
        observeLastMessage()
    }
}

const otherUser = computed(() => {
    if (!store.activeChat?.users || !auth.user) return null
    return store.activeChat.users.find((u) => u.id !== auth.user.id)
})

function displayName(user) {
    if (!user) return ''
    const full = [user.name, user.last_name].filter(Boolean).join(' ').trim()
    if (user.name && user.last_name) return full
    if (user.name) return user.name
    if (user.nickname) return user.nickname
    return user.email || ''
}

const headerTitle = computed(() => {
    if (!store.activeChat) return 'Select a chat'
    if (store.activeChat.type === 'private' && otherUser.value?.email) {
        return displayName(otherUser.value) || otherUser.value.email
    }
    return store.activeChat.title || store.activeChat.name || `Chat #${store.activeChat.id}`
})

const headerAvatar = computed(() => {
    if (store.activeChat?.type === 'private') {
        const u = otherUser.value
        return u?.avatar_thumb_path || u?.avatar_path || null
    }
    return null
})

const statusText = computed(() => {
    if (!store.activeChat) return 'No chat selected'

    // Группы: показать участников и онлайн
    if (store.activeChat.type === 'group') {
        const users = store.activeChat.users || []
        const total = users.length
        const online = users.filter((u) => !!u.last_seen_at && new Date(u.last_seen_at) > Date.now() - 5 * 60 * 1000).length
        return `${total} участников · ${online} онлайн`
    }

    // Приватка
    if (typingUser.value) return `${typingUser.value} is typing…`
    const lastSeen = otherUser.value?.last_seen_at || otherUser.value?.pivot?.last_seen_at
    if (lastSeen) {
        return formatLastSeen(lastSeen)
    }
    return 'offline'
})

const groupedMessages = computed(() => {
    const groups = []
    store.messages.forEach((msg) => {
        const date = msg.created_at ? new Date(msg.created_at) : new Date()
        const key = date.toISOString().slice(0, 10)
        let group = groups.find((g) => g.dateKey === key)
        if (!group) {
            group = { dateKey: key, label: formatDateLabel(date), items: [] }
            groups.push(group)
        }
        group.items.push(msg)
    })
    return groups
})

function isMessageRead(message) {
    // server marks read for outgoing messages as message.read
    if (typeof message.read === 'boolean') {
        return message.read
    }

    if (!auth.user) return false
    if (!store.activeChat?.users) return false
    // only relevant for own messages
    if (!isOwnMessage(message)) return false

    const others = store.activeChat.users.filter((u) => u.id !== auth.user.id)
    if (!others.length) return false

    return others.every((u) => {
        const pivotVal = u.pivot?.last_read_message_id
        return pivotVal && pivotVal >= message.id
    })
}

function isOwnMessage(message) {
    if (!auth.user) return false
    return (
        message.sender?.id === auth.user.id ||
        (message.sender?.email && message.sender.email === auth.user.email)
    )
}

watch(
    () => store.activeChat,
    (chat) => {
        if (!chat) return

        showMenu.value = false
        showAdd.value = false
        inviteNickname.value = ''
        inviteError.value = ''

        if (unsubscribeChannel) {
            unsubscribeChannel()
            unsubscribeChannel = null
        }

        unsubscribeChannel = subscribeToChat(chat.id, {
            onMessage: (e) => {
                if (auth.user && e.message.sender?.id === auth.user.id) {
                    return
                }
                store.addLastMessage(chat.id, e.message)
                if (store.activeChat?.id === chat.id) {
                    store.addMessage(e.message)
                    if (document.visibilityState === 'visible') {
                        scheduleMarkRead(e.message.id)
                    }
                } else {
                    store.incrementUnread(chat.id)
                }
            },
            onTyping: (e) => {
                if (auth.user && e.user?.id === auth.user.id) return
                typingUser.value = e.user.email
                if (store.activeChat) {
                    store.setTyping(chat.id, e.user.name || e.user.email)
                }
                setTimeout(() => {
                    typingUser.value = null
                }, 2000)
            },
            onRead: (e) => {
                if (!auth.user) return
                store.markMessagesRead(
                    chat.id,
                    e.last_read_message_id,
                    e.user.id,
                    auth.user.id
                )
            },
            onPresence: (e) => {
                if (auth.user && e.user?.id === auth.user.id) return
                store.updateUserPresence(chat.id, e.user.id, e.last_seen_at)
            },
            onDelete: (e) => {
                if (e.scope === 'all') {
                    store.markMessageDeleted(e.chat_id, e.message_id)
                    store.fetchChats().catch(() => {})
                }
            },
        })

        pendingInitialScroll.value = true
        allowLoadMore.value = false
        setTimeout(() => {
            allowLoadMore.value = true
        }, 1000)
        tryInitialScroll()
    },
    { immediate: true }
)

watch(allowLoadMore, (val) => {
    if (val) {
        nextTick(() => {
            observeTop()
            tryLoadIfAtTop()
        })
    }
})

watch(pendingInitialScroll, (val) => {
    if (!val) {
        nextTick(() => {
            observeTop()
            tryLoadIfAtTop()
        })
    }
})

watch(searchQuery, (val) => {
    searchError.value = ''
    searchResults.value = []
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }
    const term = val.trim()
    if (!term || term.length < 2 || !store.activeChat?.id) return
    searchTimeout = setTimeout(runSearch, 300)
})

function scrollToBottom() {
    const el = messagesContainer.value
    if (!el) return
    el.scrollTop = el.scrollHeight
    showDownButton.value = false
}

watch(
    () => [store.activeChat?.id, store.messages.length, store._shouldScrollBottomSignal],
    () =>
        nextTick(() => {
            observeLastMessage()
            observeTop()
            tryInitialScroll()
        }),
    { flush: 'post' }
)

watch(
    () => store.activeChat?.id,
    () => {
        if (store.activeChat) {
            showDownButton.value = false
            scheduleMarkRead()
            observeLastMessage()
            tryInitialScroll()
        }
    }
)

// стартовый скролл после загрузки сообщений
watch(
    () => store.messages.length,
    () => {
        nextTick(() => {
            observeTop()
            tryInitialScroll()
        })
    }
)

onBeforeUnmount(() => {
    if (unsubscribeChannel) {
        unsubscribeChannel()
    }
    window.removeEventListener('focus', handleFocus)
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    if (nowInterval) {
        clearInterval(nowInterval)
        nowInterval = null
    }
    if (lastMessageObserver) {
        lastMessageObserver.disconnect()
    }
    if (topObserver) {
        topObserver.disconnect()
    }
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }
    document.removeEventListener('click', handleOutsideSearchClick)
})

async function addUser() {
    inviteError.value = ''
    if (!inviteNickname.value || !store.activeChat) return
    inviteLoading.value = true
    try {
        const { data } = await chatApi.addUser(
            store.activeChat.id,
            inviteNickname.value.replace(/^@/, '')
        )
        store.activeChat = data.chat
        store.addMessage({
            id: Date.now(),
            body: `Пользователь ${inviteNickname.value} добавлен в чат`,
            sender: { email: 'system' },
        })
        inviteNickname.value = ''
        showAdd.value = false
    } catch (e) {
        inviteError.value = e?.response?.data?.message || 'Не удалось добавить пользователя'
    } finally {
        inviteLoading.value = false
    }
}

watch(
    inviteNickname,
    (val) => {
        inviteResults.value = []
        if (inviteSearchTimeout) {
            clearTimeout(inviteSearchTimeout)
        }
        if (!val || val.length < 2) return
        inviteSearchTimeout = setTimeout(fetchInviteSuggestions, 220)
    }
)

async function fetchInviteSuggestions() {
    if (!inviteNickname.value) return
    inviteSearchLoading.value = true
    try {
        const term = inviteNickname.value.replace(/^@/, '')
        const { data } = await searchUsers(term, 5)
        const existingIds = (store.activeChat?.users || []).map((u) => u.id)
        inviteResults.value = (data.users || []).filter((u) => !existingIds.includes(u.id))
    } catch (e) {
        inviteResults.value = []
    } finally {
        inviteSearchLoading.value = false
    }
}

function handleFocus() {
    if (store.activeChat) {
        scheduleMarkRead()
        observeLastMessage()
    }
}

function startAddUser() {
    if (store.activeChat?.type === 'private') return
    showMenu.value = false
    showAdd.value = true
    inviteNickname.value = ''
    inviteError.value = ''
    inviteResults.value = []
}

async function deleteChat() {
    if (!store.activeChat?.id) return
    showMenu.value = false
    const ok = window.confirm('Удалить этот чат?')
    if (!ok) return
    try {
        await chatApi.deleteChat(store.activeChat.id)
        store.removeChat(store.activeChat.id)
    } catch (e) {
        console.warn('deleteChat failed', e)
    }
}

onMounted(() => {
    window.addEventListener('focus', handleFocus)
    document.addEventListener('visibilitychange', handleVisibilityChange)
    nowInterval = setInterval(() => {
        nowTs.value = Date.now()
    }, 10000)
    nextTick(() => observeTop())
    document.addEventListener('click', handleOutsideSearchClick)
})

function scheduleMarkRead(messageId = null) {
    if (!store.activeChat) return
    const targetId = messageId || (store.messages[store.messages.length - 1]?.id)
    if (!targetId) return
    if (lastReadRequested.value && lastReadRequested.value >= targetId) return

    // сразу чистим локальный badge
    store.clearUnread(store.activeChat.id)
    lastReadRequested.value = targetId
    if (markReadTimeout) {
        clearTimeout(markReadTimeout)
    }
    markReadTimeout = setTimeout(async () => {
        try {
            await chatApi.markRead(store.activeChat.id, targetId)
        } catch (e) {
            console.warn('markRead failed', e)
            // позволим повторить позже
            lastReadRequested.value = null
        }
    }, 150)
}

function observeLastMessage() {
    if (lastMessageObserver) {
        lastMessageObserver.disconnect()
    }
    const container = messagesContainer.value
    if (!container) return
    const last = container.querySelector('[data-message]:last-child')
    if (!last) return

    lastMessageObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && store.activeChat?.id) {
                    scheduleMarkRead()
                }
            })
        },
        {
            root: container,
            threshold: 0.7,
        }
    )
    lastMessageObserver.observe(last)
}

function observeTop() {
    if (topObserver) {
        topObserver.disconnect()
    }
    const container = messagesContainer.value
    const sentinel = topSentinel.value
    if (!container || !sentinel) return
    topObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && allowLoadMore.value && store.messagesHasMore && !store.messagesLoadingMore) {
                    loadOlderMessages()
                }
            })
        },
        { root: container, threshold: 0.9 }
    )
    topObserver.observe(sentinel)
}

async function runSearch() {
    if (!store.activeChat?.id) return
    searchLoading.value = true
    try {
        const { data } = await searchMessages(store.activeChat.id, searchQuery.value.trim(), 30)
        searchResults.value = data.messages || []
    } catch (e) {
        searchError.value = 'Не удалось найти сообщения'
        searchResults.value = []
    } finally {
        searchLoading.value = false
    }
}

async function scrollToMessage(messageId) {
    if (!messageId) return
    let target = store.messages.find((m) => m.id === messageId)
    if (!target && store.messagesHasMore) {
        store.allowLoadMore = true
        while (!target && store.messagesHasMore && !store.messagesLoadingMore) {
            await store.loadMoreMessages(store.activeChat.id)
            target = store.messages.find((m) => m.id === messageId)
        }
    }

    await nextTick()
    const el = messagesContainer.value?.querySelector(`[data-message-id=\"${messageId}\"]`)
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        searchOpen.value = false
    }
}

function toggleSearch() {
    searchOpen.value = !searchOpen.value
    if (!searchOpen.value) {
        searchQuery.value = ''
        searchResults.value = []
        searchError.value = ''
    }
}

function handleOutsideSearchClick(e) {
    if (!searchOpen.value) return
    const target = e.target
    if (searchToggle.value && searchToggle.value.contains(target)) {
        return
    }
    if (searchContainer.value && !searchContainer.value.contains(target)) {
        searchOpen.value = false
        searchQuery.value = ''
        searchResults.value = []
        searchError.value = ''
    }
}

function handleHeaderClick() {
    if (!store.activeChat) return
    if (store.activeChat.type === 'group') {
        showGroupInfo.value = true
        emit('open-group-info')
    } else if (store.activeChat.type === 'private') {
        emit('open-user-profile')
    }
}

async function loadOlderMessages() {
    if (!store.activeChat || loadingOlder.value || !store.messagesHasMore || !allowLoadMore.value) return
    loadingOlder.value = true
    await store.loadMoreMessages(store.activeChat.id)
    await nextTick(() => {
        observeLastMessage()
    })
    loadingOlder.value = false
}

function onScroll() {
    const el = messagesContainer.value
    if (!el) return
    if (pendingInitialScroll.value) return
    if (el.scrollTop <= 140 && store.messagesHasMore && !store.messagesLoadingMore) {
        loadOlderMessages()
    }
    const distanceFromBottom = el.scrollHeight - el.clientHeight - el.scrollTop
    showDownButton.value = distanceFromBottom > 180
}

function tryInitialScroll(attempt = 0) {
    if (!pendingInitialScroll.value) return
    const el = messagesContainer.value
    if (!el) return

    const canScroll = el.scrollHeight > el.clientHeight
    if (canScroll || attempt >= 10) {
        scrollToBottom()
        pendingInitialScroll.value = false
        return
    }

    // подождём, пока контент дорисуется, и попробуем снова
    setTimeout(() => tryInitialScroll(attempt + 1), 50)
}
</script>

<template>
    <div class="flex-1 min-h-0 flex flex-col">
        <header class="flex items-center gap-3 px-6 py-4 border-b border-[#1b2d55] bg-[#0f1c3a]/80 backdrop-blur relative">
            <div
                class="h-10 w-10 rounded-full bg-gradient-to-br from-[#53d3ff] to-[#a66bff] flex items-center justify-center text-white font-semibold shadow-lg shadow-[#53d3ff]/20 overflow-hidden"
                :class="store.activeChat?.type === 'group' || store.activeChat?.type === 'private' ? 'cursor-pointer' : ''"
                @click="handleHeaderClick"
            >
                <img v-if="headerAvatar" :src="headerAvatar" alt="avatar" class="h-full w-full object-cover" />
                <span v-else>{{ headerTitle.slice(0, 1).toUpperCase() }}</span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <p
                        class="font-semibold text-[#e7efff] truncate"
                        :class="store.activeChat?.type ? 'cursor-pointer' : ''"
                        @click="handleHeaderClick"
                    >
                        {{ headerTitle }}
                    </p>
                </div>
                <p class="text-sm text-[#90a7ce] truncate">{{ statusText }}</p>
            </div>

            <div v-if="store.activeChat?.id" class="flex items-center gap-2">
                <button
                    ref="searchToggle"
                    class="h-9 w-9 rounded-full flex items-center justify-center text-[#e7efff] hover:bg-[#13294b] border border-[#1b2d55] bg-[#13294b]"
                    @click="toggleSearch"
                    title="Поиск по сообщениям"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="11" cy="11" r="6" />
                        <path d="m16 16 4 4" stroke-linecap="round" />
                    </svg>
                </button>
                <div class="relative">
                    <button
                        class="h-9 w-9 rounded-full flex items-center justify-center text-[#e7efff] hover:bg-[#13294b] border border-[#1b2d55] bg-[#13294b]"
                        @click="showMenu = !showMenu"
                    >
                        ⋮
                    </button>
                    <div
                        v-if="showMenu"
                        class="absolute right-0 mt-2 w-48 bg-[#0f1c3a] border border-[#1b2d55] rounded-xl shadow-lg shadow-[#53d3ff]/10 z-20 py-1"
                    >
                        <button
                            v-if="store.activeChat?.type === 'group'"
                            class="w-full text-left px-4 py-2 text-sm text-[#e7efff] hover:bg-[#13294b]"
                            @click="startAddUser"
                        >
                            Добавить пользователя
                        </button>
                        <button
                            class="w-full text-left px-4 py-2 text-sm text-red-300 hover:bg-[#2a1b29]"
                            @click="deleteChat"
                        >
                            Удалить чат
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <div v-if="searchOpen && store.activeChat?.id" class="px-6 py-3 border-b border-[#1b2d55] bg-[#0d1f41] space-y-2" ref="searchContainer">
            <div class="relative">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="w-full rounded-xl bg-[#13294b] border border-[#1b2d55] text-[#e7efff] placeholder-[#90a7ce] px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/30"
                    placeholder="Поиск по сообщениям..."
                />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[#90a7ce]">
                    {{ searchLoading ? '…' : '' }}
                </span>
            </div>
            <p v-if="searchError" class="text-sm text-red-300">{{ searchError }}</p>
            <div v-if="searchResults.length" class="max-h-52 overflow-y-auto space-y-1">
                <button
                    v-for="msg in searchResults"
                    :key="msg.id"
                    class="w-full text-left px-3 py-2 rounded-lg bg-[#13294b] hover:bg-[#1a3561] text-[#e7efff] border border-[#1b2d55]"
                    @click="scrollToMessage(msg.id)"
                >
                    <div class="text-xs text-[#90a7ce]">
                        {{ displayName(msg.sender) || 'Отправитель' }}
                    </div>
                    <div class="text-sm truncate">{{ msg.body }}</div>
                    <div class="text-[11px] text-[#90a7ce]">
                        {{ msg.created_at ? new Date(msg.created_at).toLocaleString() : '' }}
                    </div>
                </button>
            </div>
            <div v-else-if="!searchLoading && searchQuery.length >= 2" class="text-sm text-[#90a7ce]">Ничего не найдено</div>
        </div>

        <div
            v-if="showAdd && store.activeChat?.id && store.activeChat?.type === 'group'"
            class="px-6 py-4 border-b border-[#1b2d55] bg-[#0d1f41] flex items-start gap-3"
        >
            <div class="flex-1 space-y-2">
                <label class="block text-xs text-[#90a7ce] mb-1">Никнейм приглашения</label>
                <input
                    v-model="inviteNickname"
                    type="text"
                    class="w-full border border-[#1b2d55] rounded-lg px-3 py-2 bg-[#13294b] text-[#e7efff] placeholder-[#90a7ce] focus:ring-2 focus:ring-[#53d3ff]/40 focus:border-[#53d3ff]"
                    placeholder="nickname"
                />
                <div
                    v-if="inviteNickname.length >= 2"
                    class="border border-[#1b2d55] rounded-lg bg-[#0f1c3a] shadow-sm divide-y divide-[#1b2d55]"
                >
                    <div v-if="inviteSearchLoading" class="px-3 py-2 text-sm text-[#90a7ce]">Поиск...</div>
                    <template v-else>
                        <div
                            v-for="user in inviteResults"
                            :key="user.id"
                            class="px-3 py-2 hover:bg-[#13294b] cursor-pointer flex items-center justify-between"
                            @click="inviteNickname = user.nickname"
                        >
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-full bg-[#1a3561] flex items-center justify-center text-xs font-semibold uppercase text-[#e7efff]">
                                    {{ (user.nickname || user.email || '?').slice(0, 1) }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-[#e7efff]">{{ user.nickname }}</div>
                                    <div class="text-xs text-[#90a7ce]">{{ user.email }}</div>
                                </div>
                            </div>
                            <span class="text-xs text-[#90a7ce]">выбрать</span>
                        </div>
                        <div v-if="!inviteResults.length" class="px-3 py-2 text-sm text-[#90a7ce]">
                            Ничего не найдено
                        </div>
                    </template>
                </div>
                <p v-if="inviteError" class="text-red-300 text-xs mt-1">{{ inviteError }}</p>
            </div>
            <div class="flex gap-2">
                <button
                    class="px-3 py-2 rounded-lg bg-[#13294b] text-[#e7efff] border border-[#1b2d55]"
                    @click="showAdd = false"
                >
                    Отмена
                </button>
                <button
                    class="px-3 py-2 rounded-lg bg-gradient-to-r from-[#53d3ff] to-[#a66bff] text-[#0d1a35] font-semibold disabled:opacity-60"
                    :disabled="inviteLoading || !inviteNickname"
                    @click="addUser"
                >
                    {{ inviteLoading ? '...' : 'Добавить' }}
                </button>
            </div>
        </div>

        <div class="relative flex-1 min-h-0">
            <div
                ref="messagesContainer"
                class="flex-1 px-6 py-6 space-y-3 scroll-smooth absolute inset-0"
                class="overflow-y-auto"
                @scroll.passive="onScroll"
            >
                <div v-if="!store.activeChat" class="text-[#90a7ce] text-sm">
                    Начни общение: выбери чат справа или создай новый.
                </div>

                <div v-else class="space-y-5" data-message-container>
                    <div ref="topSentinel" class="h-px w-full"></div>
                    <div
                        v-for="group in groupedMessages"
                        :key="group.dateKey"
                        class="space-y-2"
                    >
                        <div class="flex justify-center sticky top-3 z-10">
                            <span class="px-3 py-1 text-xs bg-[#13294b]/90 backdrop-blur border border-[#1b2d55] rounded-full text-[#e7efff] shadow-sm">
                                {{ group.label }}
                            </span>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="msg in group.items"
                                :key="msg.id"
                                data-message
                                :data-message-id="msg.id"
                            >
                                <MessageItem
                                    :message="msg"
                                    :isOwn="isOwnMessage(msg)"
                                    :isRead="isMessageRead(msg)"
                                    :chatType="store.activeChat?.type"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button
                v-if="showDownButton"
                class="absolute bottom-5 right-6 h-11 w-11 rounded-full bg-[#13294b] border border-[#1b2d55] text-[#e7efff] shadow-lg shadow-[#53d3ff]/15 hover:bg-[#1a3561] transition"
                @click="scrollToBottom"
                title="К последнему сообщению"
            >
                <svg class="w-5 h-5 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M6 10l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </div>

        <div v-if="typingUser" class="px-6 py-2 text-sm text-[#90a7ce] italic">
            {{ typingUser }} is typing…
        </div>
    </div>
</template>

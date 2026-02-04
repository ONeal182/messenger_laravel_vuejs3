<script setup>
import { computed, ref, watch } from 'vue'
import { useChatsStore } from '@/stores/chats'
import { useAuthStore } from '@/stores/auth'
import * as messageApi from '@/services/messageApi'
import { createPrivate as createPrivateChat } from '@/services/chatApi'
import api from '@/services/api'
import EmojiPicker from 'vue3-emoji-picker'
import 'vue3-emoji-picker/css'

const store = useChatsStore()
const auth = useAuthStore()
const body = ref('')
const isDisabled = computed(() => !store.activeChat)
let lastPresencePing = 0
const showEmojis = ref(false)
const draftKey = computed(() => {
    if (!store.activeChat) return null
    if (store.activeChat.id) return `draft_chat_${store.activeChat.id}`
    const other = store.activeChat.users?.find((u) => u.id !== auth.user?.id)
    return other ? `draft_pending_${other.id}` : null
})

async function send() {
    if (!store.activeChat || !body.value.trim()) return

    // если чат еще не создан, создаем приватный и загружаем в стор
    if (!store.activeChat.id) {
        const target = store.activeChat.users?.find(u => u && u.id !== auth.user?.id)
        if (!target) return
        const { data } = await createPrivateChat(target.id)
        await store.openChat(data.chat)
    }

    const tempMessage = {
        id: Date.now(),
        body: body.value,
        sender: {
            id: auth.user?.id,
            email: auth.user?.email || 'You',
            name: auth.user?.name || auth.user?.email || 'You',
        },
        pending: true,
    }

    store.addMessage(tempMessage)
    store.addLastMessage(store.activeChat.id, tempMessage)

    body.value = ''
    clearDraft()

    try {
        const { data } = await messageApi.sendMessage(
            store.activeChat.id,
            tempMessage.body
        )

        store.replaceMessage(tempMessage.id, data.message)
        store.addLastMessage(store.activeChat.id, data.message)
    } catch {
        tempMessage.failed = true
    }
}

let typingTimeout = null

function emitTyping() {
    if (!store.activeChat) return
    if (typingTimeout) return

    messageApi.sendTyping(store.activeChat.id)
    pingPresence()

    typingTimeout = setTimeout(() => {
        typingTimeout = null
    }, 2000)
}

function pingPresence() {
    const now = Date.now()
    if (now - lastPresencePing < 5000) return
    lastPresencePing = now
    api.post('/auth/ping').catch(() => {})
}

function addEmoji(emoji) {
    const value = emoji?.i || emoji
    body.value += value
    showEmojis.value = false
    emitTyping()
}

function setCookie(name, value, days = 7) {
    const expires = new Date(Date.now() + days * 864e5).toUTCString()
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/`
}

function getCookie(name) {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`))
    return match ? decodeURIComponent(match.split('=')[1]) : ''
}

function clearDraft() {
    if (!draftKey.value) return
    document.cookie = `${draftKey.value}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
}

function loadDraft() {
    if (!draftKey.value) {
        body.value = ''
        return
    }
    body.value = getCookie(draftKey.value) || ''
}

watch(
    draftKey,
    () => {
        loadDraft()
    },
    { immediate: true }
)

watch(body, (val) => {
    if (!draftKey.value) return
    setCookie(draftKey.value, val || '', 7)
})
</script>

<template>
    <form class="border-t border-[#1b2d55] px-6 py-4 bg-[#0f1c3a]/85 backdrop-blur relative" @submit.prevent="send">
        <div v-if="isDisabled" class="text-sm text-[#90a7ce] text-center py-2">
            Выберите чат
        </div>
        <div v-else class="flex items-center gap-3">
            <div class="relative flex-1">
                <input
                    v-model="body"
                    @input="emitTyping"
                    @keyup.enter.exact.prevent="send"
                    class="w-full rounded-2xl bg-[#13294b] border border-[#1b2d55] text-[#e7efff] placeholder-[#90a7ce] px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/40"
                    placeholder="Напишите сообщение..."
                />
                <button
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[#90a7ce] hover:text-[#e7efff]"
                    @click="showEmojis = !showEmojis"
                >
                    😊
                </button>
            </div>

            <button
                type="submit"
                :disabled="!body.trim()"
                class="h-11 w-11 rounded-full bg-gradient-to-br from-[#53d3ff] to-[#a66bff] text-[#0d1a35] shadow-md flex items-center justify-center disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 14-7-7 14-2-5-5-2Z" />
                </svg>
            </button>
        </div>

        <div
            v-if="showEmojis"
            class="absolute bottom-16 right-4 max-w-[320px] z-10"
        >
            <EmojiPicker class="custom-emoji" @select="addEmoji" />
        </div>
    </form>
</template>

<style>
/* Глобально перекрашиваем vue3-emoji-picker v3-* классами */
.custom-emoji.v3-emoji-picker {
    --v3-picker-bg: linear-gradient(145deg, #0f1c3a 0%, #0b1730 100%);
    --v3-picker-fg: #e7efff;
    --v3-picker-border: #1b2d55;
    --v3-picker-input-bg: #13294b;
    --v3-picker-input-border: #1b2d55;
    --v3-picker-input-focus-border: #53d3ff;
    --v3-picker-emoji-hover: rgba(83, 211, 255, 0.08);
    --v3-group-image-filter: invert(1) brightness(1.6);

    height: 320px;
    width: 280px;
    box-shadow: 0 18px 50px rgba(12, 25, 54, 0.55);
    border-radius: 18px;
    border: 1px solid #1b2d55;
    overflow: hidden;
}

.custom-emoji .v3-header,
.custom-emoji .v3-footer {
    border-color: #1b2d55;
}

.custom-emoji .v3-search input {
    color: #e7efff;
}

.custom-emoji .v3-search input::placeholder {
    color: #90a7ce;
}

.custom-emoji .v3-groups .v3-group {
    opacity: 0.7;
    transition: 0.2s;
    color: #e7efff;
}

.custom-emoji .v3-groups .v3-group:hover,
.custom-emoji .v3-groups .v3-group.v3-is-active {
    opacity: 1;
    color: #53d3ff;
}

.custom-emoji .v3-groups {
    filter: invert(1) brightness(1.6) !important;
}


.custom-emoji .v3-body-inner {
    scrollbar-width: thin;
    scrollbar-color: #53d3ff transparent;
}

.custom-emoji .v3-body-inner::-webkit-scrollbar {
    width: 8px;
}

.custom-emoji .v3-body-inner::-webkit-scrollbar-thumb {
    background: #53d3ff;
    border-radius: 999px;
}

.custom-emoji .v3-body-inner::-webkit-scrollbar-track {
    background: transparent;
}
</style>

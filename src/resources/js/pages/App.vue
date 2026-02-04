<script setup>
import { onMounted, onUnmounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import Messenger from './Messenger.vue'
import AuthPage from './AuthPage.vue'
import api from '../services/api'

const auth = useAuthStore()
let pingInterval = null

function stopPing() {
    if (pingInterval) {
        clearInterval(pingInterval)
        pingInterval = null
    }
    window.removeEventListener('visibilitychange', handleVisibility)
    window.removeEventListener('pagehide', handlePageHide)
}

async function ping() {
    if (!auth.isAuthenticated) return
    if (document.visibilityState !== 'visible') return
    try {
        await api.post('/auth/ping')
    } catch (e) {
        // ignore
    }
}

function handleVisibility() {
    if (document.visibilityState === 'visible') {
        ping()
    } else {
        sendKeepalivePing()
    }
}

function handlePageHide() {
    sendKeepalivePing()
}

function sendKeepalivePing() {
    if (!auth.isAuthenticated) return
    const token = localStorage.getItem('token')
    fetch('/api/auth/ping', {
        method: 'POST',
        keepalive: true,
        headers: {
            Accept: 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
    }).catch(() => {})
}

onMounted(() => {
    auth.fetchUser()
})

watch(
    () => auth.isAuthenticated,
    (authed) => {
        stopPing()
        if (authed) {
            ping()
            pingInterval = setInterval(ping, 30000)
            window.addEventListener('visibilitychange', handleVisibility)
            window.addEventListener('pagehide', handlePageHide)
        }
    },
    { immediate: true }
)

onUnmounted(() => {
    stopPing()
})
</script>

<template>
    <div v-if="auth.booting" class="min-h-screen flex items-center justify-center bg-[#0d1a35] text-[#e7efff]">
        Загрузка...
    </div>
    <template v-else>
        <AuthPage v-if="!auth.isAuthenticated" />
        <Messenger v-else />
    </template>
</template>

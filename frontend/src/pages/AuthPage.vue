<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const mode = ref('login')
const form = ref({
    nickname: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const error = ref('')

async function submit() {
    error.value = ''
    try {
        if (mode.value === 'login') {
            await auth.login({
                nickname: form.value.nickname,
                password: form.value.password,
            })
        } else {
            await auth.register({ ...form.value })
        }
    } catch (e) {
        error.value = 'Упс, что-то пошло не так. Попробуйте ещё раз.'
    }
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center tg-chat-bg px-4">
        <div class="w-full max-w-lg bg-[#0d1a35]/90 backdrop-blur border border-[#1b2d55] shadow-2xl rounded-3xl p-8 space-y-8 text-[#e7efff]">
            <div class="text-center space-y-1">
                <p class="text-xs uppercase tracking-[0.2em] text-[#90a7ce]">Messenger</p>
                <h1 class="text-3xl font-bold">
                    {{ mode === 'login' ? 'Войти' : 'Создать аккаунт' }}
                </h1>
                <p class="text-sm text-[#90a7ce]">
                    {{ mode === 'login' ? 'Авторизуйтесь, чтобы продолжить' : 'Зарегистрируйтесь и начните общение' }}
                </p>
            </div>

            <div class="flex rounded-full bg-[#13294b] border border-[#1b2d55] p-1 text-sm font-semibold">
                <button
                    type="button"
                    class="flex-1 py-2 rounded-full transition"
                    :class="mode === 'login' ? 'bg-[#0f1c3a] shadow text-[#e7efff]' : 'text-[#90a7ce]'"
                    @click="mode = 'login'"
                >
                    Вход
                </button>
                <button
                    type="button"
                    class="flex-1 py-2 rounded-full transition"
                    :class="mode === 'register' ? 'bg-[#0f1c3a] shadow text-[#e7efff]' : 'text-[#90a7ce]'"
                    @click="mode = 'register'"
                >
                    Регистрация
                </button>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label class="block text-sm text-[#90a7ce] mb-1">Никнейм</label>
                    <input
                        v-model="form.nickname"
                        type="text"
                        required
                        class="w-full rounded-xl bg-[#13294b] border border-[#1b2d55] px-3 py-2 text-[#e7efff] focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/40"
                        placeholder="nickname"
                    />
                </div>

                <div v-if="mode === 'register'">
                    <label class="block text-sm text-[#90a7ce] mb-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full rounded-xl bg-[#13294b] border border-[#1b2d55] px-3 py-2 text-[#e7efff] focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/40"
                        placeholder="you@example.com"
                    />
                </div>

                <div>
                    <label class="block text-sm text-[#90a7ce] mb-1">Пароль</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        class="w-full rounded-xl bg-[#13294b] border border-[#1b2d55] px-3 py-2 text-[#e7efff] focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/40"
                        placeholder="********"
                    />
                </div>

                <div v-if="mode === 'register'">
                    <label class="block text-sm text-[#90a7ce] mb-1">Подтверждение пароля</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        class="w-full rounded-xl bg-[#13294b] border border-[#1b2d55] px-3 py-2 text-[#e7efff] focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/40"
                        placeholder="********"
                    />
                </div>

                <p v-if="error" class="text-sm text-red-400">{{ error }}</p>

                <button
                    type="submit"
                    :disabled="auth.loading"
                    class="w-full py-3 rounded-xl bg-gradient-to-r from-[#53d3ff] to-[#a66bff] text-[#0d1a35] font-semibold shadow-lg disabled:opacity-70"
                >
                    {{ auth.loading ? 'Загрузка...' : (mode === 'login' ? 'Войти' : 'Создать аккаунт') }}
                </button>
            </form>
        </div>
    </div>
</template>

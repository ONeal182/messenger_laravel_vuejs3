<script setup>
import { ref } from 'vue'
import { login } from '../services/authApi'

const email = ref('')
const password = ref('')
const error = ref(null)

async function submit() {
    error.value = null

    try {
        await login(email.value, password.value)
        window.location.reload() // пока просто
    } catch (e) {
        error.value = 'Login failed'
    }
}
</script>

<template>
    <div class="p-4 max-w-sm mx-auto">
        <h2 class="font-bold mb-2">Login</h2>

        <input
            v-model="email"
            type="email"
            placeholder="Email"
            class="border p-2 w-full mb-2"
        />

        <input
            v-model="password"
            type="password"
            placeholder="Password"
            class="border p-2 w-full mb-2"
        />

        <button
            @click="submit"
            class="bg-blue-500 text-white px-4 py-2 rounded"
        >
            Login
        </button>

        <div v-if="error" class="text-red-500 text-sm mt-2">
            {{ error }}
        </div>
    </div>
</template>

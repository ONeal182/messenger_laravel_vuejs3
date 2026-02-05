import { defineStore } from 'pinia'
import * as authApi from '@/services/authApi'
import * as userApi from '@/services/userApi'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token'),
        loading: false,
        booting: true,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
        userId: (state) => state.user?.id,
    },

    actions: {
        /**
         * 🔐 Login
         */
        async login(credentials) {
            this.loading = true

            const { data } = await authApi.login(credentials)

            this.token = data.token
            this.user = data.user

            localStorage.setItem('token', data.token)

            this.loading = false
        },

        /**
         * 🆕 Register
         */
        async register(payload) {
            this.loading = true

            const { data } = await authApi.register(payload)

            this.token = data.token
            this.user = data.user

            localStorage.setItem('token', data.token)

            this.loading = false
        },

        /**
         * 👤 Fetch current user (/me)
         * ВАЖНО: вызывается при загрузке приложения
         */
        async fetchUser() {
            this.booting = true
            if (!this.token) {
                this.booting = false
                return
            }

            try {
                const { data } = await authApi.me()
                this.user = data.user
            } catch (e) {
                this.logout()
            }
            this.booting = false
        },

        /**
         * 🚪 Logout
         */
        async logout() {
            try {
                await authApi.logout()
            } catch (e) {
                // игнорируем
            }

            this.user = null
            this.token = null
            localStorage.removeItem('token')
        },

        async updateProfile(payload) {
            const { data } = await userApi.updateProfile(payload)
            this.user = data.user
            return data.user
        },

        async uploadAvatar(file) {
            const { data } = await userApi.uploadAvatar(file)
            this.user = data.user
            return data.user
        },
    },
})

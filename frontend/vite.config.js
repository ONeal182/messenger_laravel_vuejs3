import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'
import { defineConfig, loadEnv } from 'vite'

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')
    const backendUrl = env.VITE_BACKEND_URL || process.env.VITE_BACKEND_URL || 'http://localhost:8000'
    const usePolling =
        env.VITE_USE_POLLING === 'true' ||
        process.env.VITE_USE_POLLING === 'true' ||
        process.env.CHOKIDAR_USEPOLLING === 'true'

    return {
        plugins: [vue(),tailwindcss()],
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'src'),
            },
        },
        server: {
            host: true,
            port: 5173,
            watch: usePolling
                ? {
                      usePolling: true,
                      interval: 100,
                  }
                : undefined,
            proxy: {
                '/api': {
                    target: backendUrl,
                    changeOrigin: true,
                },
                '/broadcasting/auth': {
                    target: backendUrl,
                    changeOrigin: true,
                },
                '/storage': {
                    target: backendUrl,
                    changeOrigin: true,
                },
            },
        },
    }
})

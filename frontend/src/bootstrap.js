import axios from 'axios'
window.axios = axios

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

// import './echo'

import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

// Read token from localStorage
const token = localStorage.getItem('token')

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY
if (!reverbKey) {
    console.warn('[echo] VITE_REVERB_APP_KEY is missing; realtime is disabled')
} else {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,

        wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        forceTLS: false,
        encrypted: false,

        enabledTransports: ['ws', 'wss'],

        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        },
    })

    console.log('Echo initialized', window.Echo)
}

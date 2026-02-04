import api from './api'

export function login(credentials) {
    return api.post('/auth/login', credentials)
}

export function register(payload) {
    return api.post('/auth/register', payload)
}

export function me() {
    return api.get('/auth/me')
}

export function logout() {
    return api.post('/auth/logout')
}

export function ping() {
    return api.post('/auth/ping')
}

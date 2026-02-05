import api from './api'

export function searchUsers(query, limit = 10) {
    return api.get('/users/search', {
        params: { query, limit },
    })
}

export function updateProfile(payload) {
    return api.put('/profile', payload)
}

export function uploadAvatar(file) {
    const form = new FormData()
    form.append('avatar', file, file.name || 'avatar.webp')
    return api.post('/profile/avatar', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    })
}

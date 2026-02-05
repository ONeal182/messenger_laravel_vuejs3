import api from './api'

export function fetchChats() {
    return api.get('/chats')
}

export function fetchChat(chatId) {
    return api.get(`/chats/${chatId}`)
}

export function createPrivate(userId) {
    return api.post('/chats/private', { user_id: userId })
}

export function createGroup(title, nicknames) {
    return api.post('/chats/group', { title, nicknames })
}

export function addUser(chatId, nickname) {
    return api.post(`/chats/${chatId}/users`, { nickname })
}

export function deleteChat(chatId) {
    return api.delete(`/chats/${chatId}`)
}

export function markRead(chatId, messageId = null) {
    return api.post(`/chats/${chatId}/read`, { message_id: messageId })
}

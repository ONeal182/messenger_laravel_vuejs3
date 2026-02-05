import api from './api'

export function fetchMessages(chatId, page = 1, perPage = 10) {
    return api.get(`/chats/${chatId}/messages`, { params: { page, per_page: perPage } })
}

export function searchMessages(chatId, query, limit = 20) {
    return api.get(`/chats/${chatId}/messages/search`, {
        params: { query, limit },
    })
}

export function sendMessage(chatId, body) {
    return api.post(`/chats/${chatId}/messages`, { body })
}

export function sendTyping(chatId) {
    return api.post(`/chats/${chatId}/typing`)
}

export function deleteForMe(messageId) {
    return api.delete(`/messages/${messageId}`)
}

export function deleteForAll(messageId) {
    return api.delete(`/messages/${messageId}/all`)
}

export function forwardMessage(messageId, chatId) {
    return api.post(`/messages/${messageId}/forward`, { chat_id: chatId })
}

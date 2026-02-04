import { defineStore } from 'pinia'
import * as chatApi from '@/services/chatApi'
import * as messageApi from '@/services/messageApi'

export const useChatsStore = defineStore('chats', {
    state: () => ({
        chats: [],
        activeChat: null,
        messages: [],
        _shouldScrollBottomSignal: 0,
        messagesPage: 1,
        messagesPerPage: 10,
        messagesHasMore: true,
        messagesLoadingMore: false,
        allowLoadMore: false,
        loading: false,
        typing: {},
        openingChatId: null,
    }),

    actions: {
        async fetchChats() {
            this.loading = true
            const { data } = await chatApi.fetchChats()
            this.chats = data.chats
            this.loading = false
        },

        async openChat(chat) {
            if (this.openingChatId === chat.id) return
            if (this.activeChat?.id === chat.id && this.messages.length) return
            this.openingChatId = chat.id
            this.activeChat = chat
            this.clearUnread(chat.id)
            this.messagesPage = 1
            this.messagesHasMore = true
            this.messagesLoadingMore = false
            this.allowLoadMore = false

            const [chatRes, messagesRes] = await Promise.all([
                chatApi.fetchChat(chat.id),
                messageApi.fetchMessages(chat.id, 1, this.messagesPerPage),
            ])

            this.activeChat = chatRes.data.chat
            this.messages = messagesRes.data.data.reverse()
            this.messagesPage = messagesRes.data.current_page || 1
            this.messagesHasMore = (messagesRes.data.current_page || 1) < (messagesRes.data.last_page || 1)
            this.messagesPerPage = messagesRes.data.per_page || this.messagesPerPage

            // Инициализация typing из Redis-ответа, если есть
            const typingUsers = chatRes.data.chat?.typing_users || []
            if (typingUsers.length) {
                const first = typingUsers[0]
                if (first?.label) {
                    this.setTyping(chat.id, first.label)
                }
            }

            // сразу отметить прочитанным (без ожидания UI-событий)
            try {
                await chatApi.markRead(chat.id)
            } catch (e) {
                console.warn('markRead failed', e)
            }

            // сообщить списку о необходимости прокрутить вниз
            this.$patch({ _shouldScrollBottomSignal: Date.now() })

            // включить подгрузку через секунду
            setTimeout(() => {
                this.allowLoadMore = true
            }, 1000)
            this.openingChatId = null
        },

        openPendingChat(user, currentUser) {
            this.messages = []
            this.activeChat = {
                id: null,
                type: 'private',
                users: [currentUser, user].filter(Boolean),
                last_message: null,
            }
        },

        addMessage(message) {
            this.messages.push(message)
            this.updateLastMessageInList(message.chat_id || this.activeChat?.id, message)
        },

        removeMessage(chatId, messageId) {
            // убрать из списка сообщений, если открыт этот чат
            if (this.activeChat?.id === chatId) {
                this.messages = this.messages.filter((m) => m.id !== messageId)
            }

            // если last_message совпадает — сбросить
            const chat = this.chats.find((c) => c.id === chatId)
            if (chat && chat.last_message?.id === messageId) {
                chat.last_message = null
            }
            if (this.activeChat?.id === chatId && this.activeChat.last_message?.id === messageId) {
                this.activeChat = { ...this.activeChat, last_message: null }
            }

            this.updateLastMessageInList(chatId)
        },

        markMessageDeleted(chatId, messageId) {
            this.removeMessage(chatId, messageId)
        },

        replaceMessage(tempId, realMessage) {
            const index = this.messages.findIndex(m => m.id === tempId)
            if (index !== -1) {
                this.messages[index] = realMessage
            }
        },

        addLastMessage(chatId, message) {
            const chat = this.chats.find(c => c.id === chatId)
            if (chat) {
                chat.last_message = message
            }
        },

        incrementUnread(chatId) {
            const chat = this.chats.find(c => c.id === chatId)
            if (chat) {
                chat.unread_count = (chat.unread_count || 0) + 1
            }
        },

        clearUnread(chatId) {
            const chat = this.chats.find(c => c.id === chatId)
            if (chat) {
                chat.unread_count = 0
            }
        },

        removeChat(chatId) {
            this.chats = this.chats.filter(c => c.id !== chatId)
            if (this.activeChat?.id === chatId) {
                this.activeChat = null
                this.messages = []
            }
        },

        setTyping(chatId, userLabel) {
            this.typing = {
                ...this.typing,
                [chatId]: {
                    user: userLabel,
                    at: Date.now(),
                },
            }

            setTimeout(() => {
                if (this.typing[chatId] && Date.now() - this.typing[chatId].at >= 1800) {
                    const clone = { ...this.typing }
                    delete clone[chatId]
                    this.typing = clone
                }
            }, 2000)
        },

        markMessagesRead(chatId, lastMessageId, readerId, currentUserId) {
            if (!this.activeChat || this.activeChat.id !== chatId) return
            if (!this.messages?.length) return

            this.messages = this.messages.map((m) => {
                if (m.sender?.id === currentUserId && m.id <= lastMessageId) {
                    return { ...m, read: true }
                }
                return m
            })
        },

        updateUserPresence(chatId, userId, lastSeenAt) {
            const chat = this.chats.find((c) => c.id === chatId)
            if (chat?.users) {
                chat.users = chat.users.map((u) =>
                    u.id === userId ? { ...u, last_seen_at: lastSeenAt, pivot: { ...u.pivot, last_seen_at: lastSeenAt } } : u
                )
            }

            if (this.activeChat?.id === chatId && this.activeChat.users) {
                this.activeChat = {
                    ...this.activeChat,
                    users: this.activeChat.users.map((u) =>
                        u.id === userId ? { ...u, last_seen_at: lastSeenAt, pivot: { ...u.pivot, last_seen_at: lastSeenAt } } : u
                    ),
                }
            }
        },

        updateLastMessageInList(chatId, overrideMessage = null) {
            if (!chatId) return
            const chat = this.chats.find((c) => c.id === chatId)
            if (!chat) return

            let last = overrideMessage

            // взять последнюю из текущего массива сообщений, если мы в этом чате
            if (!last && this.activeChat?.id === chatId && this.messages.length) {
                last = this.messages[this.messages.length - 1]
            }

            chat.last_message = last || chat.last_message || null
            if (this.activeChat?.id === chatId) {
                this.activeChat = { ...this.activeChat, last_message: chat.last_message }
            }
        },

        getChatById(id) {
            return this.chats.find((c) => c.id === id) || null
        },

        async loadMoreMessages(chatId) {
            if (this.messagesLoadingMore || !this.messagesHasMore || !this.allowLoadMore) return
            this.messagesLoadingMore = true
            try {
                const nextPage = this.messagesPage + 1
                const { data } = await messageApi.fetchMessages(chatId, nextPage, this.messagesPerPage)
                const older = data.data.reverse()
                this.messages = [...older, ...this.messages]
                this.messagesPage = data.current_page || nextPage
                this.messagesHasMore = (data.current_page || nextPage) < (data.last_page || nextPage)
            } finally {
                this.messagesLoadingMore = false
            }
        },
    },
})

const channels = new Map()

function getEcho() {
    return typeof window !== 'undefined' ? window.Echo : null
}

function ensureChannel(chatId) {
    const echo = getEcho()
    if (!echo) return null

    if (!channels.has(chatId)) {
        const channel = echo.private(`chat.${chatId}`)
        const record = {
            channel,
            ref: 0,
            cbs: {
                messageSent: new Set(),
                userTyping: new Set(),
                messageRead: new Set(),
                userPresence: new Set(),
                messageDeleted: new Set(),
            },
        }

        channel.listen('.message.sent', (e) => {
            record.cbs.messageSent.forEach((cb) => cb(e))
        })
        channel.listen('.user.typing', (e) => {
            record.cbs.userTyping.forEach((cb) => cb(e))
        })
        channel.listen('.message.read', (e) => {
            record.cbs.messageRead.forEach((cb) => cb(e))
        })
        channel.listen('.user.presence', (e) => {
            record.cbs.userPresence.forEach((cb) => cb(e))
        })
        channel.listen('.message.deleted', (e) => {
            record.cbs.messageDeleted.forEach((cb) => cb(e))
        })

        channels.set(chatId, record)
    }

    const rec = channels.get(chatId)
    rec.ref += 1
    return rec
}

function releaseChannel(chatId) {
    const rec = channels.get(chatId)
    if (!rec) return
    rec.ref -= 1
    if (rec.ref <= 0) {
        rec.channel.stopListening('.message.sent')
        rec.channel.stopListening('.user.typing')
        rec.channel.stopListening('.message.read')
        rec.channel.stopListening('.user.presence')
        rec.channel.stopListening('.message.deleted')
        getEcho()?.leave(`chat.${chatId}`)
        channels.delete(chatId)
    }
}

export function subscribeToChat(chatId, handlers = {}) {
    const rec = ensureChannel(chatId)
    if (!rec) return () => {}

    if (handlers.onMessage) rec.cbs.messageSent.add(handlers.onMessage)
    if (handlers.onTyping) rec.cbs.userTyping.add(handlers.onTyping)
    if (handlers.onRead) rec.cbs.messageRead.add(handlers.onRead)
    if (handlers.onPresence) rec.cbs.userPresence.add(handlers.onPresence)
    if (handlers.onDelete) rec.cbs.messageDeleted.add(handlers.onDelete)

    return () => {
        if (handlers.onMessage) rec.cbs.messageSent.delete(handlers.onMessage)
        if (handlers.onTyping) rec.cbs.userTyping.delete(handlers.onTyping)
        if (handlers.onRead) rec.cbs.messageRead.delete(handlers.onRead)
        if (handlers.onPresence) rec.cbs.userPresence.delete(handlers.onPresence)
        if (handlers.onDelete) rec.cbs.messageDeleted.delete(handlers.onDelete)
        releaseChannel(chatId)
    }
}

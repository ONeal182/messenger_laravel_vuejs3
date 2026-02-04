<script setup>
import { onMounted, ref } from 'vue'
import { useChatsStore } from '../stores/chats'
import ChatList from '../components/ChatList.vue'
import MessageList from '../components/MessageList.vue'
import MessageInput from '../components/MessageInput.vue'
import SettingsModal from '../components/SettingsModal.vue'
import GroupInfoPanel from '../components/GroupInfoPanel.vue'
import UserProfilePanel from '../components/UserProfilePanel.vue'

const store = useChatsStore()
const showSettings = ref(false)
const showGroupInfo = ref(false)
const showUserProfile = ref(false)

onMounted(() => {
    store.fetchChats()
})
</script>

<template>
    <div class="min-h-screen w-full">
        <div class="h-screen flex flex-col lg:flex-row bg-transparent">
            <div class="w-full lg:w-[28%] bg-[#0d1a35] border-b lg:border-b-0 lg:border-r border-slate-800/60 flex flex-col order-1">
                <ChatList @open-settings="() => { showSettings = true }" />
            </div>

            <div class="flex-1 min-h-0 flex flex-col tg-chat-bg order-2">
                <template v-if="showUserProfile">
                    <UserProfilePanel
                        :user="store.activeChat?.users?.find(u => u.id !== (store.activeChat?.users?.find(x => x.id === store.activeChat?.users?.[0]?.id)?.id))"
                        @close="showUserProfile = false"
                    />
                </template>
                <template v-else-if="showGroupInfo">
                    <GroupInfoPanel :chat="store.activeChat" @close="showGroupInfo = false" />
                </template>
                <template v-else>
                    <MessageList
                        @open-group-info="() => { showGroupInfo = true }"
                        @open-user-profile="() => { showUserProfile = true }"
                    />
                    <MessageInput />
                </template>
            </div>
        </div>

        <SettingsModal
            :show="showSettings"
            @close="showSettings = false"
        />
    </div>
</template>

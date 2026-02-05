<script setup>
defineProps({
    chat: {
        type: Object,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['select'])
</script>

<template>
    <li
        @click="$emit('select', chat)"
        class="p-2 rounded cursor-pointer"
        :class="active ? 'bg-blue-100 font-semibold' : 'hover:bg-gray-100'"
    >
        Chat #{{ chat.id }}
    </li>
</template>

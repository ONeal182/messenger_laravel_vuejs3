<script setup>
import { ref, computed, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import AvatarCropper from './AvatarCropper.vue'

const auth = useAuthStore()
const form = ref({
  name: '',
  last_name: '',
  middle_name: '',
  nickname: '',
  birth_date: '',
})
const saving = ref(false)
const error = ref('')

const fileInput = ref(null)
const cropSrc = ref(null)
const naturalSize = ref({ w: 0, h: 0 })
const showCropper = ref(false)

const canSave = computed(() => form.value.nickname || form.value.name || form.value.last_name)

watch(
  () => auth.user,
  () => resetForm(),
  { immediate: true }
)

function resetForm() {
  form.value = {
    name: auth.user?.name || '',
    last_name: auth.user?.last_name || '',
    middle_name: auth.user?.middle_name || '',
    nickname: auth.user?.nickname || '',
    birth_date: auth.user?.birth_date || '',
  }
  cropSrc.value = null
  naturalSize.value = { w: 0, h: 0 }
  showCropper.value = false
}

async function saveProfile() {
  saving.value = true
  error.value = ''
  try {
    await auth.updateProfile({ ...form.value })
  } catch (e) {
    error.value = 'Не удалось сохранить профиль'
  } finally {
    saving.value = false
  }
}

function chooseAvatar() {
  fileInput.value?.click()
}

function handleFile(e) {
  const file = e.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (evt) => {
    cropSrc.value = evt.target?.result
    const img = new Image()
    img.onload = () => {
      naturalSize.value = { w: img.width, h: img.height }
      showCropper.value = true
    }
    img.src = evt.target?.result
  }
  reader.readAsDataURL(file)
}

async function handleCropSave(blob) {
  saving.value = true
  error.value = ''
  try {
    const file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' })
    await auth.uploadAvatar(file)
    showCropper.value = false
  } catch (e) {
    error.value = e?.response?.data?.errors?.avatar?.[0] || 'Не удалось обновить аватар'
  } finally {
    saving.value = false
  }
}

function displayName(user) {
  if (!user) return ''
  const full = [user.name, user.last_name].filter(Boolean).join(' ').trim()
  if (user.name && user.last_name) return full
  if (user.name) return user.name
  if (user.nickname) return user.nickname
  return user.email || ''
}
</script>

<template>
  <div class="flex-1 min-h-0 overflow-y-auto p-6 text-[#e7efff]">
    <div class="max-w-4xl mx-auto space-y-6">
      <div class="flex items-center gap-4">
        <div class="h-24 w-24 rounded-full overflow-hidden border-2 border-[#1b2d55] bg-[#13294b] flex items-center justify-center">
          <img v-if="auth.user?.avatar_thumb_path" :src="auth.user.avatar_thumb_path" class="h-full w-full object-cover" alt="avatar" />
          <span v-else class="text-2xl font-semibold">{{ (auth.user?.nickname || auth.user?.name || '?').slice(0,1).toUpperCase() }}</span>
        </div>
        <div>
          <p class="text-xs uppercase tracking-[0.14em] text-[#90a7ce]">Профиль</p>
          <h2 class="text-2xl font-semibold">{{ displayName(auth.user) || 'Без имени' }}</h2>
          <div class="flex gap-2 mt-2">
            <button class="px-3 py-1.5 rounded-lg bg-[#13294b] border border-[#1b2d55]" @click="chooseAvatar">Загрузить</button>
            <input ref="fileInput" type="file" class="hidden" accept=".jpg,.jpeg,.png,.webp" @change="handleFile" />
          </div>
        </div>
      </div>

      <div class="bg-[#0f1c3a] border border-[#1b2d55] rounded-xl p-4 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-sm text-[#90a7ce] mb-1">Имя</label>
            <input v-model="form.name" type="text" class="w-full rounded-lg bg-[#13294b] border border-[#1b2d55] px-3 py-2 text-[#e7efff] focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/30" />
          </div>
          <div>
            <label class="block text-sm text-[#90a7ce] mb-1">Фамилия</label>
            <input v-model="form.last_name" type="text" class="w-full rounded-lg bg-[#13294b] border border-[#1b2d55] px-3 py-2 text-[#e7efff] focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/30" />
          </div>
          <div>
            <label class="block text-sm text-[#90a7ce] mb-1">Отчество</label>
            <input v-model="form.middle_name" type="text" class="w-full rounded-lg bg-[#13294b] border border-[#1b2d55] px-3 py-2 text-[#e7efff] focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/30" />
          </div>
          <div>
            <label class="block text-sm text-[#90a7ce] mb-1">Ник</label>
            <input v-model="form.nickname" type="text" class="w-full rounded-lg bg-[#13294b] border border-[#1b2d55] px-3 py-2 text-[#e7efff] focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/30" />
          </div>
          <div>
            <label class="block text-sm text-[#90a7ce] mb-1">Дата рождения</label>
            <input v-model="form.birth_date" type="date" class="w-full rounded-lg bg-[#13294b] border border-[#1b2d55] px-3 py-2 text-[#e7efff] focus:outline-none focus:ring-2 focus:ring-[#53d3ff]/30" />
          </div>
        </div>
        <p v-if="error" class="text-sm text-red-300">{{ error }}</p>
        <div class="flex justify-end">
          <button
            class="px-4 py-2 rounded-lg bg-gradient-to-r from-[#53d3ff] to-[#a66bff] text-[#0d1a35] font-semibold disabled:opacity-50"
            :disabled="saving || !canSave"
            @click="saveProfile"
          >
            {{ saving ? 'Сохранение...' : 'Сохранить' }}
          </button>
        </div>
      </div>
    </div>

    <AvatarCropper
      :show="showCropper"
      :src="cropSrc"
      :natural-size="naturalSize"
      @close="showCropper = false"
      @save="handleCropSave"
    />
  </div>
</template>

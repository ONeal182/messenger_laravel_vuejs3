<script setup>
import { ref, watch, computed } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  src: { type: String, default: null },
})

const emit = defineEmits(['close', 'save'])

const offsetPxX = ref(0) // смещение относительно центра min-стороны
const offsetPxY = ref(0)
const zoom = ref(1)
const naturalSize = ref({ w: 0, h: 0 })
const dragging = ref(false)
const dragStart = ref({ x: 0, y: 0 })
const startOffset = ref({ x: 0, y: 0 })
const dragSensitivity = 0.5 // множитель скорости (меньше = быстрее)
const viewport = 320 // размер видимой области (px)

watch(
  () => props.src,
  () => {
    resetControls()
  }
)

function resetControls() {
  offsetPxX.value = 0
  offsetPxY.value = 0
  zoom.value = 1
}

async function handleSave() {
  if (!props.src || !naturalSize.value.w) return
  const blob = await buildCroppedBlob()
  emit('save', blob)
}

async function buildCroppedBlob() {
  return new Promise((resolve, reject) => {
    const img = new Image()
    img.onload = () => {
      const w = img.naturalWidth || img.width
      const h = img.naturalHeight || img.height
      const baseSize = Math.min(w, h)
      const cropSize = baseSize / zoom.value
      const maxOffset = (baseSize - cropSize) / 2
      const offX = Math.min(Math.max(-maxOffset, offsetPxX.value), maxOffset)
      const offY = Math.min(Math.max(-maxOffset, offsetPxY.value), maxOffset)
      const centerX = (w - baseSize) / 2 + baseSize / 2 + offX
      const centerY = (h - baseSize) / 2 + baseSize / 2 + offY
      const sx = Math.min(Math.max(0, centerX - cropSize / 2), w - cropSize)
      const sy = Math.min(Math.max(0, centerY - cropSize / 2), h - cropSize)

      const canvas = document.createElement('canvas')
      const target = 1080
      canvas.width = target
      canvas.height = target
      const ctx = canvas.getContext('2d')
      ctx.drawImage(img, sx, sy, cropSize, cropSize, 0, 0, target, target)
      canvas.toBlob(
        (blob) => {
          if (blob) resolve(blob)
          else reject(new Error('blob fail'))
        },
        'image/jpeg',
        0.92
      )
    }
    img.onerror = reject
    img.src = props.src
  })
}

const maskStyle = computed(() => {
  const baseSize = Math.max(1, Math.min(naturalSize.value.w || 1, naturalSize.value.h || 1))
  const cropSize = baseSize / zoom.value
  const displayedSize = viewport * zoom.value
  const translateX = (offsetPxX.value / cropSize) * displayedSize
  const translateY = (offsetPxY.value / cropSize) * displayedSize
  return {
    transform: `translate(${translateX}px, ${translateY}px) scale(${zoom.value})`,
  }
})

function onImgLoad(e) {
  const el = e.target
  naturalSize.value = { w: el.naturalWidth || el.width, h: el.naturalHeight || el.height }
}

function clampPosition() {
  const baseSize = Math.max(1, Math.min(naturalSize.value.w || 1, naturalSize.value.h || 1))
  const cropSize = baseSize / zoom.value
  const maxOffset = (baseSize - cropSize) / 2
  offsetPxX.value = Math.max(-maxOffset, Math.min(maxOffset, offsetPxX.value))
  offsetPxY.value = Math.max(-maxOffset, Math.min(maxOffset, offsetPxY.value))
}

function onMouseDown(e) {
  if (!props.src) return
  if (e.button !== 0) return
  e.preventDefault()
  dragging.value = true
  dragStart.value = { x: e.clientX, y: e.clientY }
  startOffset.value = { x: offsetPxX.value, y: offsetPxY.value }
  window.addEventListener('mousemove', onMouseMove)
  window.addEventListener('mouseup', onMouseUp)
}

function onMouseMove(e) {
  if (!dragging.value) return
  const dx = e.clientX - dragStart.value.x
  const dy = e.clientY - dragStart.value.y
  const baseSize = Math.max(1, Math.min(naturalSize.value.w || 1, naturalSize.value.h || 1))
  const visible = baseSize / zoom.value
  const norm = Math.max(1, visible / dragSensitivity)
  offsetPxX.value = startOffset.value.x + dx * (baseSize / norm)
  offsetPxY.value = startOffset.value.y + dy * (baseSize / norm)
  clampPosition()
}

function onMouseUp() {
  dragging.value = false
  window.removeEventListener('mousemove', onMouseMove)
  window.removeEventListener('mouseup', onMouseUp)
}

function onWheel(e) {
  if (!props.src) return
  e.preventDefault()
  const delta = e.deltaY < 0 ? 0.05 : -0.05
  zoom.value = Math.min(3, Math.max(1, zoom.value + delta))
}

// touch support
function onTouchStart(e) {
  if (!props.src) return
  const t = e.touches?.[0]
  if (!t) return
  dragging.value = true
  dragStart.value = { x: t.clientX, y: t.clientY }
  startOffset.value = { x: offsetPxX.value, y: offsetPxY.value }
  window.addEventListener('touchmove', onTouchMove, { passive: false })
  window.addEventListener('touchend', onTouchEnd)
}

function onTouchMove(e) {
  if (!dragging.value) return
  const t = e.touches?.[0]
  if (!t) return
  e.preventDefault()
  const dx = t.clientX - dragStart.value.x
  const dy = t.clientY - dragStart.value.y
  const baseSize = Math.max(1, Math.min(naturalSize.value.w || 1, naturalSize.value.h || 1))
  const visible = baseSize / zoom.value
  const norm = Math.max(1, visible / dragSensitivity)
  offsetPxX.value = startOffset.value.x + dx * (baseSize / norm)
  offsetPxY.value = startOffset.value.y + dy * (baseSize / norm)
  clampPosition()
}

function onTouchEnd() {
  dragging.value = false
  window.removeEventListener('touchmove', onTouchMove)
  window.removeEventListener('touchend', onTouchEnd)
}
</script>

<template>
  <transition name="fade">
    <div v-if="show" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50">
      <div class="relative w-full h-full sm:h-auto sm:w-auto flex flex-col items-center justify-center px-4">
        <div class="bg-black/30 rounded-2xl p-6 shadow-2xl border border-white/10">
          <div
            class="relative w-[320px] h-[320px] bg-[#0f1c3a] rounded-3xl overflow-hidden flex items-center justify-center"
            @mousedown="onMouseDown"
            @touchstart="onTouchStart"
            @wheel="onWheel"
          >
            <img
              v-if="src"
              :src="src"
              class="w-full h-full object-cover transition-transform"
              @load="onImgLoad"
              :style="maskStyle"
            />
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
              <div class="w-64 h-64 rounded-full border-2 border-white/60 shadow-inner"></div>
            </div>
          </div>
          <div class="mt-4 space-y-2 w-[320px] text-center text-white/70 text-sm">
            <p>Перемещайте мышью, колесо или ползунок — зум</p>
            <input type="range" min="1" max="3" step="0.01" v-model.number="zoom" class="w-full" />
          </div>
          <div class="mt-6 flex justify-center gap-3">
            <button class="px-4 py-2 rounded-lg bg-white/10 text-white" @click="emit('close')">Отмена</button>
            <button class="px-4 py-2 rounded-lg bg-white/10 text-white" @click="resetControls">Сброс</button>
            <button class="px-4 py-2 rounded-lg bg-gradient-to-r from-[#53d3ff] to-[#a66bff] text-[#0d1a35] font-semibold" @click="handleSave">
              Готово
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-to,
.fade-leave-from {
  opacity: 1;
}
</style>

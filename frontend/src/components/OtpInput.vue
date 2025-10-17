<script setup>
import { ref, nextTick } from 'vue';

const props = defineProps({
  length: { type: Number, default: 6 },
  modelValue: { type: String, default: '' },
  disabled: { type: Boolean, default: false }
});
const emit = defineEmits(['update:modelValue', 'complete']);

const boxes = ref(Array.from({ length: props.length }, (_, i) => props.modelValue[i] || ''));
const inputs = ref([]);

function focusIndex(i){ nextTick(() => inputs.value[i]?.focus()); }

function setValue(i, v){
  if (props.disabled) return;
  v = (v.match(/\d/g) || []).join('').slice(0,1);
  boxes.value[i] = v;
  const joined = boxes.value.join('');
  emit('update:modelValue', joined);
  if (v && i < props.length-1) focusIndex(i+1);
  if (joined.length === props.length && boxes.value.every(c => c !== '')) emit('complete', joined);
}

function onKeyDown(i, e){
  if (props.disabled) return;
  if (e.key === 'Backspace'){
    if (boxes.value[i]) {
      boxes.value[i]=''; emit('update:modelValue', boxes.value.join('')); return;
    }
    if (i>0){ e.preventDefault(); boxes.value[i-1]=''; emit('update:modelValue', boxes.value.join('')); focusIndex(i-1); }
  } else if (e.key === 'ArrowLeft' && i>0){ e.preventDefault(); focusIndex(i-1); }
    else if (e.key === 'ArrowRight' && i<props.length-1){ e.preventDefault(); focusIndex(i+1); }
}

function onPaste(i, e){
  if (props.disabled) return;
  const digits = (e.clipboardData?.getData('text') || '').replace(/\D/g,'').slice(0, props.length);
  if (!digits) return;
  e.preventDefault();
  const arr = digits.split('');
  boxes.value = boxes.value.map((_, k) => arr[k] || '');
  const joined = boxes.value.join('');
  emit('update:modelValue', joined);
  if (joined.length === props.length && boxes.value.every(c => c !== '')) emit('complete', joined);
  else focusIndex(Math.min(i + arr.length, props.length-1));
}
</script>

<template>
  <div class="flex gap-2">
    <input v-for="(_, i) in boxes" :key="i" ref="inputs"
      class="w-12 h-12 text-center text-xl border rounded"
      inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
      :value="boxes[i]" :disabled="disabled"
      @input="setValue(i, $event.target.value)"
      @keydown="onKeyDown(i, $event)" @paste="onPaste(i, $event)" />
  </div>
</template>

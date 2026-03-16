<script setup>
import { watch, onBeforeUnmount } from "vue";
import { useEditor, EditorContent } from "@tiptap/vue-3";
import StarterKit from "@tiptap/starter-kit";

const props = defineProps({
  modelValue: { type: String, default: "" },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: "Add a comment..." },
  isFocused: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const editor = useEditor({
  content: props.modelValue,
  editable: !props.disabled,
  extensions: [StarterKit],
  onUpdate: ({ editor }) => {
    emit("update:modelValue", editor.getHTML());
  },
});

const clearFormatting = () => {
  editor.value.chain().focus().clearNodes().unsetAllMarks().run();

  activeTextColor.value = DEFAULT_TEXT_COLOR;
  activeHighlightColor.value = DEFAULT_HIGHLIGHT_COLOR;
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (editor.value && newVal === "" && editor.value.getHTML() !== "<p></p>") {
      editor.value.commands.setContent("");
    }
  },
);

watch(
  () => props.disabled,
  (newVal) => {
    if (editor.value) {
      editor.value.setEditable(!newVal);
    }
  },
);

onBeforeUnmount(() => {
  if (editor.value) editor.value.destroy();
});
</script>

<template>
  <div class="comment-editor-container" :class="{ 'is-disabled': disabled }">
    <div v-if="editor && !disabled && isFocused" class="minimal-toolbar">
      <button
        type="button"
        @click.stop="editor.chain().focus().toggleBold().run()"
        :class="{ active: editor.isActive('bold') }"
        title="Bold"
      >
        <i class="bi bi-type-bold fs-6"></i>
      </button>
      <button
        type="button"
        @click.stop="editor.chain().focus().toggleItalic().run()"
        :class="{ active: editor.isActive('italic') }"
        title="Italic"
      >
        <i class="bi bi-type-italic fs-6"></i>
      </button>
      <button
        type="button"
        @click.stop="editor.chain().focus().toggleStrike().run()"
        :class="{ active: editor.isActive('strike') }"
        title="Strike"
      >
        <i class="bi bi-type-strikethrough fs-6"></i>
      </button>
      <button
        type="button"
        @click="editor.chain().focus().toggleUnderline().run()"
        :class="{ active: editor.isActive('underline') }"
        title="Underline"
      >
        <i class="bi bi-type-underline fs-6"></i>
      </button>

      <button
        type="button"
        @click="clearFormatting"
        title="Clear All Formatting"
      >
        <i class="bi bi-eraser fs-6"></i>
      </button>
    </div>

    <div class="editor-wrapper">
      <div
        v-if="!modelValue || modelValue === '<p></p>'"
        class="custom-placeholder"
      >
        {{ placeholder }}
      </div>

      <editor-content :editor="editor" class="editor-content-area" />
    </div>
  </div>
</template>

<style scoped>
.comment-editor-container {
  background: transparent;
}

.comment-editor-container.is-disabled {
  opacity: 0.8;
}

.minimal-toolbar {
  display: flex;
  gap: 4px;
  padding: 6px 8px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.minimal-toolbar button {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 4px;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s ease;
}

.minimal-toolbar button:hover {
  background: #007c8a25;
  color: #0f172a;
}

.minimal-toolbar button.active {
  background: #78caa970;
  color: #2e6c44;
  border: 1px rgba(24, 97, 46, 0.562) solid;
}

.editor-wrapper {
  position: relative;
}

.custom-placeholder {
  position: absolute;
  top: 1rem;
  left: 1rem;
  color: #94a3b8;
  pointer-events: none;
  font-size: 0.95rem;
}

:deep(.tiptap) {
  padding: 1rem;
  min-height: 80px;
  outline: none;
  font-size: 0.95rem;
  color: #1f2937;
}
</style>

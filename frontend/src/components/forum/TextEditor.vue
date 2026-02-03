<script setup>
import { ref, computed } from "vue";
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Underline from '@tiptap/extension-underline';
import { Color } from '@tiptap/extension-color';
import { TextStyle } from '@tiptap/extension-text-style';
import { Highlight } from '@tiptap/extension-highlight';
import { uploadImage } from "@/api/media";
import { TextAlign } from '@tiptap/extension-text-align';
import { Link } from '@tiptap/extension-link';

const props = defineProps({
    modelValue: String,
    placeholder: String
});

const showLinkMenu = ref(false);
const linkUrl = ref('');
const linkInput = ref(null);

const emit = defineEmits(['update:modelValue']);

// Default colors
const DEFAULT_TEXT_COLOR = '#000000';
const DEFAULT_HIGHLIGHT_COLOR = 'transparent';

const activeTextColor = ref(DEFAULT_TEXT_COLOR);
const activeHighlightColor = ref(DEFAULT_HIGHLIGHT_COLOR);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Underline,
        TextStyle,
        Color,
        TextAlign.configure({ types: ['heading', 'paragraph'], }),
        Highlight.configure({ multicolor: true }),
        Image.configure({ inline: true }),
        Link.configure({ openOnClick: false, HTMLAttributes: { class: 'my-custom-link', }}),
    ],

    // Allows you to use tab in the editor
    editorProps: {
        handleKeyDown: (view, event) => {
            if (event.key === 'Tab') {
                event.preventDefault();

                editor.value.commands.insertContent('    ');

                return true;
            }
            return false;
        },
    },

    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
        updateActiveAttributes();
    },
    onSelectionUpdate: () => {
        updateActiveAttributes();
    }
});

const openLinkMenu = () => {
    linkUrl.value = editor.value.getAttributes('link').href || '';
    showLinkMenu.value = !showLinkMenu.value;
};

const applyLink = () => {
    if (linkUrl.value === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        let url = linkUrl.value;
        if (!/^https?:\/\//i.test(url) && !url.startsWith('/')) url = 'https://' + url;
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
    showLinkMenu.value = false;
};

const removeLink = () => {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    linkUrl.value = '';
    showLinkMenu.value = false;
};

const handleHeadingChange = (event) => {
    const value = event.target.value;
    if (value === "paragraph") editor.value.chain().focus().setParagraph().run();
    else editor.value.chain().focus().toggleHeading({ level: parseInt(value) }).run();
};

const currentHeadingValue = computed(() => {
    if (!editor.value) return "paragraph";
    if (editor.value.isActive('heading', { level: 1 })) return "1";
    if (editor.value.isActive('heading', { level: 2 })) return "2";
    return "paragraph";
});

const updateTextColor = (event) => {
    const color = event.target.value;
    activeTextColor.value = color;
    editor.value.chain().focus().setColor(color).run();
};

const updateHighlightColor = (event) => {
    const color = event.target.value;
    activeHighlightColor.value = color;
    editor.value.chain().focus().setHighlight({ color }).run();
};

const triggerImageUpload = async () => {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";
    input.onchange = async () => {
        const file = input.files?.[0];
        if (!file) return;
        try {
            const data = await uploadImage(file);
            editor.value.chain().focus().setImage({ src: data.url }).run();
        } catch (err) {
            console.error("Upload failed", err);
        }
    };
    input.click();
};

// Helper for updating toolbar active colors
const updateActiveAttributes = () => {
    if (!editor.value) return;
    const highlightAttrs = editor.value.getAttributes('highlight');

    if (highlightAttrs.color) {
        activeHighlightColor.value = highlightAttrs.color;
    } else {
        activeHighlightColor.value = DEFAULT_HIGHLIGHT_COLOR;
    }

    const textAttrs = editor.value.getAttributes('textStyle');

    if (textAttrs.color) {
        activeTextColor.value = textAttrs.color;
    } else {
        activeTextColor.value = DEFAULT_TEXT_COLOR;
    }
};

const isHighlightActive = computed(() => {
    if (!editor.value) return false;
    return editor.value.isActive('textStyle', { color: activeHighlightColor.value }) || activeHighlightColor.value !== DEFAULT_HIGHLIGHT_COLOR;
});

const isTextColorSelected = computed(() => {
    if (!editor.value) return false;
    return editor.value.isActive('textStyle', { color: activeTextColor.value }) || activeTextColor.value !== DEFAULT_TEXT_COLOR;
});

const resetHighlightColor = () => {
    editor.value.chain().focus().unsetHighlight().run();
    activeHighlightColor.value = DEFAULT_HIGHLIGHT_COLOR;
};

const resetTextColor = () => {
    editor.value.chain().focus().unsetColor().run();
    activeTextColor.value = DEFAULT_TEXT_COLOR;
};

// Toggle alignment helper
const toggleAlignment = (alignment) => {
    if (!editor.value) return;

    if (editor.value.isActive({ textAlign: alignment })) {
        editor.value.chain().focus().unsetTextAlign().run();
    } else {
        editor.value.chain().focus().setTextAlign(alignment).run();
    }
};

const clearFormatting = () => {
    editor.value.chain()
        .focus()
        .unsetAllMarks() 
        .clearNodes()   
        .run();
};

defineExpose({
    clearContent: () => editor.value?.commands.setContent("")
});
</script>

<template>
    <div class="editor-container">
        <!-- Heading select -->
        <div v-if="editor" class="tiptap-toolbar" title="Heading">
            <div class="toolbar-dropdown-group">
                <select :value="currentHeadingValue" @change="handleHeadingChange" class="heading-select">
                    <option value="paragraph">Normal</option>
                    <option value="1">Header</option>
                    <option value="2">Subheader</option>
                </select>
                <i class="pi pi-chevron-down select-chevron"></i>
            </div>

            <div class="divider"></div>

            <!-- Font styling -->
            <button type="button" @click="editor.chain().focus().toggleBold().run()"
                :class="{ 'active': editor.isActive('bold') }"
                title="Bold">
                <i class="bi bi-type-bold fs-5"></i>
            </button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()"
                :class="{ 'active': editor.isActive('italic') }"
                title="Italic">
                <i class="bi bi-type-italic fs-5"></i>
            </button>
            <button @click="editor.chain().focus().toggleStrike().run()" :class="{ 'is-active': editor.isActive('strike') }"
                    title="Strike">
                <i class="bi bi-type-strikethrough fs-5"></i>
            </button>
            <button type="button" @click="editor.chain().focus().toggleUnderline().run()"
                :class="{ 'active': editor.isActive('underline') }"
                title="Underline">
                <i class="bi bi-type-underline fs-5"></i>
            </button>

            <div class="divider"></div>

            <!-- Text coloring -->
            <div class="color-text">
                <button type="button" class="color-btn" @click="$refs.textColorInput.click()" title="Text Color">
                    <i class="bi bi-type fs-5" :style="{ borderBottom: '4px ' + activeTextColor + ' solid', borderRadius: '4px'}"></i>
                </button>
                <input type="color" ref="textColorInput" @input="updateTextColor" :value="activeTextColor"
                    class="hidden-input" />
                <button v-if="isTextColorSelected" type="button" class="reset-color" @click="resetTextColor"><i
                        class="bi bi-x"></i></button>
            </div>

            <!-- Highlight -->
            <div class="color-highlight">
                <button type="button" class="color-btn" @click="$refs.highlightInput.click()" title="Highlight Color">
                    <i class="bi bi-highlighter fs-5"
                        :style="{ borderBottom: '4px ' + activeHighlightColor + ' solid', borderRadius: '4px' }"></i>
                </button>
                <input type="color" ref="highlightInput" @input="updateHighlightColor" :value="activeHighlightColor"
                    class="hidden-input" />
                <button v-if="isHighlightActive" type="button" class="reset-color" @click="resetHighlightColor"><i
                        class="bi bi-x"></i></button>
            </div>

            <div class="divider"></div>

            <!-- Image upload -->
            <button type="button" @click="triggerImageUpload" title="Upload Image">
                <i class="bi bi-card-image fs-5"></i>
            </button>

            <!-- Hyperlink insertion -->
        <div class="toolbar-group relative" style="display: flex; align-items: center; gap: 4px;">
            <button 
                type="button" 
                @click="openLinkMenu" 
                :class="{ 'active': editor.isActive('link') }"
                title="Insert Link"
            >
                <i class="bi bi-link-45deg fs-5"></i>
            </button>

            <button 
                v-if="editor.isActive('link')" 
                type="button" 
                class="reset-color" 
                @click="removeLink" 
                title="Remove Link"
            >
                <i class="bi bi-x"></i>
            </button>

            <div v-if="showLinkMenu" class="link-floating-menu">
                <input 
                    v-model="linkUrl" 
                    placeholder="https://example.com" 
                    @keyup.enter="applyLink"
                    @keyup.esc="showLinkMenu = false"
                    class="link-input"
                    ref="linkInput"
                />
                <button type="button" @click="applyLink" class="apply-btn">
                    <i class="bi bi-check-lg"></i>
                </button>
            </div>
        </div>

            <div class="divider"></div>

            <!-- Left -->
            <button type="button" @click="toggleAlignment('left')"
                :class="{ 'active': editor.isActive({ textAlign: 'left' }) }"
                title="Left Alignment">
                <i class="bi bi-text-left fs-5"></i>
            </button>
            <!-- Center -->
            <button type="button" @click="toggleAlignment('center')"
                :class="{ 'active': editor.isActive({ textAlign: 'center' }) }"
                title="Center Alignment">
                <i class="bi bi-text-center fs-5"></i>
            </button>
            <!-- Right -->
            <button type="button" @click="toggleAlignment('right')"
                :class="{ 'active': editor.isActive({ textAlign: 'right' }) }"
                title="Right Alignment">
                <i class="bi bi-text-right fs-5"></i>
            </button>

            <div class="divider"></div>

            <!-- Clear formatting -->
            <button 
                type="button" 
                @click="clearFormatting" 
                title="Clear All Formatting"
            >
                <i class="bi bi-eraser fs-5"></i>
            </button>
        </div>


        <!-- Typing section -->
        <editor-content :editor="editor" class="tiptap-content" />
    </div>
</template>

<style scoped>
.editor-container {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
    background: white;
    min-height: 350px;
}

.tiptap-toolbar {
    background: #f8fafc;
    border-bottom: 1px solid #cbd5e1;
    padding: 6px 8px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}

.tiptap-toolbar button {
    background: none;
    border: 1px solid transparent;
    width: 34px;
    height: 34px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 4px;
    color: #64748b;
}

.tiptap-toolbar button:hover {
    background: #007c8a0c;
    color: #0f172a;
}

.tiptap-toolbar button.active {
    background: #007c8a25;
    color: #2E6C44;
}

.color-text,
.color-highlight {
    display: flex;
    align-items: center;
    border-radius: 6px;
    padding: 0 2px;
    min-width: 40px;
    transition: all 0.2s ease;
}

.color-picker-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.hidden-input {
    position: absolute;
    width: 0;
    height: 0;
    opacity: 0;
    pointer-events: none;
}

.color-indicator {
    width: 16px;
    height: 3px;
    border-radius: 5px;
    margin-top: 1px;
    border: 2px solid rgba(0, 0, 0, 0.1);
}

.relative { position: relative; }

.link-floating-menu {
  position: absolute;
  top: 110%;
  left: 0;
  z-index: 50;
  display: flex;
  background: white;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 4px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  gap: 4px;
}

.link-input {
  border: none;
  background: #f1f5f9;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.85rem;
  outline: none;
  min-width: 180px;
}

.apply-btn {
  background: #2E6C44 !important;
  color: white !important;
  width: 28px !important;
  height: 28px !important;
}

.toolbar-dropdown-group {
    position: relative;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
}

.toolbar-dropdown-group:hover {
    border: none;
    background-color: #007c8a25;
    border-radius: 8px;
    transition: all 0.7 ease;

    .select-chevron {
        transition: all 0.2 ease;
        text-shadow: 0 5px 1px black;
    }
}

.heading-select {
    appearance: none;
    background: transparent;
    border: none;
    padding: 4px 24px 4px 8px;
    font-weight: 700;
    font-size: 0.85rem;
    color: #2a3444;
    cursor: pointer;
    outline: none;

    option {
        background: #6ebe4b48;
        font-weight: 500;
    }
}

.select-chevron {
    position: absolute;
    right: 8px;
    pointer-events: none;
    color: #94a3b8;
    font-size: 0.7rem;
    transition: all 0.2 ease;
}

.divider {
    width: 1px;
    height: 18px;
    background: #cbd5e1;
    margin: 0 8px;
}

.reset-color {
    width: 20px !important;
    height: 20px !important;
    font-size: 1rem !important;
    padding: 0 !important;
    color: #94a3b8 !important;
}

.reset-color:hover {
    color: #030303 !important;
    background: none !important;
}


:deep(.tiptap) {
    padding: 1.25rem;
    outline: none;
    min-height: 350px;
}
</style>

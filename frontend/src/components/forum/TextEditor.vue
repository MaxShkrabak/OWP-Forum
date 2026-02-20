<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
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
  placeholder: String,
  isUploading: Boolean
});

const emit = defineEmits(['update:modelValue', 'update:isUploading']);

// Defaults
const DEFAULT_TEXT_COLOR = '#000000';
const DEFAULT_HIGHLIGHT_COLOR = '#ffffff';

// State
const showLinkMenu = ref(false);
const linkUrl = ref('');
const linkGroupRef = ref(null);
const activeTextColor = ref(DEFAULT_TEXT_COLOR);
const activeHighlightColor = ref(DEFAULT_HIGHLIGHT_COLOR);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({ underline: false, link: false, }),
        Underline,
        TextStyle,
        Color,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Highlight.configure({ multicolor: true }),
        Image.configure({ inline: true }),
        Link.configure({ openOnClick: false, HTMLAttributes: { class: 'my-custom-link' } }),
    ],
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
    onSelectionUpdate: updateActiveAttributes
});

function updateActiveAttributes() {
    if (!editor.value) return;
    activeHighlightColor.value = editor.value.getAttributes('highlight').color || DEFAULT_HIGHLIGHT_COLOR;
    activeTextColor.value = editor.value.getAttributes('textStyle').color || DEFAULT_TEXT_COLOR;
}

const openLinkMenu = () => {
    linkUrl.value = editor.value.getAttributes('link').href || '';
    showLinkMenu.value = !showLinkMenu.value;
};

const removeLink = () => {
    if (editor.value.isActive('link')) {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    }
    linkUrl.value = '';
    showLinkMenu.value = false;
};

const applyLink = () => {
    if (!linkUrl.value) return removeLink();
    
    let url = linkUrl.value;
    if (!/^https?:\/\//i.test(url) && !url.startsWith('/')) url = 'https://' + url;
    
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    showLinkMenu.value = false;
};

// Formatting Helpers
const handleHeadingChange = (event) => {
    const val = event.target.value;
    const chain = editor.value.chain().focus();
    val === "paragraph" ? chain.setParagraph().run() : chain.toggleHeading({ level: parseInt(val) }).run();
};

const currentHeadingValue = computed(() => {
    if (editor.value?.isActive('heading', { level: 1 })) return "1";
    if (editor.value?.isActive('heading', { level: 2 })) return "2";
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

const resetHighlightColor = () => {
    editor.value.chain().focus().unsetHighlight().run();
    activeHighlightColor.value = DEFAULT_HIGHLIGHT_COLOR;
};

const resetTextColor = () => {
    editor.value.chain().focus().unsetColor().run();
    activeTextColor.value = DEFAULT_TEXT_COLOR;
};

const isHighlightActive = computed(() => activeHighlightColor.value !== DEFAULT_HIGHLIGHT_COLOR);
const isTextColorSelected = computed(() => activeTextColor.value !== DEFAULT_TEXT_COLOR);

const triggerImageUpload = async () => {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";
    input.onchange = async () => {
        const file = input.files?.[0];
        if (file) {
            emit('update:isUploading', true);
            try {
                const data = await uploadImage(file);
                editor.value.chain().focus().setImage({ src: data.url }).run();
            } catch (err) { 
                console.error(err); 
            } finally {
                emit('update:isUploading', false); 
            }
        }
    };
    input.click();
};

const toggleAlignment = (alignment) => {
    const chain = editor.value.chain().focus();
    editor.value.isActive({ textAlign: alignment }) ? chain.unsetTextAlign().run() : chain.setTextAlign(alignment).run();
};

const handleClickOutside = (e) => {
    if (showLinkMenu.value && linkGroupRef.value && !linkGroupRef.value.contains(e.target)) {
        showLinkMenu.value = false;
    }
};

const clearFormatting = () => {
    editor.value.chain()
        .focus()
        .clearNodes()
        .unsetAllMarks()
        .run();
    
    activeTextColor.value = DEFAULT_TEXT_COLOR;
    activeHighlightColor.value = DEFAULT_HIGHLIGHT_COLOR;
};

onMounted(() => window.addEventListener('click', handleClickOutside));
onUnmounted(() => window.removeEventListener('click', handleClickOutside));

defineExpose({ clearContent: () => editor.value?.commands.setContent("") });
</script>

<template>
    <div class="editor-container">
        <div v-if="editor" class="tiptap-toolbar">
            <!-- Heading select -->
            <div class="toolbar-dropdown-group" title="Heading">
                <select :value="currentHeadingValue" @change="handleHeadingChange" class="heading-select">
                    <option value="1">Header</option>
                    <option value="2">Subheader</option>
                    <option value="paragraph">Normal</option>
                </select>
                <i class="pi pi-chevron-down select-chevron"></i>
            </div>

            <div class="divider"></div>

            <!-- Text styling -->
            <button type="button" @click="editor.chain().focus().toggleBold().run()"
                :class="{ 'active': editor.isActive('bold') }" title="Bold">
                <i class="bi bi-type-bold fs-5"></i>
            </button>
            <button type="button" @click="editor.chain().focus().toggleItalic().run()"
                :class="{ 'active': editor.isActive('italic') }" title="Italic">
                <i class="bi bi-type-italic fs-5"></i>
            </button>
            <button @click="editor.chain().focus().toggleStrike().run()"
                :class="{ 'active': editor.isActive('strike') }" title="Strike">
                <i class="bi bi-type-strikethrough fs-5"></i>
            </button>
            <button type="button" @click="editor.chain().focus().toggleUnderline().run()"
                :class="{ 'active': editor.isActive('underline') }" title="Underline">
                <i class="bi bi-type-underline fs-5"></i>
            </button>

            <div class="divider"></div>

            <!-- Text color and highlight -->
            <div class="color-group color-text">
                <button type="button" class="group-main-btn" @click="$refs.textColorInput.click()" title="Text Color">
                    <i class="bi bi-type fs-5" :style="{ borderBottom: '4px ' + activeTextColor + ' solid', borderRadius: '4px' }"></i>
                </button>
                <input type="color" ref="textColorInput" @input="updateTextColor" :value="activeTextColor" class="hidden-input" />
                <button v-if="isTextColorSelected" type="button" class="reset-color" @click="resetTextColor">
                    <i class="bi bi-x"></i>
                </button>
            </div>

            <div class="color-group color-highlight">
                <button type="button" class="group-main-btn" @click="$refs.highlightInput.click()" title="Highlight Color">
                    <i class="bi bi-highlighter fs-5" :style="{ borderBottom: '4px ' + activeHighlightColor + ' solid', borderRadius: '4px' }"></i>
                </button>
                <input type="color" ref="highlightInput" @input="updateHighlightColor" :value="activeHighlightColor" class="hidden-input" />
                <button v-if="isHighlightActive" type="button" class="reset-color" @click="resetHighlightColor">
                    <i class="bi bi-x"></i>
                </button>
            </div>

            <div class="divider"></div>

            <button type="button" @click="triggerImageUpload" title="Upload Image">
                <i class="bi bi-card-image fs-5"></i>
            </button>

            <!-- Hyperlink insertion -->
            <div class="link-group relative" :class="{ 'active': editor.isActive('link') }" ref="linkGroupRef">
                <button type="button" class="group-main-btn" @click="openLinkMenu" :class="{ 'active': editor.isActive('link') }" title="Insert Link">
                    <i class="bi bi-link-45deg fs-5"></i>
                </button>
                <button v-if="editor.isActive('link')" type="button" class="reset-color" @click="removeLink" title="Remove Link">
                    <i class="bi bi-x"></i>
                </button>

                <div v-if="showLinkMenu" class="link-floating-menu">
                    <input v-model="linkUrl" placeholder="https://example.com" @keyup.enter="applyLink" @keyup.esc="showLinkMenu = false" class="link-input"/>
                    <button type="button" @click="applyLink" class="apply-btn">
                        <i class="bi bi-check-lg"></i>
                    </button>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Alignment -->
            <button type="button" @click="toggleAlignment('left')" :class="{ 'active': editor.isActive({ textAlign: 'left' }) }" title="Left Alignment">
                <i class="bi bi-text-left fs-5"></i>
            </button>
            <button type="button" @click="toggleAlignment('center')" :class="{ 'active': editor.isActive({ textAlign: 'center' }) }" title="Center Alignment">
                <i class="bi bi-text-center fs-5"></i>
            </button>
            <button type="button" @click="toggleAlignment('right')" :class="{ 'active': editor.isActive({ textAlign: 'right' }) }" title="Right Alignment">
                <i class="bi bi-text-right fs-5"></i>
            </button>

            <div class="divider"></div>

            <!-- Clear styling -->
            <button type="button" @click="clearFormatting" title="Clear All Formatting">
                <i class="bi bi-eraser fs-5"></i>
            </button>
        </div>

        <editor-content :editor="editor" class="tiptap-content" />
    </div>
</template>

<style scoped>
.editor-container {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
    background: white;
    height: 450px; 
    display: flex;
    flex-direction: column;
    min-height: 450px;
}

.tiptap-toolbar {
    background: #f8fafc;
    border-bottom: 1px solid #cbd5e1;
    padding: 6px 8px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    flex-shrink: 0;
}

.tiptap-toolbar button,
.heading-select,
.color-group,
.link-group {
    transition: all 0.2s ease;
}

.tiptap-toolbar button {
    background: none;
    border: 1px solid transparent;
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 4px;
    color: #475569;
}

.tiptap-toolbar button:hover:not(.group-main-btn):not(.reset-color):not(.apply-btn),
.color-group:hover,
.link-group:hover,
.heading-select:hover {
    background: #007c8a25;
    color: #0f172a;
}

.tiptap-toolbar button.active,
.link-group.active {
    background: #78caa970;
    color: #2E6C44;
    border: 1px rgba(24, 97, 46, 0.562) solid;
}

.color-group, .link-group {
    display: flex;
    align-items: center;
    border-radius: 6px;
    padding: 0 2px;
}

.group-main-btn {
    background: transparent !important;
    border: none !important;
    width: 30px !important;
}

.reset-color {
    background: transparent !important;
    border: none !important;
    width: 22px !important;
    height: 34px !important;
    color: #94a3b8 !important;
    padding: 0 !important;
}

.reset-color:hover {
    color: #000000 !important;
}

.hidden-input {
    position: absolute;
    width: 0;
    height: 0;
    opacity: 0;
    pointer-events: none;
}

.divider {
    width: 1px;
    height: 18px;
    background: #cbd5e1;
    margin: 0 4px;
}

.relative { position: relative; }

/* Hyperlink dropdown */
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
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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

/* Dropdown */
.toolbar-dropdown-group {
    position: relative;
    display: flex;
    align-items: center;
}
.heading-select {
    appearance: none;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 6px 32px 6px 12px;
    font-weight: 600;
    font-size: 0.85rem;
    color: #475569;
    cursor: pointer;
    outline: none;
}
.select-chevron {
    position: absolute;
    right: 10px;
    pointer-events: none;
    color: #64748b;
    font-size: 0.7rem;
    transition: transform 0.3s ease;
}
.heading-select:focus + .select-chevron {
    transform: rotate(180deg);
    color: #2E6C44;
}

:deep(.tiptap-content) {
    flex-grow: 1;
    overflow-y: auto;
    min-height: 450px;
}
:deep(.tiptap) {
    padding: 1.25rem;
    outline: none;
    min-height: 100%;
}
</style>
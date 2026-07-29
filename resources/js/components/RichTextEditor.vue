<script setup lang="ts">
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Bold, Italic, Link2, Link2Off, List, ListOrdered, Redo2, Undo2 } from 'lucide-vue-next';
import { onBeforeUnmount, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
        disabled?: boolean;
    }>(),
    { placeholder: 'Write your message…', disabled: false },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = useEditor({
    content: props.modelValue,
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({
            heading: false,
            codeBlock: false,
            code: false,
            blockquote: false,
            horizontalRule: false,
            link: {
                openOnClick: false,
                autolink: true,
                defaultProtocol: 'https',
            },
        }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: {
            class: 'rte-content focus:outline-none',
        },
    },
    onUpdate: ({ editor: instance }) => {
        emit('update:modelValue', instance.isEmpty ? '' : instance.getHTML());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && value !== editor.value.getHTML()) {
            editor.value.commands.setContent(value, { emitUpdate: false });
        }
    },
);

watch(
    () => props.disabled,
    (disabled) => editor.value?.setEditable(!disabled),
);

onBeforeUnmount(() => editor.value?.destroy());

function setLink() {
    if (!editor.value) {
        return;
    }

    const previous = editor.value.getAttributes('link').href as string | undefined;
    const url = window.prompt('Link URL', previous ?? 'https://');

    if (url === null) {
        return;
    }

    if (url.trim() === '') {
        editor.value.chain().focus().unsetLink().run();

        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

type ToolbarButton = {
    icon: typeof Bold;
    title: string;
    isActive?: () => boolean;
    run: () => void;
};

const buttons: ToolbarButton[] = [
    {
        icon: Bold,
        title: 'Bold',
        isActive: () => editor.value?.isActive('bold') ?? false,
        run: () => editor.value?.chain().focus().toggleBold().run(),
    },
    {
        icon: Italic,
        title: 'Italic',
        isActive: () => editor.value?.isActive('italic') ?? false,
        run: () => editor.value?.chain().focus().toggleItalic().run(),
    },
    {
        icon: List,
        title: 'Bullet list',
        isActive: () => editor.value?.isActive('bulletList') ?? false,
        run: () => editor.value?.chain().focus().toggleBulletList().run(),
    },
    {
        icon: ListOrdered,
        title: 'Numbered list',
        isActive: () => editor.value?.isActive('orderedList') ?? false,
        run: () => editor.value?.chain().focus().toggleOrderedList().run(),
    },
    {
        icon: Link2,
        title: 'Add link',
        isActive: () => editor.value?.isActive('link') ?? false,
        run: setLink,
    },
    {
        icon: Link2Off,
        title: 'Remove link',
        run: () => editor.value?.chain().focus().unsetLink().run(),
    },
    {
        icon: Undo2,
        title: 'Undo',
        run: () => editor.value?.chain().focus().undo().run(),
    },
    {
        icon: Redo2,
        title: 'Redo',
        run: () => editor.value?.chain().focus().redo().run(),
    },
];
</script>

<template>
    <div
        class="border-input bg-background ring-offset-background focus-within:ring-ring rounded-md border focus-within:ring-2 focus-within:ring-offset-2"
        :class="{ 'cursor-not-allowed opacity-70': disabled }"
    >
        <div class="flex flex-wrap items-center gap-0.5 border-b px-1.5 py-1">
            <button
                v-for="button in buttons"
                :key="button.title"
                type="button"
                :title="button.title"
                :disabled="disabled"
                class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:pointer-events-none"
                :class="{ 'bg-muted text-foreground': button.isActive?.() }"
                @mousedown.prevent
                @click="button.run()"
            >
                <component :is="button.icon" class="h-3.5 w-3.5" />
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<style scoped>
:deep(.rte-content) {
    min-height: 10rem;
    max-height: 24rem;
    overflow-y: auto;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.6;
}

:deep(.rte-content p) {
    margin-bottom: 0.6em;
}

:deep(.rte-content ul) {
    list-style: disc;
    padding-left: 1.25rem;
    margin-bottom: 0.6em;
}

:deep(.rte-content ol) {
    list-style: decimal;
    padding-left: 1.25rem;
    margin-bottom: 0.6em;
}

:deep(.rte-content a) {
    text-decoration: underline;
    text-underline-offset: 2px;
}

:deep(.rte-content p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
    color: hsl(var(--muted-foreground, 220 9% 46%) / 0.7);
}
</style>

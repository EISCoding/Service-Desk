// WYSIWYG editor bound to a Livewire property, see resources/views/components/ui/rich-editor.blade.php.
// The stored HTML is sanitized on the server (App\Support\Html); only the editor's formatting survives.

const normalizeUrl = (url) => {
    const value = url.trim();
    if (! value || /^(https?:|mailto:|tel:)/i.test(value)) return value;
    return value.includes('@') && ! value.includes('/') ? `mailto:${value}` : `https://${value}`;
};

export default (model, options = {}) => {
    // Kept outside of Alpine's reactive data: TipTap does not work through proxies.
    let editor = null;
    let commitTimer = null;
    const chips = Boolean(options.placeholders);
    let withChips = (html) => html;
    const prepare = (html) => (chips ? withChips(html || '') : html || '');

    return {
        value: model,
        active: {},
        heading: 'paragraph',
        color: null,
        align: 'left',
        empty: true,
        linkOpen: false,
        linkUrl: '',
        linkText: '',
        linkButton: false,
        linkNeedsText: false,

        async init() {
            // Outside access, e.g. the composer inserting a text snippet at the cursor.
            this.$root.richEditor = {
                insert: (html) => editor?.chain().focus().insertContent(prepare(html)).run(),
                focus: () => editor?.commands.focus('end'),
            };

            // TipTap is only loaded on pages with an editor.
            const tiptap = await import('./tiptap');
            withChips = tiptap.withChips;

            editor = tiptap.createEditor({
                element: this.$refs.content,
                content: this.value || '',
                chips,
                placeholder: options.placeholder || '',
                attributes: {
                    class: 'message-body rich-editor-content',
                    role: 'textbox',
                    'aria-multiline': 'true',
                    'aria-label': options.label || options.placeholder || 'Text',
                    ...(options.invalid ? { 'aria-invalid': 'true' } : {}),
                    ...(options.describedBy ? { 'aria-describedby': options.describedBy } : {}),
                },
                onUpdate: () => {
                    this.value = editor.isEmpty ? '' : editor.getHTML();
                    // wire:model.live: send the text shortly after typing stops (e.g. for a live preview).
                    if (options.live) {
                        clearTimeout(commitTimer);
                        commitTimer = setTimeout(() => this.$wire.$commit(), 400);
                    }
                },
                onTransaction: () => this.sync(),
            });

            this.$watch('value', (value) => {
                const current = editor.isEmpty ? '' : editor.getHTML();
                if ((value || '') !== current) {
                    editor.commands.setContent(prepare(value), { emitUpdate: false });
                }
            });

            this.sync();
        },

        destroy() {
            editor?.destroy();
            editor = null;
        },

        sync() {
            if (! editor) return;
            const marks = ['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'blockquote', 'link'];
            this.active = Object.fromEntries(marks.map((name) => [name, editor.isActive(name)]));
            this.active.button = editor.isActive('link', { type: 'button' });
            this.heading = editor.isActive('heading', { level: 2 }) ? 'h2' : editor.isActive('heading', { level: 3 }) ? 'h3' : 'paragraph';
            this.color = editor.getAttributes('textStyle').color || null;
            this.align = ['center', 'right', 'justify'].find((value) => editor.isActive({ textAlign: value })) || 'left';
            this.empty = editor.isEmpty;
        },

        run(command, argument = null) {
            const chain = editor.chain().focus();
            const commands = {
                bold: () => chain.toggleBold(),
                italic: () => chain.toggleItalic(),
                underline: () => chain.toggleUnderline(),
                bulletList: () => chain.toggleBulletList(),
                orderedList: () => chain.toggleOrderedList(),
                blockquote: () => chain.toggleBlockquote(),
                rule: () => chain.setHorizontalRule(),
                heading: () => (argument === 'paragraph' ? chain.setParagraph() : chain.setHeading({ level: argument === 'h2' ? 2 : 3 })),
                align: () => chain.setTextAlign(argument),
                color: () => (argument ? chain.setColor(argument) : chain.unsetColor()),
                placeholder: () => chain.insertContent({ type: 'placeholderChip', attrs: { key: argument } }).insertContent(' '),
            };
            commands[command]?.().run();
        },

        openLink(button = false) {
            const attributes = editor.getAttributes('link');
            if (editor.isActive('link')) {
                editor.chain().focus().extendMarkRange('link').run();
            }
            this.linkUrl = attributes.href || '';
            this.linkButton = button || attributes.type === 'button';
            this.linkNeedsText = editor.state.selection.empty && ! editor.isActive('link');
            this.linkText = '';
            this.linkOpen = true;
            this.$nextTick(() => this.$refs.linkUrl?.focus());
        },

        applyLink() {
            const href = normalizeUrl(this.linkUrl);
            const type = this.linkButton ? 'button' : null;
            const chain = editor.chain().focus();

            if (! href) {
                chain.extendMarkRange('link').unsetLink().run();
            } else if (this.linkNeedsText) {
                const text = this.linkText.trim() || href.replace(/^mailto:/, '');
                chain.insertContent({ type: 'text', text, marks: [{ type: 'link', attrs: { href, type } }] }).insertContent(' ').run();
            } else {
                chain.extendMarkRange('link').setLink({ href, type }).run();
            }
            this.linkOpen = false;
        },

        removeLink() {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            this.linkOpen = false;
        },
    };
};

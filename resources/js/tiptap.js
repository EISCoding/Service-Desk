// TipTap setup of the WYSIWYG editor; loaded on demand by resources/js/editor.js.
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import { Color, TextStyle } from '@tiptap/extension-text-style';
import { Placeholder } from '@tiptap/extensions';

const PLACEHOLDER_PATTERN = /\{\s*([a-z]+\.[a-z]+)\s*\}/gi;

// Placeholders like {ticket.nummer} as chips that move and delete as one piece.
const PlaceholderChip = Node.create({
    name: 'placeholderChip',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,

    addAttributes() {
        return {
            key: {
                default: '',
                parseHTML: (element) => (element.textContent || '').replace(/[{}\s]/g, '').toLowerCase(),
                renderHTML: () => ({}),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-type="placeholder"]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return ['span', mergeAttributes(HTMLAttributes, { 'data-type': 'placeholder' }), `{${node.attrs.key}}`];
    },

    renderText({ node }) {
        return `{${node.attrs.key}}`;
    },
});

// Links can be shown as a button (data-type="button"); mails get the button styles inline.
const ButtonLink = Link.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            type: {
                default: null,
                parseHTML: (element) => (element.getAttribute('data-type') === 'button' ? 'button' : null),
                renderHTML: (attributes) => (attributes.type === 'button' ? { 'data-type': 'button' } : {}),
            },
        };
    },
});

// Plain {placeholders} in stored HTML become chips; only text outside of tags is touched.
export function withChips(html) {
    return (html || '').split(/(<[^>]*>)/).map((part, index, parts) => {
        if (part.startsWith('<') || /data-type="placeholder"/.test(parts[index - 1] || '')) {
            return part;
        }
        return part.replace(PLACEHOLDER_PATTERN, (match, key) => `<span data-type="placeholder">{${key.toLowerCase()}}</span>`);
    }).join('');
}

export function createEditor({ element, content, chips, placeholder, attributes, onUpdate, onTransaction }) {
    return new Editor({
        element,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                code: false,
                codeBlock: false,
                strike: false,
                link: false,
            }),
            ButtonLink.configure({
                openOnClick: false,
                autolink: true,
                defaultProtocol: 'https',
                protocols: ['mailto', 'tel'],
                HTMLAttributes: { rel: null, target: null, class: null },
            }),
            TextStyle,
            Color,
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Placeholder.configure({ placeholder }),
            ...(chips ? [PlaceholderChip] : []),
        ],
        content: chips ? withChips(content) : content,
        editorProps: { attributes },
        onUpdate,
        onTransaction,
    });
}

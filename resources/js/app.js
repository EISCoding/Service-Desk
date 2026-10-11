// Rich text editor for replies and notes (<trix-editor>).
import 'trix';

// Alpine.js ships with Livewire; register Alpine extensions in the `alpine:init` hook.

// Uploads in the editor are not used (attachments have their own field).
document.addEventListener('trix-file-accept', (event) => event.preventDefault());

// Keyboard shortcuts in /intern: J/K move through the ticket list, R reply, N internal note, E status.
document.addEventListener('keydown', (event) => {
    const target = event.target;
    const typing = target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT', 'TRIX-EDITOR'].includes(target.tagName);
    if (typing || event.metaKey || event.ctrlKey || event.altKey || ! location.pathname.startsWith('/intern')) {
        return;
    }

    const key = event.key.toLowerCase();

    if (key === 'j' || key === 'k') {
        const rows = [...document.querySelectorAll('ul[aria-label="Tickets"] > li > a')];
        if (! rows.length) return;
        const current = rows.findIndex((row) => row.getAttribute('aria-current') === 'page');
        const next = current === -1 ? 0 : Math.min(Math.max(current + (key === 'j' ? 1 : -1), 0), rows.length - 1);
        if (next !== current) {
            event.preventDefault();
            rows[next].scrollIntoView({ block: 'nearest' });
            rows[next].click();
        }
    } else if ((key === 'r' || key === 'n') && document.querySelector('[data-composer]')) {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('composer-focus', { detail: { mode: key === 'r' ? 'reply' : 'note' } }));
    } else if (key === 'e') {
        const status = document.querySelector('[data-shortcut="status"]:not([disabled])');
        if (! status) return;
        event.preventDefault();
        status.focus();
        try {
            status.showPicker();
        } catch (e) {
            // Not supported everywhere; the focused select opens with the arrow keys.
        }
    }
});

document.addEventListener('alpine:init', () => {
    // Trix bound to a Livewire property, see resources/views/components/ui/rich-editor.blade.php
    window.Alpine.data('richEditor', (model) => ({
        value: model,

        init() {
            const element = this.$refs.editor;
            const load = () => {
                if (element.editor && (this.value || '') !== element.value) {
                    element.editor.loadHTML(this.value || '');
                }
            };

            element.addEventListener('trix-initialize', load);
            element.addEventListener('trix-change', () => (this.value = element.value));
            this.$watch('value', load);
            load();
        },
    }));

    // Appearance switch (System/Hell/Dunkel), see resources/views/components/theme-switcher.blade.php
    window.Alpine.data('themeSwitcher', (updateUrl = null) => ({
        current: document.documentElement.dataset.theme || 'system',

        set(theme) {
            this.current = theme;
            window.applyTheme(theme);

            try {
                localStorage.setItem('theme', theme);
            } catch (e) {
                // Storage may be unavailable (private mode); the setting still applies to this page.
            }

            if (updateUrl) {
                fetch(updateUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ theme }),
                });
            }
        },
    }));

    // Toast notifications, see resources/views/components/ui/toasts.blade.php
    window.Alpine.data('toasts', (initial = null) => ({
        items: [],
        nextId: 1,

        init() {
            if (initial) {
                this.add(initial);
            }
        },

        add(toast) {
            // Livewire dispatches named arguments as an object; also accept a plain string.
            const payload = typeof toast === 'string' ? { message: toast } : toast || {};
            if (!payload.message) {
                return;
            }

            const item = {
                id: this.nextId++,
                message: payload.message,
                type: payload.type || 'success',
                visible: true,
            };
            this.items.push(item);
            setTimeout(() => this.remove(item.id), payload.duration || 4000);
        },

        remove(id) {
            const item = this.items.find((toast) => toast.id === id);
            if (!item) {
                return;
            }
            item.visible = false;
            setTimeout(() => (this.items = this.items.filter((toast) => toast.id !== id)), 200);
        },
    }));
});

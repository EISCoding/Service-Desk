// Alpine.js ships with Livewire; register Alpine extensions in the `alpine:init` hook.

document.addEventListener('alpine:init', () => {
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

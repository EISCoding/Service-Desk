// Alpine.js ships with Livewire; register Alpine extensions in the `alpine:init` hook.

document.addEventListener('alpine:init', () => {
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

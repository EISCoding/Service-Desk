// WYSIWYG editor for replies, notes, templates and signatures.
import richEditor from './editor';

// Alpine.js ships with Livewire; register Alpine extensions in the `alpine:init` hook.

// A record deleted meanwhile (e.g. a ticket open while a colleague deletes it) makes live
// updates fail with 404: reload instead of showing the error page in a modal. The page
// routes send missing tickets back to the list.
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 404) {
                preventDefault();
                window.location.reload();
            }
        });
    });
});

// Web push: service worker for notifications (staff area) and the "enable on this device" switch.
const pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

if (pushSupported && location.pathname.startsWith('/intern')) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}

function base64ToBytes(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (char) => char.charCodeAt(0));
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('webPush', (publicKey) => ({
        supported: pushSupported,
        ios: /iPhone|iPad/.test(navigator.userAgent),
        permission: pushSupported ? Notification.permission : 'default',
        subscribed: false,
        busy: false,
        error: '',

        async init() {
            if (! this.supported) return;
            const registration = await navigator.serviceWorker.register('/sw.js');
            this.subscribed = Boolean(await registration.pushManager.getSubscription());
        },

        async enable() {
            this.busy = true;
            this.error = '';
            try {
                this.permission = await Notification.requestPermission();
                if (this.permission !== 'granted') return;
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64ToBytes(publicKey) });
                await this.$wire.subscribe(subscription.toJSON(), navigator.userAgent);
                this.subscribed = true;
            } catch (error) {
                this.error = 'Aktivieren hat nicht geklappt: ' + (error?.message || error);
            } finally {
                this.busy = false;
            }
        },

        async disable() {
            this.busy = true;
            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                if (subscription) {
                    await this.$wire.unsubscribe(subscription.endpoint);
                    await subscription.unsubscribe();
                }
                this.subscribed = false;
            } finally {
                this.busy = false;
            }
        },
    }));
});

// Keyboard shortcuts in /intern: J/K move through the ticket list, R reply, N internal note, E status.
document.addEventListener('keydown', (event) => {
    const target = event.target;
    const typing = target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
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
    } else if (key === 'f' && /^\/intern\/tickets\/\d+/.test(location.pathname)) {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('toggle-ticket-focus'));
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
    window.Alpine.data('richEditor', richEditor);

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

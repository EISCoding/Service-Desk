// Service worker of the ServiceDesk: shows web push notifications and opens the ticket on click.
// No offline caching; the app always loads from the server.

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: event.data ? event.data.text() : '' };
    }

    event.waitUntil(self.registration.showNotification(data.title || 'ServiceDesk', {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192.png',
        badge: '/icons/badge-96.png',
        tag: data.tag,
        renotify: Boolean(data.tag),
        data: { url: data.url || '/intern' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/intern', self.location.origin).href;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                await client.focus();
                return 'navigate' in client ? client.navigate(url) : undefined;
            }
        }

        return self.clients.openWindow(url);
    })());
});

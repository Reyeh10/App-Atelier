// Notifications push de l'atelier : affichées par l'appareil (avec sa sonnerie),
// même quand le système est fermé. Un clic ouvre la page concernée.

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    var donnees = {};
    try {
        donnees = event.data ? event.data.json() : {};
    } catch (e) {
        donnees = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(self.registration.showNotification(donnees.title || 'App Atelier', {
        body: donnees.body || '',
        icon: donnees.icon,
        badge: donnees.icon,
        tag: donnees.tag,
        renotify: true,
        vibrate: [200, 100, 200],
        data: { url: donnees.url || '/' }
    }));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = new URL(event.notification.data.url, self.location.origin).href;

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (fenetres) {
        // Un onglet du système est déjà ouvert : on l'utilise
        for (var i = 0; i < fenetres.length; i++) {
            var f = fenetres[i];
            if (f.url.indexOf(self.location.origin) === 0 && 'focus' in f) {
                return f.focus().then(function (fenetre) {
                    return fenetre.navigate(url);
                }).catch(function () {
                    return self.clients.openWindow(url);
                });
            }
        }
        return self.clients.openWindow(url);
    }));
});

/* global firebase */
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');

let messagingInitialized = false;

function initMessaging() {
    if (messagingInitialized) {
        return Promise.resolve();
    }

    return fetch('/firebase-web-config')
        .then((r) => r.json())
        .then((config) => {
            if (!config.apiKey || !config.projectId) {
                return;
            }
            const firebaseConfig = {
                apiKey: config.apiKey,
                authDomain: config.authDomain,
                projectId: config.projectId,
                storageBucket: config.storageBucket,
                messagingSenderId: config.messagingSenderId,
                appId: config.appId,
            };
            if (config.measurementId) {
                firebaseConfig.measurementId = config.measurementId;
            }
            if (!firebase.apps.length) {
                firebase.initializeApp(firebaseConfig);
            }
            const messaging = firebase.messaging();
            messaging.onBackgroundMessage((payload) => {
                const title = payload.notification?.title ?? payload.data?.title ?? 'Notification';
                const body = payload.notification?.body ?? payload.data?.body ?? '';
                return self.registration.showNotification(title, {
                    body,
                    data: payload.data ?? {},
                });
            });
            messagingInitialized = true;
        })
        .catch(() => {});
}

self.addEventListener('install', (event) => {
    event.waitUntil(initMessaging().then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

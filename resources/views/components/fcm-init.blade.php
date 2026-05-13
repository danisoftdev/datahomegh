@auth
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js"></script>
<script>
(function () {
    async function initFcm() {
        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            return;
        }

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
        if (!csrf) {
            return;
        }

        var cfgRes = await fetch('{{ url('/firebase-web-config') }}');
        var full = await cfgRes.json();
        var vapidKey = full.vapidKey;
        var firebaseConfig = {
            apiKey: full.apiKey,
            authDomain: full.authDomain,
            projectId: full.projectId,
            storageBucket: full.storageBucket,
            messagingSenderId: full.messagingSenderId,
            appId: full.appId,
        };
        if (full.measurementId) {
            firebaseConfig.measurementId = full.measurementId;
        }

        if (!firebaseConfig.apiKey || !firebaseConfig.projectId || !vapidKey) {
            return;
        }

        var permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            return;
        }

        var reg = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
        await navigator.serviceWorker.ready;

        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
        }

        var token = await firebase.messaging().getToken({
            vapidKey: vapidKey,
            serviceWorkerRegistration: reg,
        });

        if (!token) {
            return;
        }

        await fetch(@json(route('fcm.register')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ token: token, device_type: 'web' }),
            credentials: 'same-origin',
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initFcm().catch(function () {});
        });
    } else {
        initFcm().catch(function () {});
    }
})();
</script>
@endauth

import './bootstrap';
import Alpine from 'alpinejs';

function timeAgo(iso) {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    const sec = Math.floor((Date.now() - d.getTime()) / 1000);
    if (sec < 60) {
        return 'just now';
    }
    if (sec < 3600) {
        return `${Math.floor(sec / 60)}m ago`;
    }
    if (sec < 86400) {
        return `${Math.floor(sec / 3600)}h ago`;
    }
    return `${Math.floor(sec / 86400)}d ago`;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

document.addEventListener('alpine:init', () => {
    Alpine.data('notificationBell', (config) => ({
        open: false,
        unread: 0,
        recent: [],
        loading: false,
        alertModal: null,
        pollHandle: null,

        init() {
            this.refreshUnread();
            this.pollHandle = setInterval(() => this.refreshUnread(), config.pollMs ?? 30000);
            this.$watch('open', (v) => {
                if (v) {
                    this.loadRecent();
                }
            });
        },

        destroy() {
            if (this.pollHandle) {
                clearInterval(this.pollHandle);
            }
        },

        async refreshUnread() {
            try {
                const [u, rec] = await Promise.all([
                    fetch(config.unreadUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then((r) => r.json()),
                    fetch(config.recentUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then((r) => r.json()),
                ]);
                this.unread = u.count ?? 0;
                this.recent = rec.data ?? [];
                this.evaluateAlertModal();
            } catch {
                /* ignore */
            }
        },

        async loadRecent() {
            this.loading = true;
            try {
                const r = await fetch(config.recentUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const j = await r.json();
                this.recent = j.data ?? [];
                this.evaluateAlertModal();
            } catch {
                this.recent = [];
            } finally {
                this.loading = false;
            }
        },

        evaluateAlertModal() {
            const now = Date.now();
            const fiveMin = 5 * 60 * 1000;
            const urgent = this.recent.find((n) => {
                if (n.type !== 'alert' || n.is_read) {
                    return false;
                }
                const t = new Date(n.created_at).getTime();
                return Number.isFinite(t) && now - t < fiveMin;
            });
            this.alertModal = urgent ?? null;
        },

        timeAgo(iso) {
            return timeAgo(iso);
        },

        dismissAlert() {
            this.alertModal = null;
        },

        async markAllRead() {
            try {
                await fetch(config.markReadUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                });
                await this.refreshUnread();
                await this.loadRecent();
                this.dismissAlert();
            } catch {
                /* ignore */
            }
        },
    }));
});

window.Alpine = Alpine;
Alpine.start();

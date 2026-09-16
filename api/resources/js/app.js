import Alpine from 'alpinejs';

// Alpine only, per the brief — "small interactions only (carousels,
// filter toggles, mobile menu). No heavy framework." Everything that
// matters for SEO/first paint is server-rendered HTML before this file
// even loads.
window.Alpine = Alpine;

/**
 * A4 (tester feedback): a single site-wide unread-message poll, shared
 * between the desktop header's Chats badge and the mobile bottom nav's
 * own badge (both bind to $store.messages.count) plus a toast — none of
 * this existed before at all; the badge was a static per-page-load number
 * that only ever rendered in the mobile bottom nav's own markup, so it
 * simply never appeared on desktop, and neither surface ever updated
 * without a full navigation. Deliberately a slower cadence than an open
 * thread's own 5s poll (MessagesController::poll()) — that runs only
 * while one specific thread is open; this runs on every signed-in page
 * load site-wide, so 5s here would be needless load on shared hosting for
 * comparatively little benefit.
 */
Alpine.store('messages', { count: 0 });

Alpine.data('messageNotifier', (initialCount, pollUrl, chatsUrl, isSignedIn) => ({
    toast: null,
    toastHref: chatsUrl,
    toastTimer: null,

    init() {
        Alpine.store('messages').count = initialCount;
        if (!isSignedIn) return;
        setInterval(() => this.poll(), 20000);
    },

    poll() {
        fetch(pollUrl, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data) return;
                if (data.count > Alpine.store('messages').count) this.showToast();
                Alpine.store('messages').count = data.count;
            })
            .catch(() => { /* a missed poll just waits for the next tick — never worth surfacing as an error */ });
    },

    showToast() {
        clearTimeout(this.toastTimer);
        this.toast = 'You have a new message';
        this.toastTimer = setTimeout(() => { this.toast = null; }, 5000);
    },
}));

/**
 * B3 (tester feedback): the header's notification bell — new orders,
 * order status changes, and new messages were already being written to
 * `app_notifications` (PushNotifier/LogPushNotifier::notify() has always
 * persisted every one of those, on both the API and the website's own
 * MessagesController), the website just had nowhere to show any of it.
 * Same 20s poll cadence as messageNotifier, but the list itself is only
 * fetched lazily when the dropdown actually opens — no point paying for
 * a full notifications fetch on every page load just to show a count.
 */
Alpine.data('notificationBell', (initialCount, unreadCountUrl, recentUrl, markAllReadUrl, isSignedIn) => ({
    count: initialCount,
    open: false,
    loading: false,
    notifications: [],
    loadedOnce: false,

    init() {
        if (!isSignedIn) return;
        setInterval(() => this.pollCount(), 20000);
    },

    pollCount() {
        fetch(unreadCountUrl, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => { if (data) this.count = data.count; })
            .catch(() => { /* a missed poll just waits for the next tick */ });
    },

    toggle() {
        this.open = !this.open;
        if (this.open && !this.loadedOnce) this.loadRecent();
    },

    loadRecent() {
        this.loading = true;
        fetch(recentUrl, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (data) {
                    this.notifications = data.notifications;
                    this.loadedOnce = true;
                }
            })
            .finally(() => { this.loading = false; });
    },

    markAllRead() {
        this.count = 0;
        this.notifications = this.notifications.map((n) => ({ ...n, read: true }));
        fetch(markAllReadUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        }).catch(() => { /* the next full page load will reconcile the true count regardless */ });
    },
}));

/**
 * The header's mega menu (noon.com pattern) — a 150ms delay before opening
 * on hover so a cursor merely passing over the category bar never fires
 * it, and a matching close delay so moving from the trigger link down into
 * the panel doesn't close it in transit. `toggle`/`close` back the tap and
 * keyboard paths — see partials/header.blade.php for exactly how each
 * event wires in (mouseenter/mouseleave for hover, focus/focusout for
 * keyboard nav, click for touch, Escape globally).
 */
Alpine.data('megaMenu', () => ({
    activeId: null,
    openTimer: null,
    closeTimer: null,
    OPEN_DELAY_MS: 150,
    CLOSE_DELAY_MS: 150,

    open(id) {
        clearTimeout(this.closeTimer);
        clearTimeout(this.openTimer);
        this.openTimer = setTimeout(() => {
            this.activeId = id;
        }, this.OPEN_DELAY_MS);
    },

    scheduleClose() {
        clearTimeout(this.openTimer);
        this.closeTimer = setTimeout(() => {
            this.activeId = null;
        }, this.CLOSE_DELAY_MS);
    },

    cancelClose() {
        clearTimeout(this.closeTimer);
    },

    toggle(id) {
        clearTimeout(this.openTimer);
        clearTimeout(this.closeTimer);
        this.activeId = this.activeId === id ? null : id;
    },

    close() {
        clearTimeout(this.openTimer);
        clearTimeout(this.closeTimer);
        this.activeId = null;
    },
}));

/**
 * The seller product form's photo manager (tester feedback item 1 — the
 * old plain `images[]` input gave no way to see, remove, or reorder a
 * selection, which read as "only one photo works" even though the server
 * always accepted more). Each file is compressed client-side (this is a
 * 3G market — CLAUDE.md's own stated constraint) before a real per-file
 * XHR upload, so sellers see individual progress rather than one opaque
 * page-submit spinner. Reordering the thumbnails is the direct way a
 * seller sets the cover photo — Product::media() is always ordered by
 * `sort`, so whatever ends up first here is what appears first everywhere
 * else in the app.
 */
/**
 * Seller registration's "Use my current location" (tester feedback item
 * 7) — browser geolocation, then a reverse geocode through OpenStreetMap's
 * free Nominatim (the site already uses Nominatim for the reverse
 * direction server-side in SellerRegistrationController; no Google Maps
 * key exists on this account). Denied permission, an unsupported browser,
 * or a failed lookup all fall back to the exact same state as loading the
 * page normally — every field stays a plain, always-editable text input,
 * never blocked or disabled on this failing.
 */
Alpine.data('sellerLocationPicker', (regions) => ({
    regions,
    lat: '',
    lng: '',
    region: '',
    district: '',
    address: '',
    locating: false,
    statusMessage: '',

    useCurrentLocation() {
        if (!('geolocation' in navigator)) {
            this.statusMessage = 'Location isn\'t available in this browser — please fill in the fields below.';
            return;
        }

        this.locating = true;
        this.statusMessage = '';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.lat = position.coords.latitude;
                this.lng = position.coords.longitude;
                this.reverseGeocode(this.lat, this.lng);
            },
            (error) => {
                this.locating = false;
                this.statusMessage = error.code === error.PERMISSION_DENIED
                    ? 'Location access was declined — no problem, just fill in the fields below.'
                    : 'Couldn\'t get your location — please fill in the fields below.';
            },
            { timeout: 10000, maximumAge: 60000 }
        );
    },

    async reverseGeocode(lat, lng) {
        try {
            const response = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=en`,
                { headers: { Accept: 'application/json' } }
            );
            if (!response.ok) throw new Error('lookup failed');
            const data = await response.json();
            const addr = data.address || {};

            const street = [addr.house_number, addr.road].filter(Boolean).join(' ');
            this.address = [street, addr.suburb || addr.neighbourhood].filter(Boolean).join(', ') || data.display_name || this.address;
            this.district = addr.county || addr.city_district || addr.suburb || this.district;

            const candidates = [addr.state, addr.region, addr.county, addr.city].filter(Boolean).map((s) => s.toLowerCase());
            const matchedRegion = this.regions.find((r) => candidates.some((c) => c.includes(r.toLowerCase()) || r.toLowerCase().includes(c)));
            if (matchedRegion) this.region = matchedRegion;

            this.statusMessage = 'Filled in from your location — please check it\'s correct.';
        } catch {
            this.statusMessage = 'Got your location, but couldn\'t look up the address — please fill in the fields below.';
        } finally {
            this.locating = false;
        }
    },
}));

/**
 * Shop logo upload from account settings (tester feedback A5) — no proper
 * upload existed on the website at all before this. Same client-side-
 * compression-then-real-upload shape as the product photo manager: pick a
 * file, compress it, upload via XHR, and swap the on-page preview to the
 * server's own returned URL on success so the change reflects immediately
 * without a page reload — the shop page itself picks it up on next visit
 * since both read the same `seller_profiles.logo` column.
 */
Alpine.data('shopLogoUploader', (sellerId, currentLogoUrl) => ({
    logoUrl: currentLogoUrl,
    uploading: false,
    progress: 0,
    error: null,
    csrf: document.querySelector('meta[name="csrf-token"]')?.content ?? '',

    onFileSelected(fileList) {
        const file = fileList[0];
        if (!file) return;

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            this.error = 'Only JPEG, PNG or WebP photos are supported.';
            return;
        }

        this.error = null;
        this.uploading = true;
        this.progress = 0;
        const previousUrl = this.logoUrl;
        this.logoUrl = URL.createObjectURL(file);

        this.compress(file)
            .then((blob) => this.upload(blob))
            .catch((e) => {
                this.error = e.message || 'Could not update your logo.';
                this.logoUrl = previousUrl;
            })
            .finally(() => { this.uploading = false; });
    },

    compress(file) {
        return new Promise((resolve, reject) => {
            const objectUrl = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                const maxDim = 800;
                let { width, height } = img;
                if (width > maxDim || height > maxDim) {
                    if (width > height) {
                        height = Math.round(height * (maxDim / width));
                        width = maxDim;
                    } else {
                        width = Math.round(width * (maxDim / height));
                        height = maxDim;
                    }
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    URL.revokeObjectURL(objectUrl);
                    blob ? resolve(blob) : reject(new Error('Could not process this photo.'));
                }, 'image/jpeg', 0.85);
            };
            img.onerror = () => {
                URL.revokeObjectURL(objectUrl);
                reject(new Error('Could not read this photo.'));
            };
            img.src = objectUrl;
        });
    },

    upload(blob) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('logo', blob, 'logo.jpg');

            xhr.open('POST', `/account/shop/${sellerId}/logo`);
            xhr.setRequestHeader('X-CSRF-TOKEN', this.csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100);
            };
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    const data = JSON.parse(xhr.responseText);
                    this.logoUrl = data.logo;
                    this.progress = 100;
                    resolve();
                } else {
                    let message = 'Upload failed.';
                    try {
                        const body = JSON.parse(xhr.responseText);
                        message = body.errors?.logo?.[0] || body.message || message;
                    } catch { /* non-JSON error body — keep the generic message */ }
                    reject(new Error(message));
                }
            };
            xhr.onerror = () => reject(new Error('Network error — check your connection and try again.'));
            xhr.send(formData);
        });
    },
}));

Alpine.data('productMediaManager', (productId, maxItems, initialMedia) => ({
    productId,
    maxItems,
    items: initialMedia.map((m) => ({
        id: m.id, tempId: null, thumb: m.thumb_path, uploading: false, progress: 0, error: null, file: null,
    })),
    dragOver: false,
    dragIndex: null,
    csrf: document.querySelector('meta[name="csrf-token"]')?.content ?? '',

    get remainingSlots() {
        return Math.max(0, this.maxItems - this.items.length);
    },

    onFiles(fileList) {
        Array.from(fileList).slice(0, this.remainingSlots).forEach((file) => this.handleFile(file));
    },

    handleFile(file) {
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            this.items.push({
                id: null, tempId: crypto.randomUUID(), thumb: null, uploading: false, progress: 0,
                error: 'Only JPEG, PNG or WebP photos are supported.', file: null,
            });
            return;
        }

        const item = {
            id: null,
            tempId: crypto.randomUUID(),
            thumb: URL.createObjectURL(file),
            uploading: true,
            progress: 0,
            error: null,
            file,
        };
        this.items.push(item);
        this.compress(file).then((blob) => this.upload(blob, item)).catch((e) => {
            item.uploading = false;
            item.error = e.message || 'Could not process this photo.';
        });
    },

    retry(item) {
        if (!item.file) return;
        item.error = null;
        item.uploading = true;
        item.progress = 0;
        this.compress(item.file).then((blob) => this.upload(blob, item)).catch((e) => {
            item.uploading = false;
            item.error = e.message || 'Upload failed.';
        });
    },

    // Scales to the same 1600px long edge the server's own "full" variant
    // uses (ImageVariants) and re-encodes at 0.8 quality — a typical
    // mid-range-phone photo drops from several MB to well under 1MB,
    // comfortably inside the server's 8MB cap even before it does its own
    // resizing, and a fraction of the data on a 3G connection.
    compress(file) {
        return new Promise((resolve, reject) => {
            const objectUrl = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                const maxDim = 1600;
                let { width, height } = img;
                if (width > maxDim || height > maxDim) {
                    if (width > height) {
                        height = Math.round(height * (maxDim / width));
                        width = maxDim;
                    } else {
                        width = Math.round(width * (maxDim / height));
                        height = maxDim;
                    }
                }
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                canvas.toBlob((blob) => {
                    URL.revokeObjectURL(objectUrl);
                    blob ? resolve(blob) : reject(new Error('Could not process this photo.'));
                }, 'image/jpeg', 0.8);
            };
            img.onerror = () => {
                URL.revokeObjectURL(objectUrl);
                reject(new Error('Could not read this photo.'));
            };
            img.src = objectUrl;
        });
    },

    upload(blob, item) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            formData.append('type', 'image');
            formData.append('file', blob, 'photo.jpg');
            formData.append('sort', String(this.items.indexOf(item)));

            xhr.open('POST', `/account/shop/products/${this.productId}/media`);
            xhr.setRequestHeader('X-CSRF-TOKEN', this.csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) item.progress = Math.round((e.loaded / e.total) * 100);
            };
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    const data = JSON.parse(xhr.responseText);
                    item.id = data.id;
                    item.thumb = data.thumb_path;
                    item.uploading = false;
                    item.progress = 100;
                    resolve();
                } else {
                    let message = 'Upload failed.';
                    try {
                        const body = JSON.parse(xhr.responseText);
                        message = body.errors?.file?.[0] || body.message || message;
                    } catch { /* non-JSON error body — keep the generic message */ }
                    reject(new Error(message));
                }
            };
            xhr.onerror = () => reject(new Error('Network error — check your connection and try again.'));
            item._xhr = xhr;
            xhr.send(formData);
        });
    },

    removeItem(item) {
        if (item.uploading && item._xhr) item._xhr.abort();
        this.items.splice(this.items.indexOf(item), 1);
        if (item.id) {
            fetch(`/account/shop/products/${this.productId}/media/${item.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
            }).catch(() => { /* the item is already gone from view; a stray server row isn't worth blocking the seller over */ });
        }
    },

    onDragStart(index) {
        this.dragIndex = index;
    },

    onDropReorder(index) {
        if (this.dragIndex === null || this.dragIndex === index) return;
        const [moved] = this.items.splice(this.dragIndex, 1);
        this.items.splice(index, 0, moved);
        this.dragIndex = null;
        this.persistOrder();
    },

    persistOrder() {
        const order = this.items.filter((i) => i.id).map((i) => i.id);
        if (!order.length) return;
        fetch(`/account/shop/products/${this.productId}/media/reorder`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ order }),
        }).catch(() => { /* the visible order already reflects the seller's intent; a background retry isn't worth the complexity for a rare failure */ });
    },
}));

/**
 * Part 2 (client feedback): "+255 must show as a fixed, non-editable
 * prefix — the user types only their own number." The visible input
 * only ever holds the local digits; this composes the full E.164 value
 * into a hidden `phone` field right before it's needed, so
 * RequestOtpRequest/VerifyOtpRequest's own validation (still a plain
 * `regex:/^\+255[67]\d{8}$/` against `phone`) needs no server-side
 * change at all — "store and send E.164 as now."
 *
 * Mirrors the app's own SokoniFormat.phoneToE164 exactly (same three
 * shapes accepted: 9-digit local, 10-digit with a leading 0 stripped,
 * or a 12-digit 255-prefixed paste), so a number that resolves on one
 * surface resolves identically on the other.
 */
Alpine.data('phoneInput', (initialLocal = '') => ({
    local: initialLocal,

    get e164() {
        const digits = this.local.replace(/\D/g, '');
        if (digits.length === 9 && /^[67]/.test(digits)) return `+255${digits}`;
        if (digits.length === 10 && digits.startsWith('0')) return `+255${digits.slice(1)}`;
        if (digits.length === 12 && digits.startsWith('255')) return `+${digits}`;
        // Not yet a plausible number (still typing, or genuinely
        // invalid) — let the server's own validation be the final
        // word rather than guessing at a shape here.
        return `+255${digits}`;
    },
}));

Alpine.start();

// Section-heading fade-in-on-scroll (Part 3 motion spec: "300ms fade-in on
// scroll for section headings"). Plain IntersectionObserver, not Alpine —
// this is a one-shot reveal with no interactive state, so a directive per
// element would be more machinery than the effect needs. Skips entirely
// under prefers-reduced-motion, matching app.css's own media query rather
// than fighting it with a class the CSS then has to override.
if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.fade-in-section').forEach((el) => observer.observe(el));
} else {
    document.querySelectorAll('.fade-in-section').forEach((el) => el.classList.add('is-visible'));
}

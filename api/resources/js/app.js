import Alpine from 'alpinejs';

// Alpine only, per the brief — "small interactions only (carousels,
// filter toggles, mobile menu). No heavy framework." Everything that
// matters for SEO/first paint is server-rendered HTML before this file
// even loads.
window.Alpine = Alpine;

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
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    const data = JSON.parse(xhr.responseText);
                    this.logoUrl = data.logo;
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

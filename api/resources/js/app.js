import Alpine from 'alpinejs';

// Alpine only, per the brief — "small interactions only (carousels,
// filter toggles, mobile menu). No heavy framework." Everything that
// matters for SEO/first paint is server-rendered HTML before this file
// even loads.
window.Alpine = Alpine;
Alpine.start();

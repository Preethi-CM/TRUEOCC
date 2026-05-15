// ============================================================
// TRUE OCCUPATION — Service Worker (sw.js)
// Place this file at: C:\xampp\htdocs\trueocc\sw.js
// ============================================================

const APP_VERSION  = 'trueocc-v1.0.6';
const CACHE_STATIC = `${APP_VERSION}-static`;
const CACHE_PAGES  = `${APP_VERSION}-pages`;
const CACHE_API    = `${APP_VERSION}-api`;

// ── Files to cache immediately on install ────────────────────
const STATIC_ASSETS = [
  '/trueocc/',
  '/trueocc/index.html',
  '/trueocc/frontend/css/main.css',
  '/trueocc/frontend/js/main.js',
  '/trueocc/frontend/pages/login.html',
  '/trueocc/frontend/pages/signup.html',
  '/trueocc/frontend/pages/jobs.html',
  '/trueocc/frontend/pages/user-dashboard.html',
  '/trueocc/frontend/pages/employer-dashboard.html',
  '/trueocc/frontend/pages/admin-dashboard.html',
  '/trueocc/frontend/pages/admin-login.html',
  '/trueocc/frontend/pages/applications.html',
  '/trueocc/frontend/pages/resume.html',
  '/trueocc/frontend/pages/test.html',
  '/trueocc/frontend/pages/interview.html',
  '/trueocc/frontend/pages/books.html',
  '/trueocc/frontend/pages/job-detail.html',
  '/trueocc/frontend/icons/icon-192x192.svg',
  '/trueocc/frontend/icons/icon-512x512.svg',
  '/trueocc/manifest.json',
  '/trueocc/offline.html',
  // Font Awesome (cached from CDN on first load)
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css'
];

// ── INSTALL — pre-cache all static assets ───────────────────
self.addEventListener('install', event => {
  console.log('[SW] Installing version:', APP_VERSION);
  event.waitUntil(
    caches.open(CACHE_STATIC)
      .then(cache => {
        console.log('[SW] Pre-caching static assets...');
        // Cache individually so one failure doesn't break everything
        return Promise.allSettled(
          STATIC_ASSETS.map(url =>
            cache.add(url).catch(err =>
              console.warn('[SW] Failed to cache:', url, err.message)
            )
          )
        );
      })
      .then(() => self.skipWaiting()) // Activate immediately
  );
});

// ── ACTIVATE — clean old caches ──────────────────────────────
self.addEventListener('activate', event => {
  console.log('[SW] Activating version:', APP_VERSION);
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys
          .filter(key => key !== CACHE_STATIC && key !== CACHE_PAGES && key !== CACHE_API)
          .map(key => {
            console.log('[SW] Deleting old cache:', key);
            return caches.delete(key);
          })
      );
    }).then(() => self.clients.claim()) // Take control of all pages
  );
});

// ── FETCH — intercept network requests ──────────────────────
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests (POST for API calls)
  if (request.method !== 'GET') return;

  // Skip Chrome extensions and dev tools
  if (!url.protocol.startsWith('http')) return;

  // ── Strategy 1: PHP API calls → Network first, no cache ──
  if (url.pathname.includes('/backend/api/') || url.searchParams.has('action')) {
    event.respondWith(networkOnly(request));
    return;
  }

  // ── Strategy 2: HTML pages → Network first, cache fallback ──
  if (request.destination === 'document' || url.pathname.endsWith('.html') || url.pathname.endsWith('/')) {
    event.respondWith(networkFirstWithCache(request, CACHE_PAGES));
    return;
  }

  // ── Strategy 3: CSS / JS / Images → Cache first ──────────
  if (['style', 'script', 'image', 'font'].includes(request.destination) ||
      url.pathname.endsWith('.css') || url.pathname.endsWith('.js') ||
      url.pathname.endsWith('.svg') || url.pathname.endsWith('.png') ||
      url.pathname.endsWith('.jpg') || url.pathname.endsWith('.woff2')) {
    event.respondWith(cacheFirstWithNetwork(request, CACHE_STATIC));
    return;
  }

  // ── Strategy 4: Everything else → Network first ──────────
  event.respondWith(networkFirstWithCache(request, CACHE_PAGES));
});

// ── NETWORK ONLY (API calls) ─────────────────────────────────
async function networkOnly(request) {
  try {
    return await fetch(request);
  } catch {
    return new Response(
      JSON.stringify({ success: false, message: 'You are offline. Please check your internet connection.' }),
      { status: 503, headers: { 'Content-Type': 'application/json' } }
    );
  }
}

// ── NETWORK FIRST (HTML pages) ───────────────────────────────
async function networkFirstWithCache(request, cacheName) {
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch {
    // Network failed — try cache
    const cached = await caches.match(request);
    if (cached) return cached;
    // Nothing in cache — show offline page
    const offlinePage = await caches.match('/trueocc/offline.html');
    return offlinePage || new Response(
      '<h1 style="font-family:sans-serif;text-align:center;padding:60px">You are offline</h1>',
      { headers: { 'Content-Type': 'text/html' } }
    );
  }
}

// ── CACHE FIRST (Static assets) ──────────────────────────────
async function cacheFirstWithNetwork(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch {
    return new Response('Asset unavailable offline', { status: 503 });
  }
}

// ── BACKGROUND SYNC (for offline form submissions) ───────────
self.addEventListener('sync', event => {
  if (event.tag === 'sync-applications') {
    console.log('[SW] Background sync: applications');
  }
});

// ── PUSH NOTIFICATIONS (future use) ──────────────────────────
self.addEventListener('push', event => {
  if (!event.data) return;
  const data = event.data.json();
  self.registration.showNotification(data.title || 'True Occupation', {
    body: data.body || 'You have a new update!',
    icon: '/trueocc/frontend/icons/icon-192x192.svg',
    badge: '/trueocc/frontend/icons/icon-96x96.svg',
    data: { url: data.url || '/trueocc/index.html' }
  });
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow(event.notification.data?.url || '/trueocc/index.html')
  );
});

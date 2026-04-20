const CACHE_NAME = 'ocnhs-clinic-v3';
const ASSETS_TO_CACHE = [
  'assets/css/style.css',
  'assets/css/responsive.css',
  'assets/img/ocnhs_logo.png',
  'assets/js/offline_handler.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/sweetalert2@11'
];

// Install Event - Cache only static assets (not PHP pages that need login)
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[SW] Caching static assets...');
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Activate Event - Clean old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

// Fetch Event - Network First strategy
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests (POST form submissions handled by offline_handler.js)
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Cache successful page loads and API GET requests for offline use
        if (response.ok && (event.request.url.includes('.php') || event.request.url.includes('/api/'))) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, clone);
          });
        }
        return response;
      })
      .catch(() => {
        // Offline — try to serve from cache. ignoreSearch helps load pages even if URL has ?params
        return caches.match(event.request, { ignoreSearch: true }).then((cached) => {
          if (cached) return cached;

          // If a page was requested but not cached, return a basic offline page
          if (event.request.headers.get('accept') && event.request.headers.get('accept').includes('text/html')) {
            return new Response(`
              <!DOCTYPE html>
              <html>
              <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Offline - OCNHS Clinic</title>
                <style>
                  body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f5f5f5; }
                  .offline-card { text-align: center; padding: 40px; background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); max-width: 400px; }
                  .offline-card h1 { color: #f39c12; margin-bottom: 10px; }
                  .offline-card p { color: #555; line-height: 1.6; }
                  .offline-card .icon { font-size: 60px; margin-bottom: 15px; }
                  .retry-btn { margin-top: 20px; padding: 12px 30px; background: #00ACB1; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; }
                </style>
              </head>
              <body>
                <div class="offline-card">
                  <div class="icon">📡</div>
                  <h1>You Are Offline</h1>
                  <p>Cannot connect to the server. Please check your network connection and try again.</p>
                  <p style="font-size: 13px; color: #999;">If you have pending data, it will be synced automatically once the connection is restored.</p>
                  <button class="retry-btn" onclick="window.location.reload()">Retry Connection</button>
                </div>
              </body>
              </html>
            `, { headers: { 'Content-Type': 'text/html' } });
          }

          return new Response('', { status: 503 });
        });
      })
  );
});

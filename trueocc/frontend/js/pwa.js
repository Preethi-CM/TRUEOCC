// ============================================================
// TRUE OCCUPATION — PWA Registration & Install Prompt
// File: /trueocc/frontend/js/pwa.js
// This file is included in every HTML page. Do not modify.
// ============================================================

(function () {
  'use strict';

  // ── 1. REGISTER SERVICE WORKER ───────────────────────────
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker
        .register('/trueocc/sw.js', { scope: '/trueocc/' })
        .then(reg => {
          console.log('[PWA] Service Worker registered. Scope:', reg.scope);

          // Check for updates every 60 seconds
          setInterval(() => reg.update(), 60000);

          // New version available
          reg.addEventListener('updatefound', () => {
            const newWorker = reg.installing;
            newWorker.addEventListener('statechange', () => {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                showUpdateToast();
              }
            });
          });
        })
        .catch(err => {
          console.warn('[PWA] Service Worker registration failed:', err.message);
        });

      // Reload page when new SW takes over
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        window.location.reload();
      });
    });
  }

  // ── 2. INSTALL PROMPT ────────────────────────────────────
  let deferredPrompt = null;

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();        // Stop default mini-infobar
    deferredPrompt = event;
    showInstallButton();
    console.log('[PWA] Install prompt ready');
  });

  window.addEventListener('appinstalled', () => {
    console.log('[PWA] App installed successfully!');
    hideInstallButton();
    deferredPrompt = null;
    showInstalledToast();
  });

  // ── 3. INSTALL BUTTON UI ─────────────────────────────────
  function showInstallButton() {
    // Don't show if already running as installed PWA
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    if (navigator.standalone) return; // iOS

    let btn = document.getElementById('pwa-install-btn');
    if (!btn) {
      btn = createInstallButton();
      document.body.appendChild(btn);
    }
    // Slide in after 3 seconds
    setTimeout(() => btn.classList.add('pwa-btn-visible'), 3000);
  }

  function hideInstallButton() {
    const btn = document.getElementById('pwa-install-btn');
    if (btn) { btn.classList.remove('pwa-btn-visible'); setTimeout(() => btn.remove(), 400); }
  }

  function createInstallButton() {
    // Inject styles
    const style = document.createElement('style');
    style.textContent = `
      #pwa-install-btn {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 99999;
        display: flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(135deg, #0A66C2, #1a86c8);
        color: white;
        border: none;
        border-radius: 50px;
        padding: 14px 22px;
        font-family: 'DM Sans', system-ui, sans-serif;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 6px 28px rgba(10,102,194,0.45);
        transform: translateY(100px);
        opacity: 0;
        transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s ease;
        white-space: nowrap;
      }
      #pwa-install-btn.pwa-btn-visible {
        transform: translateY(0);
        opacity: 1;
      }
      #pwa-install-btn:hover {
        box-shadow: 0 8px 36px rgba(10,102,194,0.55);
        transform: translateY(-2px);
      }
      #pwa-install-btn .pwa-icon {
        font-size: 18px;
      }
      #pwa-install-btn .pwa-close {
        background: rgba(255,255,255,0.25);
        border: none;
        color: white;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-left: 4px;
        padding: 0;
        font-family: inherit;
      }
      #pwa-install-btn .pwa-close:hover { background: rgba(255,255,255,0.4); }

      /* Update toast */
      #pwa-update-toast {
        position: fixed;
        top: 80px;
        right: 24px;
        z-index: 99999;
        background: #1e293b;
        color: white;
        border-radius: 12px;
        padding: 14px 18px;
        font-family: 'DM Sans', system-ui, sans-serif;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.25);
        max-width: 320px;
        animation: pwaSlideIn 0.3s ease;
      }
      #pwa-update-toast button {
        background: #0A66C2;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 6px 14px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        font-family: inherit;
      }
      @keyframes pwaSlideIn {
        from { transform: translateX(120%); opacity: 0; }
        to   { transform: translateX(0);   opacity: 1; }
      }

      /* Installed success toast */
      #pwa-installed-toast {
        position: fixed;
        bottom: 90px;
        right: 24px;
        z-index: 99999;
        background: #057642;
        color: white;
        border-radius: 12px;
        padding: 12px 18px;
        font-size: 14px;
        font-weight: 600;
        font-family: 'DM Sans', system-ui, sans-serif;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 6px 24px rgba(5,118,66,0.4);
        animation: pwaSlideIn 0.3s ease;
      }
    `;
    document.head.appendChild(style);

    const btn = document.createElement('button');
    btn.id = 'pwa-install-btn';
    btn.innerHTML = `
      <span class="pwa-icon">📲</span>
      <span>Install App</span>
      <button class="pwa-close" onclick="event.stopPropagation();document.getElementById('pwa-install-btn').remove()" title="Dismiss">✕</button>
    `;
    btn.addEventListener('click', triggerInstall);
    return btn;
  }

  async function triggerInstall() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    console.log('[PWA] Install outcome:', outcome);
    deferredPrompt = null;
    hideInstallButton();
  }

  // ── 4. UPDATE TOAST ──────────────────────────────────────
  function showUpdateToast() {
    const existing = document.getElementById('pwa-update-toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.id = 'pwa-update-toast';
    toast.innerHTML = `
      <span>🔄 New version available!</span>
      <button onclick="window.location.reload()">Update Now</button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 12000);
  }

  // ── 5. INSTALLED TOAST ───────────────────────────────────
  function showInstalledToast() {
    const toast = document.createElement('div');
    toast.id = 'pwa-installed-toast';
    toast.innerHTML = `✅ TrueOcc installed successfully!`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
  }

  // ── 6. ONLINE / OFFLINE STATUS BAR ───────────────────────
  function createOfflineBar() {
    const bar = document.createElement('div');
    bar.id = 'pwa-offline-bar';
    const style = document.createElement('style');
    style.textContent = `
      #pwa-offline-bar {
        position: fixed;
        top: 0; left: 0; right: 0;
        background: #b91c1c;
        color: white;
        text-align: center;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 600;
        font-family: 'DM Sans', system-ui, sans-serif;
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transform: translateY(-100%);
        transition: transform 0.3s ease;
      }
      #pwa-offline-bar.visible { transform: translateY(0); }
      #pwa-offline-bar.online  { background: #057642; }
    `;
    document.head.appendChild(style);
    bar.textContent = '📡 You are offline — some features may not work';
    document.body.prepend(bar);
    return bar;
  }

  let offlineBar = null;
  function handleOnline() {
    if (!offlineBar) return;
    offlineBar.textContent = '✅ Back online!';
    offlineBar.classList.add('online');
    setTimeout(() => offlineBar.classList.remove('visible'), 2500);
  }
  function handleOffline() {
    if (!offlineBar) offlineBar = createOfflineBar();
    offlineBar.textContent = '📡 You are offline — some features may not work';
    offlineBar.classList.remove('online');
    offlineBar.classList.add('visible');
  }

  window.addEventListener('online',  handleOnline);
  window.addEventListener('offline', handleOffline);
  if (!navigator.onLine) handleOffline();

})();

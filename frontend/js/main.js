// ============================================================
// TRUE OCCUPATION — Core JavaScript
// ============================================================

// ── API BASE ──────────────────────────────────────────────────
// Works for: http://localhost/trueocc/frontend/pages/signup.html
//        → API: http://localhost/trueocc/backend/api/
function getApiBase() {
  const origin = window.location.origin; // e.g. http://localhost
  const path   = window.location.pathname; // e.g. /trueocc/frontend/pages/signup.html

  // If page is under /frontend/, strip from there
  if (path.includes('/frontend/')) {
    const root = path.substring(0, path.indexOf('/frontend/'));
    return origin + root + '/backend/api';
  }

  // If page is at project root (e.g. index.html)
  const lastSlash = path.lastIndexOf('/');
  const dir = lastSlash > 0 ? path.substring(0, lastSlash) : '';
  return origin + dir + '/backend/api';
}

// ── API HELPER ────────────────────────────────────────────────
const API = {
  async call(endpoint, opts = {}) {
    const base   = getApiBase();
    // endpoint may already include ?action=xxx — don't double-slash
    const urlStr = base + '/' + endpoint;

    const conf = {
      method: opts.method || 'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    };

    if (opts.body) {
      if (opts.body instanceof FormData) {
        conf.body   = opts.body;
        conf.method = 'POST';
      } else {
        conf.body    = JSON.stringify(opts.body);
        conf.headers['Content-Type'] = 'application/json';
        conf.method  = 'POST';
      }
    }

    console.debug('[API]', conf.method, urlStr);

    try {
      const res = await fetch(urlStr, conf);

      // Try JSON parse regardless of status
      const ct = res.headers.get('content-type') || '';
      if (ct.includes('application/json')) {
        return await res.json();
      }

      // Not JSON — likely a PHP error or wrong path
      const txt = await res.text();
      console.error('[API] Non-JSON response (' + res.status + ') from:', urlStr);
      console.error('[API] Response body:', txt.slice(0, 500));

      if (res.status === 404) {
        return { success: false, message: 'API file not found. Ensure PHP is running and folder name is "trueocc". URL tried: ' + urlStr };
      }
      return { success: false, message: 'Server returned non-JSON (' + res.status + '). See browser console for details.' };

    } catch (e) {
      console.error('[API] Fetch failed for:', urlStr, '— Error:', e.message);
      if (e.message.includes('Failed to fetch') || e.message.includes('NetworkError')) {
        return { success: false, message: 'Cannot reach PHP server. Is XAMPP Apache running? Is the folder named "trueocc" in htdocs?' };
      }
      return { success: false, message: 'Network error: ' + e.message };
    }
  },

  get(file, action, params = {}) {
    const qp = new URLSearchParams({ action });
    Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== '') qp.set(k, v); });
    return API.call(file + '?' + qp, {});
  },

  post(file, action, body) {
    return API.call(file + '?action=' + encodeURIComponent(action), { body });
  },

  postForm(file, action, fd) {
    return API.call(file + '?action=' + encodeURIComponent(action), { body: fd });
  }
};

// ── TOAST ─────────────────────────────────────────────────────
function toast(msg, type = 'info') {
  let wrap = document.getElementById('toasts');
  if (!wrap) { wrap = document.createElement('div'); wrap.id = 'toasts'; document.body.appendChild(wrap); }
  const t = document.createElement('div');
  const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
  t.className = 'toast ' + type;
  t.innerHTML = `<i class="fas ${icons[type]||icons.info}"></i><span>${msg}</span>`;
  wrap.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(20px)'; t.style.transition = '0.3s'; setTimeout(() => t.remove(), 300); }, 3500);
}

// ── MODAL ─────────────────────────────────────────────────────
function openModal(html, width = '580px') {
  closeModal();
  const ov = document.createElement('div');
  ov.className = 'overlay'; ov.id = 'modal';
  ov.innerHTML = `<div class="modal fade-in" style="max-width:${width}">${html}</div>`;
  ov.addEventListener('click', e => { if (e.target === ov) closeModal(); });
  document.body.appendChild(ov);
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  const m = document.getElementById('modal');
  if (m) { m.remove(); document.body.style.overflow = ''; }
}

// ── PREMIUM UPGRADE UI ─────────────────────────────────────────
function openPremiumScanner() {
  const html = `
    <div style="text-align:center;padding:20px;">
      <h2 style="font-family:'Syne',sans-serif;font-size:24px;font-weight:800;margin-bottom:10px;"><i class="fas fa-crown" style="color:#764ba2;"></i> Premium Upgrade</h2>
      <p style="color:var(--muted);margin-bottom:20px;">Scan the QR code below to upgrade your account to Premium and unlock unlimited access.</p>
      <div style="background:white;padding:20px;display:inline-block;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-bottom:20px;">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=trueoccupation_premium_payment" alt="QR Code" style="width:200px;height:200px;">
      </div>
      <div style="font-size:18px;font-weight:700;margin-bottom:20px;">Amount: ₹499 / month</div>
      <button class="btn btn-grad" onclick="simulatePayment()"><i class="fas fa-check-circle"></i> I have paid</button>
      <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
    </div>
  `;
  openModal(html, '400px');
}

function simulatePayment() {
  toast('Payment verified! Processing...', 'info');
  setTimeout(async () => {
    closeModal();
    toast('You are now a Premium user! Please refresh the page.', 'success');
    // If the page has a specific state refresh function, call it.
    if (typeof gateStatus !== 'undefined') {
      gateStatus.is_premium = true;
      gateStatus.can_attempt = true;
      if (typeof renderGate === 'function') renderGate(gateStatus);
    }
  }, 1500);
}

function confirmDialog(msg, onYes, opts = {}) {
  openModal(`
    <div class="modal-hd"><h2 class="modal-title">${opts.title||'Confirm'}</h2><button class="modal-x" onclick="closeModal()">✕</button></div>
    <p style="color:var(--muted);margin-bottom:24px;">${msg}</p>
    <div style="display:flex;gap:12px;">
      <button class="btn btn-${opts.cls||'danger'}" id="conf-yes">${opts.yes||'Confirm'}</button>
      <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
    </div>
  `);
  document.getElementById('conf-yes').onclick = () => { closeModal(); onYes(); };
}

// ── LOADING ───────────────────────────────────────────────────
function setLoading(btn, on, txt = '') {
  if (!btn) return;
  if (on) { btn.dataset.orig = btn.innerHTML; btn.innerHTML = `<i class="fas fa-spinner spin"></i> ${txt||'Loading...'}`;  btn.disabled = true; }
  else    { btn.innerHTML = btn.dataset.orig || txt; btn.disabled = false; }
}

// ── FORMAT ────────────────────────────────────────────────────
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-IN',{year:'numeric',month:'short',day:'numeric'}) : '—'; }
function timeAgo(d) {
  const diff = Date.now() - new Date(d).getTime();
  const m = Math.floor(diff/60000);
  if (m < 1) return 'Just now';
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m/60);
  if (h < 24) return `${h}h ago`;
  return fmtDate(d);
}

function statusBadge(s) {
  const map = { Applied:'b-blue', Shortlisted:'b-yellow', Interview:'b-blue', Rejected:'b-red', Hired:'b-green', verified:'b-green', pending:'b-yellow', rejected:'b-red', none:'b-gray' };
  const ico = { Hired:'🎉', verified:'✓', rejected:'✗', pending:'⏳', Shortlisted:'⭐' };
  return `<span class="badge ${map[s]||'b-gray'}">${ico[s]||''}${s}</span>`;
}

// ── JOB CARD ──────────────────────────────────────────────────
const coColors = ['#E3F2FD','#E8F5E9','#FFF3E0','#F3E5F5','#E0F7FA','#FFF8E1','#FCE4EC'];
function coBg(s) { return coColors[(s||'').charCodeAt(0) % coColors.length]; }

function jobCard(j, opts = {}) {
  const m = j.match_pct || 0;
  return `
    <div class="job-card fade-in" onclick="window.location.href='job-detail.html?id=${j.id}'">
      ${m > 0 ? `<div class="match-pill">🎯 ${m}%</div>` : ''}
      <div class="job-card-top">
        <div class="co-logo" style="background:${coBg(j.company)}">${(j.company||'?')[0]}</div>
        <div><div class="job-title">${j.title}</div><div class="job-co">${j.company}</div></div>
      </div>
      <div class="job-tags">
        <span class="jtag"><i class="fas fa-map-marker-alt"></i> ${j.location}</span>
        <span class="jtag"><i class="fas fa-clock"></i> ${j.job_type}</span>
        ${j.salary_range ? `<span class="jtag"><i class="fas fa-rupee-sign"></i> ${j.salary_range}</span>` : ''}
        <span class="jtag"><i class="fas fa-layer-group"></i> ${j.experience_level||'Entry'}</span>
      </div>
      <div style="font-size:13px;color:var(--muted);margin-bottom:10px;line-height:1.5;">${(j.description||'').slice(0,100)}...</div>
      <div>${(j.skills_required||'').split(',').slice(0,4).map(s=>`<span class="chip">${s.trim()}</span>`).join('')}</div>
      <div style="margin-top:8px;">
        ${j.require_test ? '<span class="badge b-orange" style="font-size:11px;"><i class="fas fa-clipboard-list"></i> Test Required</span> ' : ''}
        ${j.require_interview ? '<span class="badge b-blue" style="font-size:11px;"><i class="fas fa-microphone"></i> Interview Required</span>' : ''}
      </div>
    </div>`;
}

// ── AUTH CHECK ────────────────────────────────────────────────
async function checkAuth(role = '') {
  const res = await API.get('auth.php', 'me');
  if (!res.success) { window.location.href = 'login.html'; return null; }
  const d = res.data;
  if (d.type === 'admin') {
    if (role !== 'admin') { window.location.href = 'admin-dashboard.html'; return null; }
    return d;
  }
  if (role && d.user.role !== role) { window.location.href = 'login.html'; return null; }
  return d;
}

// ── NAVBAR ────────────────────────────────────────────────────
function buildNavbar(user, role) {
  const init = user.name.split(' ').map(n=>n[0]).join('').slice(0,2).toUpperCase();
  let links = '';
  if (role === 'seeker') {
    links = `<a href="user-dashboard.html"><i class="fas fa-home"></i> Dashboard</a>
             <a href="jobs.html"><i class="fas fa-briefcase"></i> Jobs</a>
             <a href="resume.html"><i class="fas fa-file-alt"></i> Resume</a>
             <a href="test.html"><i class="fas fa-clipboard-list"></i> Test</a>
             <a href="interview.html"><i class="fas fa-microphone"></i> Interview</a>
             <a href="applications.html"><i class="fas fa-tasks"></i> Applications</a>
             <a href="books.html"><i class="fas fa-book"></i> Learning</a>`;
  } else if (role === 'employer') {
    links = `<a href="employer-dashboard.html"><i class="fas fa-home"></i> Dashboard</a>
             <a href="employer-dashboard.html#jobs"><i class="fas fa-briefcase"></i> My Jobs</a>
             <a href="employer-dashboard.html#applicants"><i class="fas fa-users"></i> Applicants</a>`;
  }
  return `<nav class="nav">
    <div class="wrap">
      <a href="${role==='employer'?'employer-dashboard.html':'user-dashboard.html'}" class="nav-brand">True<span>Occ</span></a>
      <div class="nav-links">${links}</div>
      <div class="nav-right">
        <div style="position:relative;">
          <button class="nav-notif" id="notif-btn" onclick="toggleNotifs()"><i class="fas fa-bell"></i><span class="notif-badge" id="notif-cnt" style="display:none">0</span></button>
          <div class="notif-dd" id="notif-dd" style="display:none;"></div>
        </div>
        <div class="nav-user" onclick="window.location.href=(role==='employer'?'employer-dashboard.html':'user-dashboard.html')">
          <div class="avatar">${init}</div>
          <span class="nav-user-name">${user.name.split(' ')[0]}</span>
          <i class="fas fa-chevron-down" style="font-size:11px;color:var(--muted);"></i>
        </div>
        <button class="btn btn-ghost btn-sm" onclick="doLogout()"><i class="fas fa-sign-out-alt"></i></button>
      </div>
    </div>
  </nav>`;
}

// ── NOTIFICATIONS ─────────────────────────────────────────────
async function loadNotifs() {
  const res = await API.get('user.php', 'notifications');
  if (!res.success) return;
  const { notifications, unread_count } = res.data;
  const badge = document.getElementById('notif-cnt');
  if (badge) { badge.textContent = unread_count; badge.style.display = unread_count > 0 ? 'flex' : 'none'; }
  const dd = document.getElementById('notif-dd');
  if (!dd) return;
  if (!notifications.length) {
    dd.innerHTML = '<div style="padding:24px;text-align:center;color:var(--muted);font-size:14px;">No notifications</div>';
    return;
  }
  dd.innerHTML = `
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
      <strong style="font-size:14px;">Notifications</strong>
      ${unread_count > 0 ? `<button class="btn btn-ghost btn-sm" onclick="markAllRead()">Mark all read</button>` : ''}
    </div>
    ${notifications.slice(0,10).map(n => `
      <div class="notif-item ${n.is_read?'':'unread'}" onclick="markRead(${n.id})">
        <div style="font-weight:600;font-size:13px;margin-bottom:2px;">${n.title}</div>
        <div style="font-size:12px;color:var(--muted);">${n.message}</div>
        <div style="font-size:11px;color:var(--light);margin-top:4px;">${timeAgo(n.created_at)}</div>
      </div>`).join('')}`;
}

async function markRead(id) { await API.post('user.php', 'mark_read', { id }); loadNotifs(); }
async function markAllRead() { await API.get('user.php', 'mark_all_read'); loadNotifs(); }

function toggleNotifs() {
  const dd = document.getElementById('notif-dd');
  if (!dd) return;
  const shown = dd.style.display !== 'none';
  dd.style.display = shown ? 'none' : 'block';
  if (!shown) loadNotifs();
}
document.addEventListener('click', e => {
  const dd = document.getElementById('notif-dd');
  const btn = document.getElementById('notif-btn');
  if (dd && btn && !btn.contains(e.target) && !dd.contains(e.target)) dd.style.display = 'none';
});

async function doLogout() { await API.get('auth.php', 'logout'); window.location.href = 'login.html'; }

// ── SCORE RING ────────────────────────────────────────────────
function scoreRing(pct, size = 120) {
  const inner = Math.floor(size * 0.73);
  return `<div class="score-ring" style="width:${size}px;height:${size}px;--pct:${pct}">
    <div style="position:absolute;width:${inner}px;height:${inner}px;border-radius:50%;background:var(--surface);"></div>
    <div class="score-val" style="font-size:${Math.floor(size*.18)}px;">${Math.round(pct)}%</div>
  </div>`;
}

// ── TIMER CLASS ───────────────────────────────────────────────
class TestTimer {
  constructor(secs, onTick, onExpire) {
    this.left = secs; this.total = secs;
    this.onTick = onTick; this.onExpire = onExpire; this.iv = null;
  }
  start() {
    this.iv = setInterval(() => {
      this.left--;
      const m = String(Math.floor(this.left/60)).padStart(2,'0');
      const s = String(this.left%60).padStart(2,'0');
      this.onTick(`${m}:${s}`, (this.left/this.total)*100, this.left);
      if (this.left <= 0) { clearInterval(this.iv); this.onExpire(); }
    }, 1000);
  }
  stop() { clearInterval(this.iv); }
}
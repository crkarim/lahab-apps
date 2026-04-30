@extends('layouts.admin.app')

@section('title', 'Kitchen Pass')

@section('content')
{{-- Standalone kitchen pass — strips admin chrome (sidebar/footer
     hidden via the .lh-kitchen-scan-body class) so a kitchen monitor
     dedicated to scanning can run this page full-screen. The page has
     three jobs:
       1. Catch USB-scanner / camera / manual-entry KOT input and POST
          to the scan endpoint.
       2. Show the LIVE queue of cooking orders so the line cooks know
          what's outstanding without flipping screens.
       3. Show the recent-scans audit strip (existing). --}}
<style>
    body.lh-kitchen-scan-body {
        background: #0e0e10 !important;
        margin: 0;
    }
    body.lh-kitchen-scan-body #sidebarMain,
    body.lh-kitchen-scan-body .navbar-vertical-aside,
    body.lh-kitchen-scan-body #headerMain,
    body.lh-kitchen-scan-body .navbar,
    body.lh-kitchen-scan-body .footer,
    body.lh-kitchen-scan-body #footer { display: none !important; }
    body.lh-kitchen-scan-body main#content,
    body.lh-kitchen-scan-body .main {
        padding: 0 !important;
        margin: 0 !important;
    }

    .lh-kitchen-page {
        min-height: 100vh;
        background:
            radial-gradient(ellipse at top right, rgba(230, 126, 34, 0.10), transparent 50%),
            radial-gradient(ellipse at bottom left, rgba(230, 126, 34, 0.06), transparent 60%),
            linear-gradient(180deg, #0e0e10 0%, #15100c 100%);
        color: #f5f5f5;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        padding: 24px 32px 80px;
        position: relative;
    }

    .lh-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }
    .lh-back {
        padding: 6px 12px;
        background: rgba(255,255,255,0.06);
        color: #ddd;
        border-radius: 999px;
        font-size: 12px;
        text-decoration: none;
        border: 1px solid rgba(255,255,255,0.08);
        transition: background 0.15s;
    }
    .lh-back:hover { color: #fff; background: rgba(255,255,255,0.10); text-decoration: none; }
    .lh-clock {
        font-size: 13px;
        color: #888;
        font-variant-numeric: tabular-nums;
    }

    /* Hero — scan banner */
    .lh-hero {
        display: flex;
        align-items: center;
        gap: 24px;
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px;
        padding: 20px 24px;
        margin-bottom: 24px;
    }
    .lh-hero-icon {
        flex: 0 0 64px;
        width: 64px; height: 64px;
        border-radius: 50%;
        background: rgba(230, 126, 34, 0.18);
        display: flex; align-items: center; justify-content: center;
        font-size: 30px;
        color: #E67E22;
        animation: lh-pulse 2.4s ease-in-out infinite;
    }
    @keyframes lh-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(230, 126, 34, 0.40); }
        50%      { box-shadow: 0 0 0 14px rgba(230, 126, 34, 0.00); }
    }
    .lh-hero-text { flex: 1; }
    .lh-hero h1 {
        font-size: 22px;
        font-weight: 800;
        margin: 0 0 2px;
        letter-spacing: -0.3px;
    }
    .lh-hero p {
        margin: 0;
        font-size: 13px;
        color: #999;
    }
    .lh-hero-actions {
        display: flex;
        gap: 10px;
    }
    .lh-btn {
        background: #E67E22;
        color: #fff;
        border: 0;
        border-radius: 10px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: filter 0.15s;
    }
    .lh-btn:hover { filter: brightness(1.1); }
    .lh-btn.outline {
        background: transparent;
        border: 1px solid rgba(255,255,255,0.16);
        color: #ddd;
    }
    .lh-btn.outline:hover { background: rgba(255,255,255,0.06); }

    /* Hidden scanner-target input */
    #lh-scan-input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
        height: 0;
        width: 0;
    }

    /* Live queue grid */
    .lh-queue-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 32px 0 14px;
    }
    .lh-queue-title {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.6px;
        text-transform: uppercase;
        color: #aaa;
    }
    .lh-queue-count {
        font-size: 11px;
        color: #777;
        font-variant-numeric: tabular-nums;
    }

    .lh-queue {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 12px;
    }
    .lh-card {
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 12px;
        padding: 14px;
        position: relative;
        animation: lh-card-in 280ms cubic-bezier(.16,1,.3,1) both;
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    }
    @keyframes lh-card-in {
        from { transform: translateY(8px); opacity: 0; }
        to   { transform: translateY(0);   opacity: 1; }
    }
    @keyframes lh-card-out {
        to { transform: scale(0.85); opacity: 0; }
    }
    .lh-card.leaving {
        animation: lh-card-out 320ms ease-out forwards;
    }
    .lh-card.aged {
        border-color: rgba(232, 77, 79, 0.35);
        box-shadow: 0 0 0 1px rgba(232, 77, 79, 0.20) inset;
    }
    .lh-card.aged::before {
        content: '';
        position: absolute; top: 8px; right: 8px;
        width: 8px; height: 8px; border-radius: 50%;
        background: #e84d4f;
        animation: lh-blink 1s ease-in-out infinite;
    }
    @keyframes lh-blink { 50% { opacity: 0.3; } }
    .lh-card .row1 {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
    }
    .lh-card .kot {
        font-size: 18px;
        font-weight: 800;
        color: #fff;
        letter-spacing: 0.5px;
        font-variant-numeric: tabular-nums;
    }
    .lh-card .age {
        font-size: 11px;
        color: #999;
        font-variant-numeric: tabular-nums;
    }
    .lh-card.aged .age { color: #e84d4f; font-weight: 700; }
    .lh-card .table-row {
        margin-top: 6px;
        font-size: 13px;
        color: #ddd;
        font-weight: 600;
    }
    .lh-card .meta {
        margin-top: 4px;
        font-size: 11px;
        color: #888;
        line-height: 1.5;
    }
    .lh-card .meta strong { color: #bbb; font-weight: 600; }
    .lh-card .item-pill {
        position: absolute;
        bottom: 12px; right: 12px;
        background: rgba(230, 126, 34, 0.16);
        color: #E67E22;
        border-radius: 999px;
        padding: 3px 9px;
        font-size: 11px;
        font-weight: 700;
    }
    .lh-card.aged .item-pill {
        background: rgba(232, 77, 79, 0.18);
        color: #ff8587;
    }

    .lh-queue-empty {
        grid-column: 1 / -1;
        text-align: center;
        padding: 40px 20px;
        color: #666;
        font-size: 13px;
        background: rgba(255,255,255,0.02);
        border: 1px dashed rgba(255,255,255,0.06);
        border-radius: 12px;
    }

    /* Recent scans strip */
    .lh-recent {
        margin-top: 32px;
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 12px;
        overflow: hidden;
    }
    .lh-recent h3 {
        font-size: 11px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #888;
        padding: 10px 16px;
        margin: 0;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .lh-recent .row-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        border-bottom: 1px solid rgba(255,255,255,0.04);
        animation: lh-card-in 240ms ease-out both;
    }
    .lh-recent .row-item:last-child { border-bottom: 0; }
    .lh-recent .badge-dot {
        width: 9px; height: 9px; border-radius: 50%; background: #1ee06f;
        flex-shrink: 0;
    }
    .lh-recent .badge-dot.warn { background: #f0ad4e; }
    .lh-recent .badge-dot.err  { background: #e84d4f; }
    .lh-recent .kot {
        font-size: 14px; font-weight: 700; color: #fff;
        font-variant-numeric: tabular-nums;
        min-width: 80px;
    }
    .lh-recent .meta {
        flex: 1; min-width: 0;
        font-size: 11px; color: #999;
    }
    .lh-recent .meta strong { color: #ddd; font-weight: 600; }
    .lh-recent .ago {
        font-size: 11px; color: #666;
    }

    /* Flash overlay */
    .lh-flash {
        position: fixed; inset: 0;
        background: #1ee06f;
        opacity: 0;
        pointer-events: none;
        animation: lh-flash-fade 380ms ease-out;
        z-index: 9998;
    }
    .lh-flash.err { background: #e84d4f; animation-duration: 480ms; }
    @keyframes lh-flash-fade {
        from { opacity: 0.30; }
        to   { opacity: 0; }
    }

    /* Camera modal */
    .lh-modal {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.85);
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .lh-modal.open { display: flex; }
    .lh-modal-box {
        background: #0e0e10;
        border: 1px solid rgba(255,255,255,0.10);
        border-radius: 16px;
        padding: 16px;
        max-width: 420px;
        width: 100%;
    }
    .lh-modal-box h2 {
        margin: 0 0 4px;
        font-size: 16px;
        font-weight: 800;
        color: #fff;
    }
    .lh-modal-box p {
        margin: 0 0 14px;
        font-size: 12px;
        color: #999;
    }
    #lh-camera-preview {
        width: 100%;
        aspect-ratio: 1;
        background: #000;
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }
    #lh-camera-preview video { width: 100%; height: 100%; object-fit: cover; }
    .lh-modal-foot {
        display: flex; gap: 8px; margin-top: 14px;
    }
    .lh-modal-foot .lh-btn { flex: 1; justify-content: center; }

    /* Manual-entry chip in hero — always there if scanner is missing */
    .lh-manual {
        margin-top: 8px;
        font-size: 11px;
        color: #666;
    }
    .lh-manual a {
        color: #E67E22;
        cursor: pointer;
        text-decoration: underline;
    }

    @media (max-width: 600px) {
        .lh-kitchen-page { padding: 16px; }
        .lh-hero {
            flex-direction: column;
            align-items: stretch;
            text-align: center;
        }
        .lh-hero-icon { margin: 0 auto; }
        .lh-hero-actions { justify-content: center; }
    }
</style>

<div class="lh-kitchen-page">
    <div class="lh-topbar">
        <a href="{{ url('/admin/dashboard') }}" class="lh-back">← Back to admin</a>
        <span class="lh-clock" id="lh-clock"></span>
    </div>

    <input type="text" id="lh-scan-input" autocomplete="off" autofocus />

    <div class="lh-hero">
        <div class="lh-hero-icon">📷</div>
        <div class="lh-hero-text">
            <h1>Scan KOT to mark ready</h1>
            <p>Aim the scanner at the barcode on the printed KOT, or use camera / manual entry below.</p>
            <div class="lh-manual">
                Scanner offline? <a id="lh-manual-trigger">Type a KOT manually</a>
            </div>
        </div>
        <div class="lh-hero-actions">
            <button class="lh-btn" id="lh-camera-btn">📱 Use camera</button>
        </div>
    </div>

    {{-- LIVE queue --}}
    <div class="lh-queue-header">
        <span class="lh-queue-title">Cooking right now</span>
        <span class="lh-queue-count" id="lh-queue-count">— orders</span>
    </div>
    <div class="lh-queue" id="lh-queue">
        <div class="lh-queue-empty" id="lh-queue-empty">Loading current orders…</div>
    </div>

    {{-- Recent scans --}}
    <div class="lh-recent">
        <h3>Recent scans</h3>
        <div id="lh-history">
            <div class="row-item" id="lh-empty">
                <div class="meta" style="text-align:center; color:#666;">No scans yet — point a scanner at a KOT.</div>
            </div>
        </div>
    </div>
</div>

{{-- Camera scanner modal --}}
<div class="lh-modal" id="lh-camera-modal">
    <div class="lh-modal-box">
        <h2>Camera scanner</h2>
        <p>Point your camera at the KOT barcode. The page reads CODE128.</p>
        <div id="lh-camera-preview"></div>
        <div class="lh-modal-foot">
            <button class="lh-btn outline" id="lh-camera-close">Close</button>
        </div>
    </div>
</div>

{{-- html5-qrcode handles barcode + QR via the device camera. Pinned to a stable
     version so a CDN bump doesn't break the kitchen pass during service. --}}
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
(function () {
    'use strict';
    document.body.classList.add('lh-kitchen-scan-body');

    const input    = document.getElementById('lh-scan-input');
    const history  = document.getElementById('lh-history');
    const empty    = document.getElementById('lh-empty');
    const queue    = document.getElementById('lh-queue');
    const queueEmpty = document.getElementById('lh-queue-empty');
    const queueCount = document.getElementById('lh-queue-count');
    const clockEl  = document.getElementById('lh-clock');
    let scanned    = 0;

    // ── Clock ─────────────────────────────────────────────────────────
    function tickClock() {
        const d = new Date();
        clockEl.textContent = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    setInterval(tickClock, 1000); tickClock();

    // ── Scanner input focus ───────────────────────────────────────────
    function refocus() {
        // Don't yank focus from the camera modal or manual-entry prompt.
        if (document.body.classList.contains('lh-modal-active')) return;
        if (document.activeElement && document.activeElement.id === 'lh-camera-preview') return;
        input.focus();
    }
    setInterval(refocus, 600);
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.lh-modal-box') && !e.target.closest('button')) refocus();
    });

    // ── Effects ───────────────────────────────────────────────────────
    function flash(ok) {
        const el = document.createElement('div');
        el.className = 'lh-flash' + (ok ? '' : ' err');
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 600);
        if (window.navigator.vibrate) window.navigator.vibrate(ok ? 60 : [40, 80, 40]);
    }
    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }
    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }
    function relativeAge(seconds) {
        if (seconds < 60) return seconds + 's';
        if (seconds < 3600) return Math.round(seconds / 60) + 'm';
        return Math.round(seconds / 3600) + 'h';
    }

    // ── Recent scans strip ────────────────────────────────────────────
    function appendRecent(data, kind) {
        if (empty) empty.remove();
        const row = document.createElement('div');
        row.className = 'row-item';
        const dotClass = kind === 'ok' ? '' : (kind === 'warn' ? 'warn' : 'err');
        const o = data.order || {};
        const kot = o.kot_number || '—';
        const tbl = o.order_type === 'pos' || o.order_type === 'take_away'
            ? 'Take-away'
            : (o.table_number ? 'Table ' + o.table_number : '—');
        const placed = o.placed_by || '—';
        const customer = o.customer || '—';
        const time = new Date().toLocaleTimeString();
        row.innerHTML =
            '<div class="badge-dot ' + dotClass + '"></div>' +
            '<div class="kot">' + escapeHtml(kot) + '</div>' +
            '<div class="meta">' +
                '<strong>' + escapeHtml(tbl) + '</strong> · ' +
                escapeHtml(customer) + ' · by ' + escapeHtml(placed) +
            '</div>' +
            '<div class="ago">' + time + '</div>';
        history.insertBefore(row, history.firstChild);
        while (history.children.length > 12) {
            history.removeChild(history.lastChild);
        }
        scanned++;
    }

    // ── LIVE cooking queue ────────────────────────────────────────────
    let lastQueueIds = new Set();
    let queueAgesById = new Map(); // age tracking for client-side ticking

    function renderQueue(orders) {
        const newIds = new Set(orders.map(o => o.id));

        // Cards leaving — animate out then remove. We DO NOT remove them
        // immediately so a freshly-scanned KOT visibly flies out.
        for (const id of lastQueueIds) {
            if (!newIds.has(id)) {
                const card = document.querySelector('.lh-card[data-id="' + id + '"]');
                if (card && !card.classList.contains('leaving')) {
                    card.classList.add('leaving');
                    setTimeout(() => card.remove(), 320);
                }
            }
        }

        // Cards entering or updating.
        orders.forEach(o => {
            queueAgesById.set(o.id, o.age_seconds);
            let card = document.querySelector('.lh-card[data-id="' + o.id + '"]');
            const isAged = o.age_seconds > 900; // > 15 min = needs attention
            if (!card) {
                card = document.createElement('div');
                card.className = 'lh-card';
                card.dataset.id = o.id;
                queue.insertBefore(card, queue.firstChild);
            }
            card.classList.toggle('aged', isAged);
            card.innerHTML =
                '<div class="row1">' +
                    '<div class="kot">' + escapeHtml(o.kot_number || '—') + '</div>' +
                    '<div class="age" data-age>' + relativeAge(o.age_seconds) + '</div>' +
                '</div>' +
                '<div class="table-row">' + escapeHtml(o.table_label || '—') + '</div>' +
                '<div class="meta">' +
                    escapeHtml(o.customer || '—') +
                    '<br>by <strong>' + escapeHtml(o.placed_by || '—') + '</strong>' +
                '</div>' +
                (o.item_count > 0
                    ? '<div class="item-pill">' + o.item_count + ' item' + (o.item_count === 1 ? '' : 's') + '</div>'
                    : '');
        });

        lastQueueIds = newIds;

        if (orders.length === 0) {
            queueEmpty.style.display = '';
            queueEmpty.textContent = 'Pass is clear — no orders cooking.';
        } else {
            queueEmpty.style.display = 'none';
        }
        queueCount.textContent = orders.length === 1 ? '1 order' : orders.length + ' orders';
    }

    function fetchQueue() {
        fetch('{{ url('admin/kitchen/cooking-json') }}', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(r => r.ok ? r.json() : null)
        .then(j => { if (j && Array.isArray(j.orders)) renderQueue(j.orders); })
        .catch(() => { /* network blip — try again on next tick */ });
    }
    fetchQueue();
    setInterval(fetchQueue, 5000); // poll every 5s

    // Tick visible ages every 15s without re-fetching.
    setInterval(() => {
        document.querySelectorAll('.lh-card').forEach(card => {
            const id = +card.dataset.id;
            if (queueAgesById.has(id)) {
                const newAge = queueAgesById.get(id) + 15;
                queueAgesById.set(id, newAge);
                const ageEl = card.querySelector('[data-age]');
                if (ageEl) ageEl.textContent = relativeAge(newAge);
                if (newAge > 900) card.classList.add('aged');
            }
        });
    }, 15000);

    // ── Scan submit ───────────────────────────────────────────────────
    function send(kot) {
        return fetch('{{ url('admin/kitchen/scan') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ kot_number: kot }),
        })
        .then(r => r.json().then(j => ({ status: r.status, body: j })))
        .then(({ status, body }) => {
            if (body.ok) {
                flash(true);
                appendRecent(body, body.code === 'already_ready' ? 'warn' : 'ok');
                if (window.toastr) toastr.success(body.message || 'Marked ready');
                // Optimistic queue: remove the card immediately so the
                // pass clears before the next poll round-trip (which
                // arrives within 5s anyway).
                const card = document.querySelector('.lh-card[data-id="' + (body.order?.id || '') + '"]');
                if (card && !card.classList.contains('leaving')) {
                    card.classList.add('leaving');
                    setTimeout(() => card.remove(), 320);
                }
                fetchQueue(); // refresh count
            } else {
                flash(false);
                if (body.order) appendRecent(body, 'err');
                if (window.toastr) toastr.error(body.message || 'Could not mark ready', '', { timeOut: 6000 });
            }
        })
        .catch(() => {
            flash(false);
            if (window.toastr) toastr.error('Network error — try the scan again.');
        });
    }

    let lastInputAt = 0;
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const kot = input.value.trim();
            input.value = '';
            if (kot) send(kot);
        }
    });
    input.addEventListener('input', function () {
        lastInputAt = Date.now();
        const myStamp = lastInputAt;
        setTimeout(() => {
            if (lastInputAt !== myStamp) return;
            const v = input.value.trim();
            if (!v || v.length < 5) return;
            input.value = '';
            send(v);
        }, 120);
    });

    // ── Manual entry ──────────────────────────────────────────────────
    document.getElementById('lh-manual-trigger').addEventListener('click', () => {
        const kot = window.prompt('Enter KOT number (e.g. 30-04-013)')?.trim();
        if (kot) send(kot);
    });

    // ── Camera scanner ────────────────────────────────────────────────
    const camBtn   = document.getElementById('lh-camera-btn');
    const camModal = document.getElementById('lh-camera-modal');
    const camClose = document.getElementById('lh-camera-close');
    let html5Qr = null;

    function openCamera() {
        if (typeof Html5Qrcode === 'undefined') {
            alert('Camera scanner library failed to load. Check internet connectivity.');
            return;
        }
        camModal.classList.add('open');
        document.body.classList.add('lh-modal-active');
        html5Qr = new Html5Qrcode('lh-camera-preview');
        html5Qr.start(
            { facingMode: 'environment' },
            {
                fps: 12,
                qrbox: { width: 250, height: 120 },
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.QR_CODE,
                ],
            },
            (decoded) => {
                const kot = (decoded || '').trim();
                if (!kot) return;
                // Stop on first successful read so we don't hammer the
                // endpoint while the camera is still pointing at the slip.
                html5Qr.stop().then(() => {
                    closeCamera();
                    send(kot);
                }).catch(closeCamera);
            },
            () => { /* per-frame errors are too noisy to log */ }
        ).catch(err => {
            alert('Could not access camera: ' + err);
            closeCamera();
        });
    }
    function closeCamera() {
        if (html5Qr) {
            try { html5Qr.stop().catch(()=>{}); html5Qr.clear(); } catch (_) {}
            html5Qr = null;
        }
        camModal.classList.remove('open');
        document.body.classList.remove('lh-modal-active');
    }
    camBtn.addEventListener('click', openCamera);
    camClose.addEventListener('click', closeCamera);
    camModal.addEventListener('click', (e) => { if (e.target === camModal) closeCamera(); });

    // Initial focus.
    refocus();
})();
</script>
@endsection

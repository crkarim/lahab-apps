@extends('layouts.admin.app')

@section('title', 'Kitchen Scan')

@section('content')
{{-- Standalone scan surface — strips admin chrome (sidebar/footer
     hidden via the .lh-kitchen-scan body class) so a kitchen monitor
     dedicated to scanning can run this page full-screen. --}}
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
        background: linear-gradient(135deg, #0e0e10 0%, #1a0f08 100%);
        color: #f5f5f5;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 24px;
        position: relative;
    }
    .lh-kitchen-card {
        max-width: 720px;
        width: 100%;
        text-align: center;
    }
    .lh-kitchen-icon {
        width: 96px; height: 96px;
        margin: 0 auto 20px;
        border-radius: 50%;
        background: rgba(230, 126, 34, 0.18);
        display: flex; align-items: center; justify-content: center;
        font-size: 48px;
        color: #E67E22;
        animation: lh-kitchen-pulse 2.4s ease-in-out infinite;
    }
    @keyframes lh-kitchen-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(230, 126, 34, 0.40); }
        50%      { box-shadow: 0 0 0 18px rgba(230, 126, 34, 0.00); }
    }
    .lh-kitchen-title {
        font-size: 32px;
        font-weight: 800;
        letter-spacing: -0.5px;
        margin: 0 0 6px;
    }
    .lh-kitchen-sub {
        font-size: 15px;
        color: #aaa;
        margin: 0 0 28px;
    }

    /* The hidden input — the scanner types into this. We make it
       look invisible but keep it focused so every scan registers
       without clicking. */
    #lh-scan-input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
        height: 0;
    }

    .lh-kitchen-recent {
        max-width: 720px;
        width: 100%;
        margin-top: 24px;
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 12px;
        overflow: hidden;
    }
    .lh-kitchen-recent h3 {
        font-size: 11px;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #888;
        padding: 12px 16px;
        margin: 0;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .lh-kitchen-recent .row-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid rgba(255,255,255,0.04);
        animation: lh-kitchen-slide 280ms cubic-bezier(.16,1,.3,1) both;
    }
    @keyframes lh-kitchen-slide {
        from { transform: translateY(-8px); opacity: 0; }
        to   { transform: translateY(0);    opacity: 1; }
    }
    .lh-kitchen-recent .row-item:last-child { border-bottom: 0; }
    .lh-kitchen-recent .badge-dot {
        width: 10px; height: 10px; border-radius: 50%; background: #1ee06f;
    }
    .lh-kitchen-recent .badge-dot.warn { background: #f0ad4e; }
    .lh-kitchen-recent .badge-dot.err  { background: #e84d4f; }
    .lh-kitchen-recent .kot {
        font-size: 18px; font-weight: 800; color: #fff;
        font-variant-numeric: tabular-nums;
    }
    .lh-kitchen-recent .meta {
        flex: 1; min-width: 0;
        font-size: 12px; color: #aaa;
    }
    .lh-kitchen-recent .meta strong { color: #ddd; font-weight: 700; }
    .lh-kitchen-recent .ago {
        font-size: 11px; color: #777;
    }

    .lh-flash {
        position: fixed;
        inset: 0;
        background: #1ee06f;
        opacity: 0;
        pointer-events: none;
        animation: lh-flash-fade 380ms ease-out;
    }
    @keyframes lh-flash-fade {
        from { opacity: 0.35; }
        to   { opacity: 0; }
    }
    .lh-flash.err {
        background: #e84d4f;
        animation-duration: 480ms;
    }

    .lh-kitchen-stats {
        max-width: 720px;
        width: 100%;
        margin-top: 16px;
        text-align: center;
        font-size: 12px;
        color: #777;
    }

    .lh-kitchen-back {
        position: absolute;
        top: 16px; left: 16px;
        padding: 6px 12px;
        background: rgba(255,255,255,0.06);
        color: #ddd;
        border-radius: 999px;
        font-size: 12px;
        text-decoration: none;
        border: 1px solid rgba(255,255,255,0.08);
    }
    .lh-kitchen-back:hover { color: #fff; background: rgba(255,255,255,0.10); text-decoration: none; }
</style>

<div class="lh-kitchen-page">
    <a href="{{ url('/admin/dashboard') }}" class="lh-kitchen-back">← Back to admin</a>

    <input type="text" id="lh-scan-input" autocomplete="off" autofocus />

    <div class="lh-kitchen-card">
        <div class="lh-kitchen-icon">📷</div>
        <h1 class="lh-kitchen-title">Scan KOT to mark ready</h1>
        <p class="lh-kitchen-sub">Aim the scanner at the barcode on the printed KOT.<br>The waiter will be notified instantly.</p>
    </div>

    <div class="lh-kitchen-recent">
        <h3>Recent scans</h3>
        <div id="lh-history">
            <div class="row-item" id="lh-empty">
                <div class="meta" style="text-align:center; color:#666;">No scans yet — point the scanner at a KOT.</div>
            </div>
        </div>
    </div>

    <div class="lh-kitchen-stats">
        Session: <span id="lh-stats">0 scanned</span> ·
        Page stays focused for the scanner — leave it open during service.
    </div>
</div>

<script>
(function () {
    'use strict';
    document.body.classList.add('lh-kitchen-scan-body');

    const input    = document.getElementById('lh-scan-input');
    const history  = document.getElementById('lh-history');
    const empty    = document.getElementById('lh-empty');
    const stats    = document.getElementById('lh-stats');
    let scanned    = 0;

    // Keep the hidden input focused. If the operator clicks somewhere
    // else (or the browser steals focus) we re-claim it on the next
    // tick so scanner input doesn't fall on the floor.
    function refocus() { input.focus(); }
    setInterval(refocus, 600);
    document.addEventListener('click', refocus);

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

    function appendRow(data, kind) {
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
        // Cap visible history at 12 rows.
        while (history.children.length > 12) {
            history.removeChild(history.lastChild);
        }
        scanned++;
        stats.textContent = scanned + (scanned === 1 ? ' scanned' : ' scanned');
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }

    let buf = '';
    let lastInputAt = 0;
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const kot = (input.value || buf).trim();
            input.value = '';
            buf = '';
            if (!kot) return;
            send(kot);
        }
    });
    // The scanner types fast; we catch buffer too in case the wedge
    // doesn't always end with Enter. Auto-fire if the input has been
    // idle for 80ms (typical scanner ~5ms between chars).
    input.addEventListener('input', function () {
        lastInputAt = Date.now();
        const myStamp = lastInputAt;
        setTimeout(() => {
            if (lastInputAt !== myStamp) return; // newer input arrived
            const v = input.value.trim();
            if (!v) return;
            // Heuristic: if no Enter came and value is at least 5 chars
            // (KOT format DD-MM-NNN = 9 chars), submit it.
            if (v.length >= 5) {
                input.value = '';
                send(v);
            }
        }, 120);
    });

    function send(kot) {
        fetch('{{ url('admin/kitchen/scan') }}', {
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
                appendRow(body, body.code === 'already_ready' ? 'warn' : 'ok');
                if (window.toastr) toastr.success(body.message || 'Marked ready');
            } else {
                flash(false);
                if (body.order) appendRow(body, 'err');
                if (window.toastr) toastr.error(body.message || 'Could not mark ready', '', { timeOut: 6000 });
            }
        })
        .catch(() => {
            flash(false);
            if (window.toastr) toastr.error('Network error — try the scan again.');
        });
    }

    // Initial focus.
    refocus();
})();
</script>
@endsection

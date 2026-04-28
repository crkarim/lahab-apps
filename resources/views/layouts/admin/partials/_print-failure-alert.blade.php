{{--
    Print-failure escalation bottom sheet.

    Polls /admin/print-failures/pending every 5s. When the queue is
    non-empty, surfaces a sticky bottom sheet listing EVERY pending
    failure with a Print / View / Retry / Mark-handled action row.
    Stays visible across multi-station setups until ANY admin acts on
    each row (handled-by stamp dismisses for everyone on the next poll).

    The PRIMARY action — Print — opens the KOT in a new window (which
    auto-fires the browser native print dialog) and posts an "acknowledge
    printed" stamp so the waiter app drops the PRINTER OFFLINE pill.
    Retry stays as the network-printer reattempt; View KOT is preview-
    only (no auto-print).

    No external libs — vanilla JS + jQuery (already in the layout) so
    it loads on every admin page without extra bundle weight.
--}}
<style>
    /* Floating bottom-right panel. Standard "toast stack" footprint —
       460px wide on desktop, full-width with side margins on small
       screens so it never crowds the working area of the page. */
    .lh-pf-sheet {
        position: fixed;
        right: 20px; bottom: 20px;
        z-index: 1080;
        width: 460px;
        max-width: calc(100vw - 24px);
        background: #fff;
        border: 1px solid #FAD3D3;
        border-top: 3px solid #E84D4F;
        border-radius: 14px;
        box-shadow: 0 18px 44px rgba(184, 28, 28, 0.28), 0 4px 12px rgba(0,0,0,0.10);
        max-height: min(70vh, 560px);
        display: none;
        flex-direction: column;
        overflow: hidden;
        animation: lh-pf-slide-up 320ms cubic-bezier(.16,1,.3,1) both;
    }
    .lh-pf-sheet.is-open { display: flex; }

    @keyframes lh-pf-slide-up {
        from { transform: translate3d(0, 16px, 0); opacity: 0; }
        to   { transform: translate3d(0, 0, 0);     opacity: 1; }
    }
    @keyframes lh-pf-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(232, 77, 79, 0.0); }
        50%      { box-shadow: 0 0 0 6px rgba(232, 77, 79, 0.18); }
    }

    .lh-pf-bar {
        background: linear-gradient(135deg, #E84D4F 0%, #B12B2D 100%);
        color: #fff;
        padding: 10px 16px;
        display: flex; align-items: center; gap: 10px;
        font-weight: 700;
    }
    .lh-pf-bar .ico {
        font-size: 18px; line-height: 1;
        animation: lh-pf-blink 1100ms ease-in-out infinite;
    }
    @keyframes lh-pf-blink {
        0%, 100% { opacity: 1; }
        50%      { opacity: 0.45; }
    }
    .lh-pf-bar .title { font-size: 13px; letter-spacing: 0.5px; text-transform: uppercase; }
    .lh-pf-bar .count {
        background: rgba(255,255,255,0.22); padding: 2px 8px;
        border-radius: 999px; font-size: 11px; font-weight: 800;
    }
    .lh-pf-bar .spacer { flex: 1; }
    .lh-pf-mute, .lh-pf-collapse {
        background: rgba(255,255,255,0.18); color: #fff;
        border: 0; border-radius: 999px; padding: 3px 9px;
        font-size: 11px; cursor: pointer;
        font-weight: 700; letter-spacing: 0.5px;
    }
    .lh-pf-mute:hover, .lh-pf-collapse:hover { background: rgba(255,255,255,0.28); }

    .lh-pf-list {
        flex: 1; overflow-y: auto;
        padding: 10px 14px 14px;
        background: #FAFAF7;
    }

    .lh-pf-row {
        background: #fff;
        border: 1px solid #FAD3D3;
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 8px;
    }
    .lh-pf-row:last-child { margin-bottom: 0; }
    .lh-pf-row.is-fresh {
        animation: lh-pf-pulse 1500ms ease-in-out infinite;
    }
    .lh-pf-row .head {
        display: flex; align-items: center; gap: 12px;
        margin-bottom: 8px;
    }
    .lh-pf-row .kot {
        font-size: 22px; font-weight: 900; color: #1a1a1a;
        line-height: 1; letter-spacing: -0.4px;
        min-width: 56px;
    }
    .lh-pf-row .meta { color: #555; font-size: 12px; line-height: 1.5; min-width: 0; flex: 1; }
    .lh-pf-row .meta strong { color: #1a1a1a; font-weight: 700; }
    .lh-pf-row .meta .sub { color: #999; font-size: 11px; margin-top: 2px; }

    .lh-pf-row .actions {
        display: flex; gap: 6px; flex-wrap: wrap;
    }
    .lh-pf-row .actions button {
        height: 34px; padding: 0 10px;
        border: 0; border-radius: 7px;
        font-size: 12px; font-weight: 800; letter-spacing: 0.3px;
        cursor: pointer; transition: transform 100ms, box-shadow 120ms, background 120ms;
        white-space: nowrap;
    }
    .lh-pf-row .actions button:disabled { opacity: 0.55; cursor: progress; }
    /* Print is the primary — give it room while siblings stay compact */
    .lh-pf-row .actions .lh-pf-print { flex: 1 1 auto; min-width: 110px; }

    .lh-pf-print {
        background: #E67E22; color: #fff;
        box-shadow: 0 4px 10px -4px rgba(230,126,34,0.55);
    }
    .lh-pf-print:hover:not(:disabled) { background: #C9661A; }

    .lh-pf-view {
        background: #fff; color: #1a1a1a;
        border: 1px solid #E5E7EB !important;
    }
    .lh-pf-view:hover:not(:disabled) { background: #f1f2f4; }

    .lh-pf-retry {
        background: #fff; color: #4794FF;
        border: 1px solid #BFD8FF !important;
    }
    .lh-pf-retry:hover:not(:disabled) { background: #EFF5FF; }

    .lh-pf-handled {
        background: transparent; color: #999;
        border: 0;
        font-weight: 600 !important;
        padding: 0 8px !important;
        font-size: 11px !important;
        text-decoration: underline;
    }
    .lh-pf-handled:hover:not(:disabled) { color: #555; }
</style>

<div class="lh-pf-sheet" id="lh-pf-sheet" aria-live="assertive">
    <div class="lh-pf-bar">
        <span class="ico">⚠</span>
        <span class="title">Kitchen tickets need attention</span>
        <span class="count" id="lh-pf-count">0</span>
        <span class="spacer"></span>
        <button type="button" class="lh-pf-mute" id="lh-pf-mute" title="Mute alert sound">🔔 ON</button>
    </div>
    <div class="lh-pf-list" id="lh-pf-list"></div>
</div>

<script>
(function () {
    'use strict';

    const POLL_MS = 5000;
    const BEEP_INTERVAL_MS = 30000;

    const sheet   = document.getElementById('lh-pf-sheet');
    if (!sheet) return;
    const list    = document.getElementById('lh-pf-list');
    const counter = document.getElementById('lh-pf-count');
    const btnMute = document.getElementById('lh-pf-mute');

    let queue = [];
    let busy = new Set();
    let lastBeep = 0;
    let muted = sessionStorage.getItem('lh-pf-mute') === '1';
    btnMute.textContent = muted ? '🔕 OFF' : '🔔 ON';

    btnMute.addEventListener('click', function () {
        muted = !muted;
        sessionStorage.setItem('lh-pf-mute', muted ? '1' : '0');
        btnMute.textContent = muted ? '🔕 OFF' : '🔔 ON';
    });

    function beep() {
        if (muted) return;
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            const ctx = new Ctx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.001, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc.start();
            osc.stop(ctx.currentTime + 0.42);
            setTimeout(() => ctx.close(), 600);
        } catch (e) { /* audio not available — silently skip */ }
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }

    function rowHtml(f, isFirst) {
        const meta = [];
        if (f.order_type) meta.push('<strong>' + escapeHtml(f.order_type.replace('_',' ').toUpperCase()) + '</strong>');
        if (f.table_number) meta.push('Table ' + escapeHtml(f.table_number) + (f.table_zone ? ' · ' + escapeHtml(f.table_zone) : ''));
        if (f.customer)  meta.push('Cust: ' + escapeHtml(f.customer));
        if (f.placed_by) meta.push('By ' + escapeHtml(f.placed_by));
        if (f.branch)    meta.push(escapeHtml(f.branch));
        const sub = [];
        if (f.failed_human) sub.push(escapeHtml(f.failed_human));
        sub.push('Order #' + f.id);

        const isBusy = busy.has(f.id);

        return ''
            + '<div class="lh-pf-row' + (isFirst ? ' is-fresh' : '') + '" data-id="' + f.id + '">'
            +   '<div class="head">'
            +     '<div class="kot">' + escapeHtml(f.kot_number || '—') + '</div>'
            +     '<div class="meta">'
            +       meta.join(' · ')
            +       '<div class="sub">' + sub.join(' · ') + '</div>'
            +     '</div>'
            +   '</div>'
            +   '<div class="actions">'
            +     '<button type="button" class="lh-pf-print"   data-act="print"   ' + (isBusy ? 'disabled' : '') + '>🖨 Print</button>'
            +     '<button type="button" class="lh-pf-view"    data-act="view"    ' + (isBusy ? 'disabled' : '') + '>View KOT</button>'
            +     '<button type="button" class="lh-pf-retry"   data-act="retry"   ' + (isBusy ? 'disabled' : '') + '>Retry net</button>'
            +     '<button type="button" class="lh-pf-handled" data-act="handled" ' + (isBusy ? 'disabled' : '') + '>Mark handled</button>'
            +   '</div>'
            + '</div>';
    }

    function render() {
        if (queue.length === 0) {
            sheet.classList.remove('is-open');
            return;
        }
        const wasOpen = sheet.classList.contains('is-open');
        counter.textContent = queue.length;
        list.innerHTML = queue.map((f, i) => rowHtml(f, i === 0)).join('');
        if (!wasOpen) {
            sheet.classList.add('is-open');
            beep(); // first show — beep immediately
            lastBeep = Date.now();
        } else if (Date.now() - lastBeep > BEEP_INTERVAL_MS) {
            beep();
            lastBeep = Date.now();
        }
    }

    function withCsrf(headers) {
        return Object.assign({
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        }, headers || {});
    }

    function poll() {
        fetch('{{ route('admin.print-failures.pending') }}', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;
                queue = data.failures || [];
                render();
            })
            .catch(() => {});
    }

    function findOrder(id) {
        return queue.find(x => String(x.id) === String(id));
    }

    function setRowBusy(id, v) {
        if (v) busy.add(id); else busy.delete(id);
        const row = list.querySelector('.lh-pf-row[data-id="' + id + '"]');
        if (row) row.querySelectorAll('button').forEach(b => b.disabled = v);
    }

    function dropRow(id) {
        queue = queue.filter(x => x.id !== id);
        busy.delete(id);
        render();
        poll();
    }

    function actPrint(id) {
        const f = findOrder(id);
        if (!f) return;
        // Open the KOT URL in a new window — kot.blade.php auto-fires
        // window.print() on load. Then ack the failure so the waiter
        // app drops the PRINTER OFFLINE pill. We trust the click; some
        // browsers don't reliably fire afterprint, so optimistic ack
        // beats leaving the sheet stuck open.
        const url = f.kitchen_ticket_url || ('/admin/orders/' + f.id + '/kitchen-ticket');
        window.open(url, '_blank');

        setRowBusy(id, true);
        fetch('{{ url('/admin/print-failures') }}/' + id + '/ack-printed', {
            method: 'POST',
            credentials: 'same-origin',
            headers: withCsrf(),
            body: '{}',
        })
            .then(r => r.json())
            .then(data => {
                setRowBusy(id, false);
                if (data.ok) {
                    if (window.toastr) toastr.success(data.message || 'Sent to print.');
                    dropRow(id);
                } else {
                    if (window.toastr) toastr.error(data.message || 'Could not record print.');
                }
            })
            .catch(() => {
                setRowBusy(id, false);
                if (window.toastr) toastr.error('Network error.');
            });
    }

    function actView(id) {
        const f = findOrder(id);
        if (!f) return;
        // Preview-only: append ?preview=1 so kot.blade.php skips its
        // auto window.print() — operator just wants to look at the
        // ticket, not fire a print job.
        const base = f.kitchen_ticket_url || ('/admin/orders/' + f.id + '/kitchen-ticket');
        const sep = base.indexOf('?') === -1 ? '?' : '&';
        window.open(base + sep + 'preview=1', '_blank');
    }

    function actRetry(id) {
        setRowBusy(id, true);
        fetch('{{ url('/admin/print-failures') }}/' + id + '/retry', {
            method: 'POST',
            credentials: 'same-origin',
            headers: withCsrf(),
            body: '{}',
        })
            .then(r => r.json())
            .then(data => {
                setRowBusy(id, false);
                if (data.ok) {
                    if (window.toastr) toastr.success(data.message || 'Reprint sent.');
                    dropRow(id);
                } else {
                    if (window.toastr) toastr.error(data.message || 'Network printer still offline.', '', { timeOut: 5000 });
                    poll(); // refresh reason
                }
            })
            .catch(() => {
                setRowBusy(id, false);
                if (window.toastr) toastr.error('Network error.');
            });
    }

    function actHandled(id) {
        if (!confirm('Mark this KOT as handled (e.g. you wrote it on paper)?')) return;
        setRowBusy(id, true);
        fetch('{{ url('/admin/print-failures') }}/' + id + '/mark-handled', {
            method: 'POST',
            credentials: 'same-origin',
            headers: withCsrf(),
            body: '{}',
        })
            .then(r => r.json())
            .then(data => {
                setRowBusy(id, false);
                if (data.ok) {
                    if (window.toastr) toastr.info(data.message || 'Marked handled.');
                    dropRow(id);
                }
            })
            .catch(() => { setRowBusy(id, false); });
    }

    list.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-act]');
        if (!btn) return;
        const row = btn.closest('.lh-pf-row');
        if (!row) return;
        const id = parseInt(row.dataset.id, 10);
        if (!id || busy.has(id)) return;
        const act = btn.dataset.act;
        if (act === 'print')   actPrint(id);
        else if (act === 'view')    actView(id);
        else if (act === 'retry')   actRetry(id);
        else if (act === 'handled') actHandled(id);
    });

    // Periodic poller — also fires once on page load so a returning
    // admin sees pending failures before the first 5s tick.
    poll();
    setInterval(poll, POLL_MS);
})();
</script>

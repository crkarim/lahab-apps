{{-- Command palette. Open with ⌘K (macOS) or Ctrl+K (others). Include once in the admin layout. --}}
<style>
    .cmdk-overlay {
        position: fixed; inset: 0; z-index: 10500;
        background: rgba(20, 20, 30, 0.55);
        backdrop-filter: blur(4px);
        display: none; align-items: flex-start; justify-content: center;
        padding: 14vh 16px 16px;
        animation: cmdk-fade 120ms ease-out;
    }
    .cmdk-overlay.is-open { display: flex; }
    @keyframes cmdk-fade { from { opacity: 0 } to { opacity: 1 } }

    .cmdk-panel {
        width: 100%; max-width: 620px;
        background: #fff; color: #1a1a1a;
        border-radius: 14px;
        box-shadow: 0 25px 60px rgba(0,0,0,0.25), 0 4px 12px rgba(0,0,0,0.08);
        overflow: hidden;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, sans-serif;
        animation: cmdk-rise 160ms cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes cmdk-rise { from { transform: translateY(-8px); opacity: 0 } to { transform: none; opacity: 1 } }

    .cmdk-input {
        width: 100%; border: 0; outline: 0; background: transparent;
        padding: 18px 20px;
        font-size: 17px; font-weight: 500;
        border-bottom: 1px solid #e5e5ea;
    }
    .cmdk-input::placeholder { color: #9a9a9f; font-weight: 400; }

    .cmdk-results { max-height: 50vh; overflow-y: auto; padding: 6px 0; }
    .cmdk-section { padding: 6px 16px 4px; font-size: 11px; font-weight: 600;
                    letter-spacing: 0.5px; text-transform: uppercase; color: #8e8e93; }

    .cmdk-item {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 16px; cursor: pointer;
        font-size: 14px; color: #1a1a1a;
        user-select: none;
    }
    .cmdk-item:hover, .cmdk-item.is-active {
        background: #E67E22; color: #fff;
    }
    .cmdk-item:hover .cmdk-subtitle, .cmdk-item.is-active .cmdk-subtitle { color: rgba(255,255,255,0.85); }
    .cmdk-icon {
        width: 28px; height: 28px; border-radius: 6px;
        display: inline-flex; align-items: center; justify-content: center;
        background: #f2f2f7; color: #5a5a60; font-size: 14px; flex-shrink: 0;
    }
    .cmdk-item.is-active .cmdk-icon, .cmdk-item:hover .cmdk-icon {
        background: rgba(255,255,255,0.25); color: #fff;
    }
    .cmdk-title { font-weight: 500; }
    .cmdk-subtitle { font-size: 12px; color: #8e8e93; margin-top: 1px; }
    .cmdk-item-body { flex: 1; min-width: 0; }
    .cmdk-item-body .cmdk-title,
    .cmdk-item-body .cmdk-subtitle { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .cmdk-empty { padding: 30px 20px; text-align: center; color: #8e8e93; font-size: 13px; }

    .cmdk-footer {
        display: flex; align-items: center; gap: 16px;
        padding: 10px 16px; background: #f7f7fa;
        border-top: 1px solid #e5e5ea;
        font-size: 11px; color: #6a6a70;
    }
    .cmdk-footer kbd {
        display: inline-block; margin-right: 4px;
        padding: 1px 6px; background: #fff; border: 1px solid #d8d8dd;
        border-radius: 4px; font-family: inherit; font-size: 10px; font-weight: 600;
        box-shadow: 0 1px 0 #d8d8dd;
    }
    .cmdk-footer .ml-auto { margin-left: auto; }
</style>

<div id="cmdk-overlay" class="cmdk-overlay" aria-hidden="true">
    <div class="cmdk-panel" role="dialog" aria-label="Command palette">
        <input id="cmdk-input" class="cmdk-input" type="text"
               placeholder="{{ translate('Search or jump to… (try: pos, orders, 123)') }}"
               autocomplete="off" spellcheck="false">
        <div id="cmdk-results" class="cmdk-results" role="listbox"></div>
        <div class="cmdk-footer">
            <span><kbd>↑</kbd><kbd>↓</kbd>{{ translate('navigate') }}</span>
            <span><kbd>↵</kbd> {{ translate('open') }}</span>
            <span><kbd>esc</kbd> {{ translate('close') }}</span>
            <span class="ml-auto"><kbd>⌘</kbd><kbd>K</kbd></span>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    try {

    // --- Static navigation catalog -------------------------------------------
    const NAV = [
        // Top daily tasks
        { s: 'Tasks',   t: 'POS — New Sale',            sub: 'Start a new in-restaurant order', u: @json(route('admin.pos.index')),                          i: '🛒' },
        { s: 'Tasks',   t: 'Dashboard',                 sub: 'Home',                             u: @json(route('admin.dashboard')),                          i: '📊' },

        // Orders
        { s: 'Orders',  t: 'In-Restaurant Orders',      sub: 'POS + Dine-in combined',           u: @json(route('admin.pos.orders')),                         i: '🧾' },
        { s: 'Orders',  t: 'Online Orders — All',       sub: 'Delivery orders',                  u: @json(route('admin.orders.list', ['all'])),               i: '📦' },
        { s: 'Orders',  t: 'Online Orders — Pending',   sub: '',                                  u: @json(route('admin.orders.list', ['pending'])),           i: '⏳' },
        { s: 'Orders',  t: 'Online Orders — Processing',sub: '',                                  u: @json(route('admin.orders.list', ['processing'])),        i: '🔥' },
        { s: 'Orders',  t: 'Online Orders — Delivered', sub: '',                                  u: @json(route('admin.orders.list', ['delivered'])),         i: '✅' },
        { s: 'Orders',  t: 'Running Tables',            sub: 'Live table sessions',              u: @json(route('admin.table.order.running')),                i: '🍽' },

        // Menu management
        { s: 'Menu',    t: 'Products',                  sub: 'Manage menu items',                u: @json(route('admin.product.list')),                       i: '🍔' },
        { s: 'Menu',    t: 'Add New Product',           sub: '',                                  u: @json(route('admin.product.add-new')),                    i: '➕' },
        { s: 'Menu',    t: 'Categories',                sub: '',                                  u: @json(route('admin.category.add')),                       i: '📂' },
        { s: 'Menu',    t: 'Cuisines',                  sub: '',                                  u: @json(route('admin.cuisine.add')),                        i: '🌶' },
        { s: 'Menu',    t: 'Addons',                    sub: '',                                  u: @json(route('admin.addon.add-new')),                      i: '🧂' },
        { s: 'Menu',    t: 'Tables',                    sub: 'Add or edit tables & zones',       u: @json(route('admin.table.list')),                         i: '🪑' },

        // People
        { s: 'People',  t: 'Customers',                 sub: 'Customer list',                    u: @json(route('admin.customer.list')),                      i: '👥' },
        { s: 'People',  t: 'Delivery Men',              sub: '',                                  u: @json(route('admin.delivery-man.list')),                  i: '🛵' },
        { s: 'People',  t: 'Employees',                 sub: '',                                  u: @json(route('admin.employee.list')),                      i: '👤' },

        // Promotions
        { s: 'Promotions', t: 'Banners',                sub: '',                                  u: @json(route('admin.banner.add-new')),                     i: '🖼' },
        { s: 'Promotions', t: 'Coupons',                sub: '',                                  u: @json(route('admin.coupon.add-new')),                     i: '🎟' },
        { s: 'Promotions', t: 'Send Notification',      sub: '',                                  u: @json(route('admin.notification.add-new')),               i: '🔔' },

        // Reports
        { s: 'Reports', t: 'Earning Report',            sub: '',                                  u: @json(route('admin.report.earning')),                     i: '💰' },
        { s: 'Reports', t: 'Order Report',              sub: '',                                  u: @json(route('admin.report.order')),                       i: '📈' },
        { s: 'Reports', t: 'Sale Report',               sub: '',                                  u: @json(route('admin.report.sale-report')),                 i: '📊' },

        // Settings (end)
        { s: 'Settings', t: 'Restaurant Setup',         sub: '',                                  u: @json(route('admin.business-settings.restaurant.restaurant-setup')), i: '🏪' },
        { s: 'Settings', t: 'Offline Payment Methods',  sub: 'bKash, Nagad, Rocket…',             u: @json(route('admin.business-settings.web-app.third-party.offline-payment.list')), i: '💳' },
        { s: 'Settings', t: 'Branches',                 sub: '',                                  u: @json(route('admin.branch.list')),                        i: '🏬' },
        { s: 'Settings', t: 'Profile',                  sub: '',                                  u: @json(route('admin.settings')),                           i: '⚙️' },
    ];

    // --- Elements ----------------------------------------------------------------
    const overlay = document.getElementById('cmdk-overlay');
    const input   = document.getElementById('cmdk-input');
    const results = document.getElementById('cmdk-results');
    let activeIdx = 0;
    let currentList = [];

    // --- Fuzzy match -------------------------------------------------------------
    function score(query, item) {
        if (!query) return 1;
        const q = query.toLowerCase();
        const hay = (item.t + ' ' + (item.sub || '') + ' ' + item.s).toLowerCase();
        if (hay.includes(q)) return 100 - hay.indexOf(q); // earlier hit = higher score
        // loose char-in-order match
        let i = 0;
        for (const ch of hay) { if (ch === q[i]) i++; if (i === q.length) break; }
        return i === q.length ? 20 : 0;
    }

    function buildList(query) {
        const q = (query || '').trim();
        const list = [];

        // Order-ID jump
        if (/^\d{2,}$/.test(q)) {
            list.push({
                s: 'Jump', t: 'Open Order #' + q, sub: 'View order details',
                u: @json(url('/admin/orders/details')) + '/' + q, i: '🔎'
            });
        }

        // Nav matches
        const matches = NAV
            .map(n => ({ n, sc: score(q, n) }))
            .filter(x => x.sc > 0)
            .sort((a, b) => b.sc - a.sc)
            .map(x => x.n);

        return list.concat(matches);
    }

    function render(list) {
        currentList = list;
        activeIdx = 0;
        if (!list.length) {
            results.innerHTML = '<div class="cmdk-empty">{{ translate("No matches. Try: pos, orders, products, branch…") }}</div>';
            return;
        }
        let html = '';
        let lastSection = null;
        list.forEach((item, idx) => {
            if (item.s !== lastSection) {
                html += '<div class="cmdk-section">' + item.s + '</div>';
                lastSection = item.s;
            }
            const cls = idx === activeIdx ? 'cmdk-item is-active' : 'cmdk-item';
            html += '<div class="' + cls + '" data-idx="' + idx + '" role="option">'
                 +    '<span class="cmdk-icon">' + (item.i || '•') + '</span>'
                 +    '<div class="cmdk-item-body">'
                 +      '<div class="cmdk-title">' + escapeHtml(item.t) + '</div>'
                 +      (item.sub ? '<div class="cmdk-subtitle">' + escapeHtml(item.sub) + '</div>' : '')
                 +    '</div>'
                 +  '</div>';
        });
        results.innerHTML = html;
    }

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[c]); }

    function setActive(idx) {
        if (!currentList.length) return;
        activeIdx = Math.max(0, Math.min(currentList.length - 1, idx));
        [...results.querySelectorAll('.cmdk-item')].forEach(el => el.classList.remove('is-active'));
        const el = results.querySelector('[data-idx="' + activeIdx + '"]');
        if (el) {
            el.classList.add('is-active');
            el.scrollIntoView({ block: 'nearest' });
        }
    }

    function open() {
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        input.value = '';
        render(buildList(''));
        setTimeout(() => input.focus(), 20);
    }
    function close() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
    }
    function navigate() {
        const item = currentList[activeIdx];
        if (item && item.u) window.location.href = item.u;
    }

    // --- Wiring ------------------------------------------------------------------
    document.addEventListener('keydown', function (e) {
        // ⌘K / Ctrl+K opens — don't hijack inside textarea/contenteditable
        if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
            const tag = (document.activeElement || {}).tagName;
            if (tag !== 'TEXTAREA' && !document.activeElement?.isContentEditable) {
                e.preventDefault();
                overlay.classList.contains('is-open') ? close() : open();
            }
        }
        if (!overlay.classList.contains('is-open')) return;
        if (e.key === 'Escape')     { e.preventDefault(); close(); }
        if (e.key === 'ArrowDown')  { e.preventDefault(); setActive(activeIdx + 1); }
        if (e.key === 'ArrowUp')    { e.preventDefault(); setActive(activeIdx - 1); }
        if (e.key === 'Enter')      { e.preventDefault(); navigate(); }
    });

    input.addEventListener('input', function () { render(buildList(input.value)); });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });
    results.addEventListener('mouseover', function (e) {
        const item = e.target.closest('.cmdk-item');
        if (item) setActive(Number(item.dataset.idx));
    });
    results.addEventListener('click', function (e) {
        const item = e.target.closest('.cmdk-item');
        if (item) { setActive(Number(item.dataset.idx)); navigate(); }
    });

    } catch (err) {
        if (window && window.console) console.error('Command palette init failed:', err);
        // Swallow — must never break the admin layout's theme JS.
    }
})();
</script>

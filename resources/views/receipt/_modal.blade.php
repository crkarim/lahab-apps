{{-- Reusable Print Receipt modal.
     Include once per page. Any element with class="print-receipt-btn"
     and data-order-id="…" (or data-print-url for non-admin routes) opens it.
     Required once on the page:
       @include('receipt._modal')
--}}
<style>
    /* Branded modal chrome for the print-preview. Scoped to #receipt-modal so
       styling never leaks to the thermal receipt inside. */
    #receipt-modal .modal-content {
        border: 0; border-radius: 14px; overflow: hidden;
        box-shadow: 0 24px 48px -16px rgba(107,47,26,0.18);
    }
    #receipt-modal .modal-header {
        background: linear-gradient(135deg, #FFF3E6 0%, #FFFFFF 70%);
        border-bottom: 1px solid #f0e1d0;
        padding: 14px 20px;
    }
    #receipt-modal .modal-title {
        display: inline-flex; align-items: center; gap: 10px;
        font-weight: 700; color: #6B2F1A;
    }
    #receipt-modal .modal-title::before {
        content: ""; width: 6px; height: 22px; border-radius: 3px;
        background: #E67E22; display: inline-block;
    }
    #receipt-modal .modal-header .close {
        width: 32px; height: 32px; border-radius: 8px;
        background: rgba(230,126,34,0.08); color: #6B2F1A;
        display: inline-flex; align-items: center; justify-content: center;
        opacity: 1; transition: background 120ms;
    }
    #receipt-modal .modal-header .close:hover { background: rgba(230,126,34,0.18); }
    #receipt-modal #receipt-modal-actions { border-bottom: 1px dashed #eceef0; }
    #receipt-modal #receipt-modal-print {
        padding: 10px 20px; font-weight: 600; border-radius: 10px;
        box-shadow: 0 6px 16px -6px rgba(230,126,34,0.5);
    }
    #receipt-modal #receipt-modal-actions hr { display: none; }
    #receipt-modal .modal-body { background: #fafaf8; }
</style>

<div class="modal fade" id="receipt-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 480px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0">{{ translate('Print Receipt') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="px-4 pt-3 pb-3 text-center non-printable" id="receipt-modal-actions">
                <button type="button" class="btn btn-primary" id="receipt-modal-print">
                    <i class="tio-print"></i> {{ translate('Proceed — printer ready') }}
                </button>
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    {{ translate('Cancel') }}
                </button>
            </div>

            <div class="modal-body p-3">
                <div id="receipt-modal-preview" style="display: flex; justify-content: center;">
                    <div class="text-muted p-5">{{ translate('Loading...') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('script_2')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
    (function () {
        'use strict';

        var baseUrl = @json(url('/'));

        function openReceipt(orderId, fragmentUrl) {
            var $preview = $('#receipt-modal-preview');
            $preview.html('<div class="text-muted p-5">{{ translate("Loading...") }}</div>');
            $('#receipt-modal').modal('show');

            $.get({
                url: fragmentUrl,
                dataType: 'json',
                success: function (data) {
                    $preview.html(data.html);
                    $preview.find('.r-barcode').each(function () {
                        try {
                            JsBarcode(this, this.dataset.code, {
                                format: 'CODE128', displayValue: false, width: 2, height: 40, margin: 0
                            });
                        } catch (e) {}
                    });
                },
                error: function () {
                    $preview.html('<div class="text-danger p-5">{{ translate("Failed to load receipt") }}</div>');
                }
            });
        }

        $(document).on('click', '.print-receipt-btn', function (e) {
            e.preventDefault();
            var orderId = $(this).data('order-id');
            var url = $(this).data('fragment-url') || (baseUrl + '/admin/orders/' + orderId + '/receipt-fragment');
            openReceipt(orderId, url);
        });

        $('#receipt-modal-print').on('click', function () {
            // Clone the receipt to a body-level #print-host so the print CSS can
            // hide everything else (modal backdrop + page chrome) without leaving
            // blank pages from collapsed-but-sized containers. Removed after print.
            var preview = document.getElementById('receipt-modal-preview');
            var rDoc = preview ? preview.querySelector('.r-doc') : null;
            if (!rDoc) { window.print(); return; }

            var prev = document.getElementById('print-host');
            if (prev && prev.parentNode) prev.parentNode.removeChild(prev);

            var host = document.createElement('div');
            host.id = 'print-host';
            host.appendChild(rDoc.cloneNode(true));
            document.body.appendChild(host);

            var cleanup = function () {
                if (host && host.parentNode) host.parentNode.removeChild(host);
                window.removeEventListener('afterprint', cleanup);
            };
            window.addEventListener('afterprint', cleanup);
            // Fallback for browsers that don't fire afterprint reliably.
            setTimeout(cleanup, 2000);

            window.print();
        });
    })();
</script>
@endpush

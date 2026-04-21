<style>
    /* All rules scoped to .r-doc so they don't leak into the admin layout
       when the partial is injected into a modal preview. */
    .r-doc {
        font-family: "SF Mono", Menlo, Consolas, "Courier New", monospace;
        width: 80mm;
        max-width: 100%;
        margin: 0 auto;
        padding: 4mm;
        color: #000;
        background: #fff;
        font-size: 12px;
        line-height: 1.35;
        box-sizing: border-box;
        page-break-after: always;
    }
    .r-doc:last-child { page-break-after: auto; }
    .r-doc *, .r-doc *::before, .r-doc *::after { box-sizing: border-box; }

    .r-doc .r-shop { text-align: center; margin-bottom: 4mm; }
    .r-doc .r-logo { max-width: 60mm; max-height: 18mm; margin-bottom: 2mm; object-fit: contain; }
    .r-doc .r-name { font-size: 15px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
    .r-doc .r-sub  { font-size: 11px; margin-top: 1mm; }

    .r-doc .r-head-meta {
        display: flex; justify-content: space-between;
        font-size: 11px; margin-top: 2mm; padding: 2mm 0;
        border-top: 1px dashed #000; border-bottom: 1px dashed #000;
    }
    .r-doc .r-meta { font-size: 11px; margin-top: 2mm; }

    .r-doc .r-items { margin: 2mm 0; }
    .r-doc .r-item-row { display: flex; justify-content: space-between; gap: 4mm; margin-bottom: 1.5mm; }
    .r-doc .r-item-name { flex: 1; }
    .r-doc .r-item-name small { display: block; font-size: 10px; color: #444; margin-left: 4mm; }
    .r-doc .r-item-amt { white-space: nowrap; min-width: 18mm; text-align: right; }

    .r-doc .r-divider { border-top: 1px dashed #000; margin: 2mm 0; }

    .r-doc .r-totals-row { display: flex; justify-content: space-between; font-size: 11px; margin: 0.8mm 0; }
    .r-doc .r-grand { font-size: 14px; font-weight: 800; margin-top: 2mm; padding-top: 2mm; border-top: 1px solid #000; }

    .r-doc .r-payments { margin-top: 2mm; }
    .r-doc .r-pay-row { display: flex; justify-content: space-between; font-size: 11px; }

    .r-doc .r-footer { text-align: center; margin-top: 4mm; font-size: 10px; }
    .r-doc .r-barcode-wrap { text-align: center; margin-top: 3mm; }
    .r-doc .r-barcode { height: 38px; max-width: 100%; display: block; margin: 0 auto; }
    .r-doc .r-verify { font-size: 9px; color: #444; margin-top: 1mm; letter-spacing: 1px; }

    .r-doc .cut-marker {
        text-align: center; font-size: 10px; color: #666;
        letter-spacing: 2px; margin: 6mm 0 4mm;
        border-top: 1px dashed #000; padding-top: 2mm;
    }

    /* Print: isolate the receipt on its own 80mm page. Two cases:
       1) Standalone pages (/r/{token}, KOT): body contains only .r-doc blocks — hide nothing extra.
       2) Modal preview: JS clones .r-doc into body-level #print-host; hide every other body child. */
    @media print {
        @page { size: 80mm auto; margin: 0; }
        html, body { background: #fff; margin: 0 !important; padding: 0 !important; }
        /* If a #print-host exists, hide every other body child. */
        body:has(#print-host) > *:not(#print-host) { display: none !important; }
        #print-host, #print-host * { visibility: visible; }
        #print-host .r-doc {
            box-shadow: none;
            padding: 2mm;
            width: 80mm;
            margin: 0 auto;
        }
        .no-print { display: none !important; }
    }
</style>

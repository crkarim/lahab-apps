<?php

namespace App\Services\Printer;

use App\CentralLogics\Helpers;
use App\Model\AddOn;
use App\Model\Order;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

/**
 * Formats an Order into ESC/POS bytes for the kitchen's 80mm thermal
 * printer. Mirrors the layout of the existing browser-rendered KOT
 * (admin-views/order/kot.blade.php) so paper KOTs and screen KOTs
 * carry identical information — kitchen staff can switch between the
 * two without learning a new layout.
 *
 * Print is best-effort. Returns ['ok' => bool, 'skipped' => bool,
 * 'error' => ?string] so callers can store the outcome on the order
 * + show an honest toast on the waiter app. Skipped = printer is
 * disabled / not configured (no error, just nothing to print).
 */
class KitchenPrinter
{
    private string $ip;
    private int    $port;
    private int    $timeoutSeconds;
    private bool   $enabled;
    private int    $widthChars;

    public function __construct(?array $config = null)
    {
        $config ??= ReceiptPrinter::config();
        $this->ip             = (string) ($config['ip'] ?? '');
        $this->port           = (int)    ($config['port'] ?? 9100);
        $this->timeoutSeconds = (int)    ($config['timeout_seconds'] ?? 5);
        $this->enabled        = (bool)   ($config['enabled'] ?? false);
        $this->widthChars     = (int)    ($config['width_chars'] ?? 48);
    }

    /**
     * Send a KOT for the given order to the network printer. Idempotent:
     * the order's `kot_print_count` is bumped on each successful send so
     * a "REPRINT" tag can be placed on subsequent paper copies — same
     * convention as the admin-side KOT view.
     *
     * @return array{ok: bool, skipped: bool, error: ?string}
     */
    public function printOrder(Order $order, bool $isReprint = false): array
    {
        if (!$this->enabled) {
            return ['ok' => false, 'skipped' => true, 'error' => null];
        }
        if ($this->ip === '') {
            return ['ok' => false, 'skipped' => true, 'error' => 'Printer IP is not configured.'];
        }

        try {
            $order->loadMissing(['details', 'customer', 'branch', 'table', 'placedBy.role']);

            $connector = new NetworkPrintConnector($this->ip, $this->port, $this->timeoutSeconds);
            $printer   = new Printer($connector);

            $this->renderHeader($printer, $order, $isReprint);
            $this->renderMeta($printer, $order);
            $this->renderItems($printer, $order);
            $this->renderFooter($printer, $order);

            $printer->cut();
            $printer->close();

            // Bump print count so reprints are flagged.
            $order->increment('kot_print_count');

            return ['ok' => true, 'skipped' => false, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'skipped' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Supplementary KOT — prints only the newly appended detail rows
     * with a "ROUND N · ADDITIONAL ITEMS" banner. Used by the Add-items
     * flow so the kitchen sees just what's new and doesn't re-cook the
     * original lines.
     *
     * @param array<int, int> $newDetailIds
     * @return array{ok: bool, skipped: bool, error: ?string}
     */
    public function printSupplementary(Order $order, array $newDetailIds, int $round): array
    {
        if (!$this->enabled) {
            return ['ok' => false, 'skipped' => true, 'error' => null];
        }
        if ($this->ip === '') {
            return ['ok' => false, 'skipped' => true, 'error' => 'Printer IP is not configured.'];
        }
        if (empty($newDetailIds)) {
            return ['ok' => false, 'skipped' => true, 'error' => 'No new items to print.'];
        }

        try {
            $order->loadMissing(['details', 'customer', 'branch', 'table', 'placedBy.role']);

            // Only print the just-appended lines.
            $newOnly = $order->details->whereIn('id', $newDetailIds);
            // Swap the relation cache so renderItems/renderMeta operate
            // on the filtered subset without needing a second code path.
            $order->setRelation('details', $newOnly->values());

            $connector = new NetworkPrintConnector($this->ip, $this->port, $this->timeoutSeconds);
            $printer   = new Printer($connector);

            $this->renderSupplementaryHeader($printer, $order, $round);
            $this->renderMeta($printer, $order);
            $this->renderItems($printer, $order);
            $this->renderFooter($printer, $order);

            $printer->cut();
            $printer->close();

            $order->increment('kot_print_count');
            return ['ok' => true, 'skipped' => false, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'skipped' => false, 'error' => $e->getMessage()];
        }
    }

    private function renderSupplementaryHeader(Printer $printer, Order $order, int $round): void
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        $printer->setEmphasis(true);
        $printer->setTextSize(1, 1);
        $printer->text("*** SUPPLEMENTARY KOT ***\n");
        $printer->text("ADDITIONAL ITEMS · ROUND $round\n");
        $printer->setEmphasis(false);

        // Parent KOT number — operators match the supplementary slip
        // back to the original by number, so it gets the big-text spot.
        $printer->setTextSize(2, 2);
        $printer->setEmphasis(true);
        $printer->text(($order->kot_number ?: '—') . "\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);

        if ($order->order_type === 'pos' || $order->order_type === 'take_away') {
            $printer->feed(1);
            $printer->setEmphasis(true);
            $printer->text(str_repeat('=', $this->widthChars) . "\n");
            $printer->text("⚠ TAKE-AWAY · PACK FOR CUSTOMER\n");
            $printer->text(str_repeat('=', $this->widthChars) . "\n");
            $printer->setEmphasis(false);
        }
    }

    private function renderHeader(Printer $printer, Order $order, bool $isReprint): void
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        // Big, unmissable label so the kitchen can scan a stack of
        // tickets at a glance.
        $printer->setEmphasis(true);
        $printer->setTextSize(1, 1);
        $printer->text(($isReprint ? "*** REPRINT ***\n" : "KITCHEN ORDER TICKET\n"));
        $printer->setEmphasis(false);

        // KOT number — largest text on the page (2x2)
        $printer->setTextSize(2, 2);
        $printer->setEmphasis(true);
        $printer->text(($order->kot_number ?: '—') . "\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);

        // Take-away / delivery banner — boxed so packaging staff see it.
        if ($order->order_type === 'pos' || $order->order_type === 'take_away') {
            $printer->feed(1);
            $printer->setEmphasis(true);
            $printer->text(str_repeat('=', $this->widthChars) . "\n");
            $printer->text("⚠ TAKE-AWAY · PACK FOR CUSTOMER\n");
            $printer->text(str_repeat('=', $this->widthChars) . "\n");
            $printer->setEmphasis(false);
        } elseif ($order->order_type === 'delivery') {
            $printer->feed(1);
            $printer->setEmphasis(true);
            $printer->text(str_repeat('=', $this->widthChars) . "\n");
            $printer->text("⚠ DELIVERY · PACK FOR RIDER\n");
            $printer->text(str_repeat('=', $this->widthChars) . "\n");
            $printer->setEmphasis(false);
        }
    }

    private function renderMeta(Printer $printer, Order $order): void
    {
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->feed(1);

        $printer->setEmphasis(true);
        $printer->text("Order #{$order->id}\n");
        $printer->setEmphasis(false);

        if ($order->table) {
            $tableLine = "Table: " . $order->table->number;
            if (!empty($order->table->zone)) {
                $tableLine .= " · " . $order->table->zone;
            }
            $printer->text($tableLine . "\n");
        }

        if ($order->customer) {
            $custName = trim(($order->customer->f_name ?? '') . ' ' . ($order->customer->l_name ?? ''));
            if ($custName !== '') {
                $printer->text("Customer: $custName\n");
            }
        }

        if ($order->placedBy) {
            $placedBy = trim(($order->placedBy->f_name ?? '') . ' ' . ($order->placedBy->l_name ?? ''));
            if ($placedBy === '') {
                $placedBy = $order->placedBy->email ?? 'Staff';
            }
            $role = $order->placedBy->role?->name;
            $printer->text("Placed by: $placedBy" . ($role ? " · $role" : '') . "\n");
        }

        if (!empty($order->order_note)) {
            $printer->setEmphasis(true);
            $printer->text("Note: {$order->order_note}\n");
            $printer->setEmphasis(false);
        }

        $printer->text(str_repeat('-', $this->widthChars) . "\n");
    }

    private function renderItems(Printer $printer, Order $order): void
    {
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        // Header: QTY ┊ ITEM
        $printer->setEmphasis(true);
        $printer->text(sprintf("%-4s %s\n", 'QTY', 'ITEM'));
        $printer->setEmphasis(false);
        $printer->text(str_repeat('-', $this->widthChars) . "\n");

        foreach ($order->details as $item) {
            $product = is_array($item->product_details)
                ? $item->product_details
                : (json_decode($item->product_details, true) ?: []);

            $variations = is_array($item->variation)
                ? $item->variation
                : (json_decode($item->variation, true) ?: []);

            $addonIds  = is_array($item->add_on_ids)  ? $item->add_on_ids  : (json_decode($item->add_on_ids, true)  ?: []);
            $addonQtys = is_array($item->add_on_qtys) ? $item->add_on_qtys : (json_decode($item->add_on_qtys, true) ?: []);

            // Main line: QTY × NAME — emphasised + 2x height for the
            // kitchen's eyes. Wrap if the name is long.
            $name = $product['name'] ?? 'Item';
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 2);
            $printer->text(sprintf("%-3s× %s\n", (string) $item->quantity, $name));
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);

            // Variations
            if (!empty($variations) && is_array($variations)) {
                foreach ($variations as $v) {
                    $label = '';
                    if (is_array($v)) {
                        if (!empty($v['value'])) {
                            $label = $v['value'];
                        } elseif (!empty($v['values']) && is_array($v['values'])) {
                            $label = collect($v['values'])
                                ->map(fn ($x) => is_array($x) ? ($x['label'] ?? $x['level'] ?? $x['name'] ?? '') : (string) $x)
                                ->filter()
                                ->implode(', ');
                        } else {
                            $label = $v['type'] ?? $v['Size'] ?? '';
                        }
                        $name = $v['name'] ?? null;
                    }
                    if ($label !== '') {
                        $line = "  • " . ($name ? "$name: " : '') . $label;
                        $printer->text($line . "\n");
                    }
                }
            }

            // Add-ons (resolved by id so name/qty come from the canonical
            // add_ons row even if the cart copy was stale)
            if (!empty($addonIds) && is_array($addonIds)) {
                foreach ($addonIds as $i => $aid) {
                    $addonName = collect($product['add_ons'] ?? [])
                        ->firstWhere('id', $aid)['name'] ?? null;
                    if (!$addonName) {
                        $addonName = AddOn::find($aid)->name ?? 'Addon';
                    }
                    $aqty = (int) ($addonQtys[$i] ?? 1);
                    $printer->text(sprintf("  + %s× %s\n", $aqty, $addonName));
                }
            }

            // Per-line note (waiter app sets product_details.line_note)
            if (!empty($product['line_note'])) {
                $printer->setEmphasis(true);
                $printer->text("  ✎ NOTE: " . $product['line_note'] . "\n");
                $printer->setEmphasis(false);
            }

            $printer->text(str_repeat('-', $this->widthChars) . "\n");
        }
    }

    private function renderFooter(Printer $printer, Order $order): void
    {
        $printer->setJustification(Printer::JUSTIFY_RIGHT);
        $sentAt = ($order->kot_sent_at ?? now())->format('d M Y · H:i');
        $printer->text("Sent: $sentAt\n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->feed(2);
    }
}

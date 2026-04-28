<?php

namespace App\Services\Printer;

use App\CentralLogics\Helpers;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

/**
 * Thin wrapper around mike42/escpos-php for the network 80mm thermal
 * printer the restaurant already uses for the admin panel POS receipts.
 *
 * Why a wrapper: the Flutter waiter app will eventually trigger prints
 * via /api/v1/waiter/order/{id}/print-receipt, and we want one place
 * that knows how to reach the printer (IP/port from business_settings)
 * + how to format the standard 80mm receipt. Phase 0 only exercises the
 * test-page path; richer formatting lands when we wire real receipts.
 */
class ReceiptPrinter
{
    private string $ip;
    private int    $port;
    private int    $timeoutSeconds;

    public function __construct(?array $config = null)
    {
        $config ??= self::config();
        $this->ip             = (string) ($config['ip'] ?? '');
        $this->port           = (int)    ($config['port'] ?? 9100);
        $this->timeoutSeconds = (int)    ($config['timeout_seconds'] ?? 5);
    }

    /**
     * Reads the saved printer settings from business_settings.
     *
     * `print_path` decides who actually owns the TCP socket:
     *   - 'device' (default for cloud-hosted admin panels) — the waiter
     *     Flutter app prints over its own LAN connection, the server
     *     skips its TCP attempt entirely. This is the only path that
     *     works when the admin panel runs on the cloud and the printer
     *     lives on a restaurant LAN.
     *   - 'server' — server prints over network. Needs the printer's IP
     *     to be reachable from the server (typical for self-hosted
     *     admin panels on the same LAN as the printer).
     */
    public static function config(): array
    {
        $raw = Helpers::get_business_settings('receipt_printer');
        $val = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $path = (string) ($val['print_path'] ?? 'device');
        if (!in_array($path, ['device', 'server'], true)) $path = 'device';
        return [
            'ip'              => $val['ip']              ?? '',
            'port'            => (int) ($val['port']     ?? 9100),
            'enabled'         => (bool) ($val['enabled'] ?? false),
            'width_chars'     => (int) ($val['width_chars'] ?? 48),
            'timeout_seconds' => (int) ($val['timeout_seconds'] ?? 5),
            'print_path'      => $path,
        ];
    }

    /**
     * Print a small "this is a test" receipt. Returns ['ok' => bool,
     * 'error' => ?string] so the caller can show an honest toast — same
     * contract we used for the SMS gateway plumbing.
     */
    public function printTestPage(): array
    {
        if ($this->ip === '') {
            return ['ok' => false, 'error' => 'Printer IP is not configured.'];
        }

        try {
            $connector = new NetworkPrintConnector($this->ip, $this->port, $this->timeoutSeconds);
            $printer   = new Printer($connector);

            $shopName = Helpers::get_business_settings('restaurant_name') ?: 'Lahab';

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->setTextSize(2, 2);
            $printer->text($shopName . "\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            $printer->text("Receipt printer test\n");
            $printer->feed(1);

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(str_repeat('-', 48) . "\n");
            $printer->text("Printer IP : {$this->ip}:{$this->port}\n");
            $printer->text("Timestamp  : " . now()->format('d M Y · H:i:s') . "\n");
            $printer->text("Status     : OK\n");
            $printer->text(str_repeat('-', 48) . "\n");
            $printer->feed(1);

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("If you can read this,\n");
            $printer->text("the network connection works.\n");
            $printer->feed(2);

            $printer->cut();
            $printer->close();

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

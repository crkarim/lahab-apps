<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Browser-native KOT print audit columns. When the network printer is
 * down, the admin panel's print-failure bottom sheet lets counter staff
 * print the KOT through the browser's native print dialog (USB / shared
 * printer / next-best fallback). Clicking Print acks the failure to the
 * waiter app, but we still want to know LATER whether the KOT was
 * physically reached the kitchen via paper or just acked-and-forgotten.
 *
 * `kot_native_printed_at` answers "did someone trigger a paper print
 * via the admin panel?" `kot_native_printed_by` answers "who did?".
 * Both stay null when the kitchen got the order via the original network
 * print; populated only when the bottom-sheet failover path was used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('kot_native_printed_at')->nullable()->after('print_failure_handled_by');
            $table->unsignedBigInteger('kot_native_printed_by')->nullable()->after('kot_native_printed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['kot_native_printed_at', 'kot_native_printed_by']);
        });
    }
};

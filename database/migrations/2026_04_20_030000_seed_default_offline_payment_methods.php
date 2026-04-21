<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $methods = [
            [
                'method_name'         => 'bKash',
                'payment_note'        => 'Send to 01XXXXXXXXX and enter the transaction ID.',
                'method_fields'       => json_encode([
                    ['field_name' => 'sender_number', 'placeholder' => 'Sender Mobile Number'],
                    ['field_name' => 'trx_id',        'placeholder' => 'Transaction ID'],
                ]),
                'method_informations' => json_encode([]),
                'status'              => 1,
            ],
            [
                'method_name'         => 'Nagad',
                'payment_note'        => 'Send to 01XXXXXXXXX and enter the transaction ID.',
                'method_fields'       => json_encode([
                    ['field_name' => 'sender_number', 'placeholder' => 'Sender Mobile Number'],
                    ['field_name' => 'trx_id',        'placeholder' => 'Transaction ID'],
                ]),
                'method_informations' => json_encode([]),
                'status'              => 1,
            ],
            [
                'method_name'         => 'Rocket',
                'payment_note'        => 'Send to 01XXXXXXXXX and enter the transaction ID.',
                'method_fields'       => json_encode([
                    ['field_name' => 'sender_number', 'placeholder' => 'Sender Mobile Number'],
                    ['field_name' => 'trx_id',        'placeholder' => 'Transaction ID'],
                ]),
                'method_informations' => json_encode([]),
                'status'              => 1,
            ],
        ];

        foreach ($methods as $m) {
            $exists = DB::table('offline_payment_methods')->where('method_name', $m['method_name'])->exists();
            if (!$exists) {
                DB::table('offline_payment_methods')->insert(array_merge($m, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('offline_payment_methods')
            ->whereIn('method_name', ['bKash', 'Nagad', 'Rocket'])
            ->delete();
    }
};

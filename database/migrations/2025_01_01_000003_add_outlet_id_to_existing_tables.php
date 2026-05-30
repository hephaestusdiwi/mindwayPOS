<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'transactions',
        'stock_ledger',
        'purchase_orders',
        'goods_receipts',
        'stock_adjustments',
    ];

    public function up(): void
    {
        // 1. Insert outlet default
        $defaultOutletId = DB::table('outlets')->insertGetId([
            'name'       => 'Outlet Utama',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Loop setiap tabel
        foreach ($this->tables as $tableName) {
            // Step A: tambah kolom nullable + FK dengan RESTRICT
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('outlet_id')
                      ->nullable()
                      ->after('id')
                      ->constrained()
                      ->restrictOnDelete(); // ← kunci fix-nya, ganti dari nullOnDelete
            });

            // Step B: backfill data lama
            DB::table($tableName)
                ->whereNull('outlet_id')
                ->update(['outlet_id' => $defaultOutletId]);

            // Step C: jadikan NOT NULL — aman karena FK sudah RESTRICT
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('outlet_id')->nullable(false)->change();
            });
        }

        // 3. stock_opnames (opsional)
        if (Schema::hasTable('stock_opnames')) {
            Schema::table('stock_opnames', function (Blueprint $table) {
                $table->foreignId('outlet_id')
                      ->nullable()
                      ->after('id')
                      ->constrained()
                      ->restrictOnDelete();
            });
            DB::table('stock_opnames')
                ->whereNull('outlet_id')
                ->update(['outlet_id' => $defaultOutletId]);
            Schema::table('stock_opnames', function (Blueprint $table) {
                $table->unsignedBigInteger('outlet_id')->nullable(false)->change();
            });
        }

        // 4. Assign user existing ke outlet default
        DB::table('users')->get()->each(function ($user) use ($defaultOutletId) {
            DB::table('outlet_user')->insertOrIgnore([
                'outlet_id'  => $defaultOutletId,
                'user_id'    => $user->id,
                'role'       => $user->role ?? 'cashier',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        $tables = $this->tables;

        if (Schema::hasTable('stock_opnames')) {
            $tables[] = 'stock_opnames';
        }

        foreach ($tables as $tableName) {
            if (Schema::hasColumn($tableName, 'outlet_id')) {
                Schema::table($tableName, function (Blueprint $t) {
                    $t->dropForeign(['outlet_id']);
                    $t->dropColumn('outlet_id');
                });
            }
        }
    }
};
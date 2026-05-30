<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Rename tabel lama supaya data tidak hilang ─────────────────────
        Schema::rename('stock_adjustments', 'stock_adjustments_legacy');

        // ── 2. Buat tabel dokumen baru (header) ───────────────────────────────
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adj_number', 30)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('adj_date');
            $table->enum('type', ['addition', 'reduction', 'recount'])->default('recount');
            $table->string('reason', 200);
            $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── 3. Buat tabel items (sudah ada dari phase2, skip kalau ada) ───────
        if (!Schema::hasTable('stock_adjustment_items')) {
            Schema::create('stock_adjustment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->decimal('qty_system', 15, 4);
                $table->decimal('qty_actual', 15, 4);
                $table->decimal('qty_difference', 15, 4);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::rename('stock_adjustments_legacy', 'stock_adjustments');
    }
};
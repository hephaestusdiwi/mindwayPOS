<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Suppliers ──────────────────────────────────────────────────────
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name');
                $table->string('contact_person')->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('city', 100)->nullable();
                $table->string('npwp', 30)->nullable();
                $table->enum('payment_terms', ['cash', 'net7', 'net14', 'net30', 'net60'])->default('cash');
                $table->string('bank_name', 100)->nullable();
                $table->string('bank_account', 50)->nullable();
                $table->string('bank_account_name', 100)->nullable();
                $table->text('notes')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 2. Units of Measure ───────────────────────────────────────────────
        if (!Schema::hasTable('units_of_measure')) {
            Schema::create('units_of_measure', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50)->unique();
                $table->string('symbol', 20)->unique();
                $table->boolean('is_base')->default(false);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('uom_conversions')) {
            Schema::create('uom_conversions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('from_uom_id')->constrained('units_of_measure')->cascadeOnDelete();
                $table->foreignId('to_uom_id')->constrained('units_of_measure')->cascadeOnDelete();
                $table->decimal('factor', 15, 6);
                $table->timestamps();
                $table->unique(['from_uom_id', 'to_uom_id']);
            });
        }

        // ── 3. Tambah kolom ke tabel products (skip jika sudah ada) ──────────
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'uom_id')) {
                $table->foreignId('uom_id')->nullable()->after('category_id')
                      ->constrained('units_of_measure')->nullOnDelete();
            }
            if (!Schema::hasColumn('products', 'cost_price')) {
                $table->decimal('cost_price', 15, 2)->default(0)->after('price');
            }
            if (!Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode', 100)->nullable()->unique()->after('sku');
            }
            if (!Schema::hasColumn('products', 'min_stock')) {
                $table->integer('min_stock')->default(0)->after('stock');
            }
            if (!Schema::hasColumn('products', 'reorder_point')) {
                $table->integer('reorder_point')->default(0)->after('min_stock');
            }
            if (!Schema::hasColumn('products', 'current_stock')) {
                $table->decimal('current_stock', 15, 4)->default(0)->after('reorder_point');
            }
        });

        // ── 4. Stock Ledger ───────────────────────────────────────────────────
        if (!Schema::hasTable('stock_ledger')) {
            Schema::create('stock_ledger', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('ref_type', 50);
                $table->unsignedBigInteger('ref_id');
                $table->string('ref_number', 50)->nullable();
                $table->decimal('qty_in', 15, 4)->default(0);
                $table->decimal('qty_out', 15, 4)->default(0);
                $table->decimal('qty_balance', 15, 4);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->decimal('total_cost', 15, 2)->default(0);
                $table->string('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['product_id', 'created_at']);
                $table->index(['ref_type', 'ref_id']);
            });
        }

        // ── 5. Purchase Orders ────────────────────────────────────────────────
        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('po_number', 30)->unique();
                $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('po_date');
                $table->date('expected_date')->nullable();
                $table->enum('status', ['draft', 'approved', 'partial', 'received', 'cancelled'])->default('draft');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->foreignId('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
                $table->decimal('qty_ordered', 15, 4);
                $table->decimal('qty_received', 15, 4)->default(0);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->decimal('total_price', 15, 2);
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }

        // ── 6. Goods Receipts (GRN) ───────────────────────────────────────────
        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('grn_number', 30)->unique();
                $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
                $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
                $table->date('received_date');
                $table->string('supplier_delivery_note', 100)->nullable();
                $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
                $table->decimal('total_cost', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
                $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->foreignId('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
                $table->decimal('qty_received', 15, 4);
                $table->decimal('qty_in_base_uom', 15, 4);
                $table->decimal('unit_cost', 15, 2);
                $table->decimal('total_cost', 15, 2);
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }

        // ── 7. Purchase Invoices (Tagihan Beli / AP) ──────────────────────────
        if (!Schema::hasTable('purchase_invoices')) {
            Schema::create('purchase_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number', 50)->unique();
                $table->string('internal_number', 30)->unique();
                $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
                $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->date('invoice_date');
                $table->date('due_date');
                $table->enum('status', ['unpaid', 'partial', 'paid', 'cancelled'])->default('unpaid');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->decimal('remaining_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('purchase_invoice_payments')) {
            Schema::create('purchase_invoice_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->date('payment_date');
                $table->decimal('amount', 15, 2);
                $table->string('payment_method', 50)->default('transfer');
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // ── 8. Stock Adjustments (Penyesuaian Stok) ──────────────────────────
        if (!Schema::hasTable('stock_adjustments')) {
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
        }

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

        // ── 9. Supplier Returns (Retur ke Supplier) ───────────────────────────
        if (!Schema::hasTable('supplier_returns')) {
            Schema::create('supplier_returns', function (Blueprint $table) {
                $table->id();
                $table->string('return_number', 30)->unique();
                $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
                $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->date('return_date');
                $table->enum('status', ['draft', 'confirmed', 'cancelled'])->default('draft');
                $table->string('reason', 200);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('supplier_return_items')) {
            Schema::create('supplier_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_return_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->restrictOnDelete();
                $table->decimal('qty_returned', 15, 4);
                $table->decimal('unit_cost', 15, 2);
                $table->decimal('total_amount', 15, 2);
                $table->string('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
        Schema::dropIfExists('supplier_returns');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('purchase_invoice_payments');
        Schema::dropIfExists('purchase_invoices');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('stock_ledger');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'uom_id')) {
                $table->dropForeign(['uom_id']);
                $table->dropColumn('uom_id');
            }
            foreach (['cost_price', 'barcode', 'min_stock', 'reorder_point', 'current_stock'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('uom_conversions');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('suppliers');
    }
};
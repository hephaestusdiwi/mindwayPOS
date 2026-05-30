<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Dokumen stok opname ───────────────────────────────────────────────
        Schema::create('stock_opname_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique(); // OP-20260322-001
            $table->enum('status', ['draft', 'confirmed'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ── Item per dokumen ──────────────────────────────────────────────────
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                  ->constrained('stock_opname_documents')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->integer('quantity_system');   // stok saat dokumen dibuat
            $table->integer('quantity_actual')->nullable(); // input user
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opname_documents');
    }
};
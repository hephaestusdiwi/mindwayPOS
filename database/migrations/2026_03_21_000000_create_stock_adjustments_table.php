<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void 
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('min_stock')->default(5)->after('stock');
        });

        Schema::create('stock_adjusments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->enum('type', ['opname', 'in', 'out'])->default('opname');

            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->integer('quantity_diff');

            $table->string('notes')->nullable();
            $table->string('reference')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void 
    {
        Schema::dropIfExists('stock_adjusments');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['min_stock']);
        });
    }
};
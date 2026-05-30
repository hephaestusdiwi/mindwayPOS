<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('sku');
            $table->string('image')->nullable()->after('description');
            $table->decimal('discount_price', 12, 2)->nullable()->after('price');
            $table->boolean('is_active')->default(true)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sku', 'description', 'image', 'discount_price', 'is_active']);
        });
    }
};
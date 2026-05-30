<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void 
    {
        Schema::table('users', function (Blueprint $table) {
            // Role : admin = Superuser, manager = report & produk, cashier = only kasir
            $table->enum('role', ['admin', 'manager', 'cashier'])->default('cashier')->after('name');

            $table->string('phone', 20)->nullable()->after('email');

            $table->string('avatar')->nullable()->after('phone');

            $table->boolean('is_active')->default(true)->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'avatar', 'is_active']);
        });
    }
};
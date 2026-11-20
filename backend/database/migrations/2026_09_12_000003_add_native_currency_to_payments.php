<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('UGX');
        });
        DB::table('payments')->update(['amount' => DB::raw('amount_ugx')]);

        Schema::table('fundraising_donations', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('UGX');
        });
        DB::table('fundraising_donations')->update(['amount' => DB::raw('amount_ugx')]);
    }

    public function down(): void
    {
        Schema::table('fundraising_donations', function (Blueprint $table) {
            $table->dropColumn(['amount', 'currency']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['amount', 'currency']);
        });
    }
};

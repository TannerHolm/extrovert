<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('discount_code')->nullable()->index()->after('notes');
            $table->string('shopify_price_rule_id')->nullable()->after('discount_code');
            $table->string('shopify_discount_code_id')->nullable()->after('shopify_price_rule_id');
            $table->string('ref_token')->nullable()->unique()->after('shopify_discount_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['discount_code', 'shopify_price_rule_id', 'shopify_discount_code_id', 'ref_token']);
        });
    }
};

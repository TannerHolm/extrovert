<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('sending_from_email')->nullable()->after('is_personal');
            $table->string('sending_from_name')->nullable()->after('sending_from_email');
            $table->timestamp('sending_domain_verified_at')->nullable()->after('sending_from_name');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['sending_from_email', 'sending_from_name', 'sending_domain_verified_at']);
        });
    }
};

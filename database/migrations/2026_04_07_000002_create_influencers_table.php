<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencers', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('platform_id');
            $table->string('handle');
            $table->string('profile_url');
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->unsignedBigInteger('follower_count')->nullable();
            $table->decimal('engagement_rate', 5, 2)->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamp('latest_activity_at')->nullable();
            $table->json('platform_data')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'platform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencers');
    }
};

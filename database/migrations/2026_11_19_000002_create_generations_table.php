<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pixelforge_id')->nullable()->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('status', 32)->index();

            $table->text('prompt');
            $table->string('size', 16);
            $table->string('quality', 16);
            $table->json('reference_images');
            $table->json('rates_snapshot');
            $table->json('usage')->nullable();

            $table->bigInteger('estimated_min_provider_cost_picodollars');
            $table->bigInteger('estimated_expected_provider_cost_picodollars');
            $table->bigInteger('estimated_max_provider_cost_picodollars');
            $table->bigInteger('estimated_min_user_cost_picodollars');
            $table->bigInteger('estimated_expected_user_cost_picodollars');
            $table->bigInteger('estimated_max_user_cost_picodollars');
            $table->bigInteger('reserved_user_cost_picodollars')->default(0);

            $table->bigInteger('actual_provider_cost_picodollars')->nullable();
            $table->bigInteger('actual_user_cost_picodollars')->nullable();
            $table->bigInteger('profit_picodollars')->nullable();
            $table->timestampTz('settled_at')->nullable();

            $table->text('image_url')->nullable();
            $table->timestampTz('image_url_expires_at')->nullable();

            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);

            $table->timestampTz('pixelforge_created_at')->nullable();
            $table->timestampTz('pixelforge_started_at')->nullable();
            $table->timestampTz('pixelforge_completed_at')->nullable()->index();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'pixelforge_completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};

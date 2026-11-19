<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixelforge_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id', 128)->unique();
            $table->string('event_type', 64)->index();
            $table->string('pixelforge_generation_id', 128)->nullable()->index();
            $table->unsignedInteger('delivery_attempt')->default(1);
            $table->json('payload');
            $table->timestampTz('processed_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pixelforge_webhook_events');
    }
};

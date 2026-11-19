<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('generation_id')->nullable()->constrained('generations')->nullOnDelete();
            $table->string('type', 32)->index();
            $table->bigInteger('amount_picodollars');
            $table->bigInteger('balance_after_picodollars');
            $table->bigInteger('reserved_after_picodollars');
            $table->string('description');
            $table->timestampTz('created_at');

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};

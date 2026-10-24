<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('source_filename');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->json('raw');
            $table->json('ai')->nullable();
            $table->string('status')->default('pending');
            $table->json('errors')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('rejected_by_validator')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('woo_id')->nullable();
            $table->timestamps();

            $table->index(['feed_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('feeds');
    }
};

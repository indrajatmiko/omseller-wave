<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sku_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_id')->constrained()->index();
            $table->unsignedBigInteger('item_id')->index(); // Tambahkan baris ini
            $table->string('variation_name');
            $table->string('variation_option_name');
            $table->string('image_url')->nullable();
            $table->string('model_sku')->index();
            $table->string('currency');
            $table->decimal('current_price', 12, 2);
            $table->decimal('original_price', 12, 2);
            $table->decimal('harga_modal', 12, 2)->nullable();
            $table->timestamps();
            $table->index(['sku_id', 'model_sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sku_models');
    }
};

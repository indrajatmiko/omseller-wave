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
        Schema::create('skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->string('item_status');
            $table->unsignedBigInteger('category_id');
            $table->string('item_name');
            $table->string('item_sku')->nullable()->index();
            $table->string('currency');
            $table->decimal('original_price', 12, 2);
            $table->decimal('current_price', 12, 2);
            $table->string('image_url')->nullable();
            $table->string('condition');
            $table->string('original_brand_name');
            $table->boolean('has_model');
            $table->decimal('harga_modal', 12, 2)->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skus');
    }
};

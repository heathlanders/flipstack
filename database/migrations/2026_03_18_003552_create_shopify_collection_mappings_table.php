<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_collection_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_credential_id')->constrained('integration_credentials')->cascadeOnDelete();
            $table->uuid('category_id')->nullable();
            $table->bigInteger('shopify_collection_id');
            $table->string('shopify_collection_title');
            $table->string('collection_type')->default('custom');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->unique(['integration_credential_id', 'category_id', 'shopify_collection_id'], 'shopify_coll_map_cred_cat_coll_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_collection_mappings');
    }
};

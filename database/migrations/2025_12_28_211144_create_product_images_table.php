<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('product_id');

            $table->string('url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);

            $table->jsonb('metadata')->default('{}'); // Store dimensions, file size, etc.

            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['product_id', 'sort_order']);
            $table->index(['product_id', 'is_primary']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_images');
    }
};

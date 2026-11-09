<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // framework.pub/go/{code}: a short address for an artifact, report, or
        // collection, easy to type off a slide. Each has one made code and may
        // have one chosen word.
        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->morphs('linkable');
            $table->boolean('custom')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};

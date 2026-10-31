<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The latest .framework/spec.json a project sent with fw. Structure and
    // template text only: specs hold no data and no secrets.
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->jsonb('spec')->nullable();
            $table->timestamp('spec_received_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['spec', 'spec_received_at']);
        });
    }
};

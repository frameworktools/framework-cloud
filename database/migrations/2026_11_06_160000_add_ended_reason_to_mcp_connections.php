<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// How a connection ended: disconnected here, disconnected in the app, expired, or replaced
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcp_connections', function (Blueprint $table) {
            $table->string('ended_reason', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mcp_connections', fn (Blueprint $table) => $table->dropColumn('ended_reason'));
    }
};

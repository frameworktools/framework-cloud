<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Web AI apps a person has approved once (by name and redirect host), so later connections skip the consent screen
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_approved_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('client_name');
            $table->string('redirect_host');
            $table->string('access', 16);
            $table->timestamps();

            $table->unique(['user_id', 'client_name', 'redirect_host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_approved_apps');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('oauth_client_id');
            $table->string('client_name');
            $table->string('redirect_host')->nullable();
            $table->string('access', 16);
            $table->foreignId('personal_access_token_id')->nullable()->constrained()->nullOnDelete();
            $table->text('api_key');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'oauth_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_connections');
    }
};

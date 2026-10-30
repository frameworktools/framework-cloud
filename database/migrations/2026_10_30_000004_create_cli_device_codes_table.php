<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // fw login's device flow. Expired rows are pruned before each new
        // code is issued, so the unique user_code only spans live codes.
        Schema::create('cli_device_codes', function (Blueprint $table) {
            $table->id();
            $table->string('device_code_hash', 64)->unique();
            $table->string('user_code', 8)->unique();
            $table->string('client_name', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cli_device_codes');
    }
};

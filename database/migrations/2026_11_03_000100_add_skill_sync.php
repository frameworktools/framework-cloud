<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->string('sync_id', 100)->nullable()->unique();
            $table->softDeletes();
        });
        foreach (DB::table('skills')->get(['id', 'user_id', 'key']) as $skill) {
            DB::table('skills')->where('id', $skill->id)->update(['sync_id' => $skill->user_id === null ? 'framework:'.$skill->key : (string) Str::uuid()]);
        }
        Schema::table('skill_versions', function (Blueprint $table) {
            $table->json('bundle_files')->nullable();
            $table->unsignedBigInteger('base_version_id')->nullable();
            $table->json('client')->nullable();
        });
        Schema::create('skill_sync_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->string('fingerprint', 64);
            $table->unsignedBigInteger('version_id');
            $table->string('variant', 16);
            $table->unique(['user_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_sync_receipts');
        Schema::table('skill_versions', fn (Blueprint $t) => $t->dropColumn(['bundle_files', 'base_version_id', 'client']));
        Schema::table('skills', fn (Blueprint $t) => $t->dropColumn(['sync_id', 'deleted_at']));
    }
};

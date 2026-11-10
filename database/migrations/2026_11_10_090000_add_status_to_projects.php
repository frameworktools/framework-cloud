<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a project stands: active, triaged (waiting on a decision),
        // completed, or inactive. Replaces the active flag.
        Schema::table('projects', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->after('description');
        });
        DB::table('projects')->where('active', false)->update(['status' => 'inactive']);
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('description');
        });
        DB::table('projects')->where('status', '!=', 'active')->update(['active' => false]);
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

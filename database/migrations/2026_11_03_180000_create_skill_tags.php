<?php

use App\Support\SkillTags;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_tags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name', 60);
            $table->unsignedInteger('position')->default(0);
        });
        Schema::create('skill_skill_tag', function (Blueprint $table) {
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['skill_id', 'skill_tag_id']);
        });
        SkillTags::seed();
        foreach (DB::table('skills')->orderBy('id')->get() as $skill) {
            $ids = $skill->user_id === null
                ? DB::table('skill_tags')->whereIn('key', SkillTags::SKILLS[$skill->key] ?? [])->pluck('id')
                : collect();
            if ($skill->forked_from_id) {
                $parent = DB::table('skill_versions')->where('id', $skill->forked_from_id)->value('skill_id');
                $ids = DB::table('skill_skill_tag')->where('skill_id', $parent)->pluck('skill_tag_id');
            }
            foreach ($ids as $id) {
                DB::table('skill_skill_tag')->insert(['skill_id' => $skill->id, 'skill_tag_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_skill_tag');
        Schema::dropIfExists('skill_tags');
    }
};

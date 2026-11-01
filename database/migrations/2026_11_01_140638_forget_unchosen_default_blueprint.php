<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Accounts used to be seeded with defaults.project_type = bare. A document
 * at revision 0 has never been saved, from the web or a computer, so its
 * Bare is the old seed, not a choice: drop it, and the account gets the
 * standard project like a new one. Saved documents keep whatever they hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('kind', 'global')->where('revision', 0)->orderBy('id')
            ->each(function (object $row) {
                $document = json_decode($row->document, true);
                if (($document['defaults']['project_type'] ?? null) === 'bare') {
                    unset($document['defaults']['project_type']);
                    DB::table('settings')->where('id', $row->id)->update(['document' => json_encode($document)]);
                }
            });
    }

    public function down(): void
    {
        // The removed values were the old seed, which DefaultSettings no longer writes
    }
};

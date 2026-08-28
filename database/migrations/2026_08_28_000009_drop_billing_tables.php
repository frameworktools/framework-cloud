<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing is not part of this application. The open core stays free;
     * money, if it ever exists, lives in the separate Framework Cloud
     * package (table1/framework-cloud), which brings its own tables.
     * This drops the Cashier scaffolding that briefly existed.
     */
    public function up(): void
    {
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');

        if (Schema::hasColumn('users', 'stripe_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
            });
        }
    }

    public function down(): void
    {
        // Billing scaffolding is not recreated by this application.
    }
};

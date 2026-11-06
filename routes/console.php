<?php

use Illuminate\Support\Facades\Schedule;

// Empty the Trash of what has been there longer than FW_TRASH_DAYS
Schedule::command('fw:purge-trash')->dailyAt('03:30')->withoutOverlapping()->onOneServer();

// Expired and revoked OAuth tokens and codes from connected agents
Schedule::command('passport:purge')->dailyAt('03:45');

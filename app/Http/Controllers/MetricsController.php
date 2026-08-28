<?php

namespace App\Http\Controllers;

use App\Models\Blueprint;
use App\Models\Document;
use App\Models\LedgerEntry;
use App\Models\Project;
use App\Models\SettingRevision;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Prometheus text-format metrics, hand-rolled (no exporter dependency).
 * Guarded by VerifyMetricsToken; scrape over HTTPS with a bearer token.
 */
class MetricsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            '# HELP framework_app_up Application responded to the metrics scrape.',
            '# TYPE framework_app_up gauge',
            'framework_app_up 1',

            '# HELP framework_queue_jobs_pending Jobs waiting on the default queue.',
            '# TYPE framework_queue_jobs_pending gauge',
            'framework_queue_jobs_pending '.Queue::size(),

            '# HELP framework_queue_jobs_failed_total Rows in failed_jobs.',
            '# TYPE framework_queue_jobs_failed_total gauge',
            'framework_queue_jobs_failed_total '.DB::table('failed_jobs')->count(),

            '# HELP framework_users_total Registered accounts.',
            '# TYPE framework_users_total gauge',
            'framework_users_total '.User::count(),

            '# HELP framework_projects_total Cloud project definitions (live).',
            '# TYPE framework_projects_total gauge',
            'framework_projects_total '.Project::count(),

            '# HELP framework_documents_total Published documents.',
            '# TYPE framework_documents_total gauge',
            'framework_documents_total '.Document::count(),

            '# HELP framework_documents_bytes_total Stored published bytes.',
            '# TYPE framework_documents_bytes_total gauge',
            'framework_documents_bytes_total '.(int) Document::sum('size_bytes'),

            '# HELP framework_document_views_total Wrapper-page views across documents.',
            '# TYPE framework_document_views_total gauge',
            'framework_document_views_total '.(int) Document::sum('views'),

            '# HELP framework_ledger_entries_total Integrity ledger entries.',
            '# TYPE framework_ledger_entries_total gauge',
            'framework_ledger_entries_total '.LedgerEntry::count(),

            '# HELP framework_setting_revisions_total Settings revisions recorded.',
            '# TYPE framework_setting_revisions_total gauge',
            'framework_setting_revisions_total '.SettingRevision::count(),

            '# HELP framework_blueprints_total Blueprint definitions.',
            '# TYPE framework_blueprints_total gauge',
            'framework_blueprints_total '.Blueprint::count(),
        ];

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}

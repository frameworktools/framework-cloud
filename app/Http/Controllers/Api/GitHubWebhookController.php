<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GitHubAccount;
use App\Models\Project;
use App\Services\GitHub\Scaffolder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Events from the Framework GitHub App: scaffold runs finishing, and users
 * revoking the app. Every delivery is signed with the webhook secret.
 */
class GitHubWebhookController extends Controller
{
    public function __invoke(Request $request, Scaffolder $scaffolder): Response
    {
        $secret = (string) config('services.github.webhook_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');

        abort_if($secret === '' || ! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature), 401);

        $event = $request->header('X-GitHub-Event');
        $payload = $request->json()->all();

        if ($event === 'workflow_run' && ($payload['action'] ?? null) === 'completed'
            && ($payload['workflow_run']['path'] ?? null) === Scaffolder::WORKFLOW_PATH) {
            $project = Project::with('user.githubAccount')
                ->where('github_repo_id', $payload['repository']['id'] ?? 0)
                ->first();

            if ($project) {
                $scaffolder->finish($project, (string) $payload['workflow_run']['conclusion'], $payload['workflow_run']['html_url'] ?? null);
            }
        }

        // The user revoked the app: their tokens no longer work
        if ($event === 'github_app_authorization' && ($payload['action'] ?? null) === 'revoked') {
            GitHubAccount::where('github_id', $payload['sender']['id'] ?? 0)->delete();
        }

        return response()->noContent();
    }
}

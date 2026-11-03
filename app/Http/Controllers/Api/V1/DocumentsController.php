<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\ArtifactPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentsController extends Controller
{
    /**
     * Publish a rendered document to this project. Republishing the same
     * slug adds a new version under the same links; earlier versions stay
     * available until their owner deletes them.
     *
     * `slug` names the document within the project. `readable` turns on the
     * readable URL, /@{handle}/{project}/{slug}. Leaving it out keeps the
     * document's current setting (new artifacts start unlisted).
     */
    public function store(Request $request): JsonResponse
    {
        $project = $request->user();
        abort_unless($project instanceof Project, 403, 'Publishing requires a project token.');
        abort_unless($request->user()->tokenCan(Project::PULL_ABILITY), 403, 'Token lacks publish rights.');
        abort_unless(
            $project->user->canPublish(), 402,
            'Publishing to '.config('app.name').' is part of a paid plan. Ask about it in your account, or publish to your own bucket with a connection.',
        );

        $version = app(ArtifactPublisher::class)->publish($project, $request->validate(ArtifactPublisher::rules()));
        $document = $version->document;

        $document->load('project.user');
        $readableUrl = $document->readableUrl();

        return response()->json([
            'id' => $document->id,
            'kind' => $document->kind,
            'password_protected' => $document->isPasswordProtected(),
            'slug' => $document->slug,
            'version' => $version->number,
            // The link to share: readable when available, else unlisted.
            'url' => $readableUrl ?? $document->publicUrl(),
            'private_url' => $document->publicUrl(),
            'readable_url' => $readableUrl,
            'version_url' => $readableUrl ? $document->readableUrl($version->number) : $version->url(),
            'raw_url' => $document->rawUrl(),
            'notice' => $document->isReadable() && ! $readableUrl
                ? 'Set a handle in Account > Account details to get a readable URL. The unlisted link works now.'
                : null,
        ], 201);
    }
}

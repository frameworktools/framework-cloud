<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Support\Dags\ArtifactDag;
use App\Support\Dags\DagittyParser;
use App\Support\Snippets\ArtifactSnippet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ArtifactPublisher
{
    public const KINDS = ['html', 'image', 'chart', 'table', 'data', 'model', 'test', 'text', 'pdf', 'file', ArtifactSnippet::KIND, ArtifactDag::KIND];

    public const VERSION_FIELDS = ['disk_path', 'entrypoint', 'content_type', 'size_bytes', 'kind', 'original_filename', 'manifest', 'storage_disk', 'source_path'];

    public static function rules(): array
    {
        return [
            'file' => ['required_without:bundle', 'prohibits:bundle', 'file', 'max:51200'],
            'bundle' => ['required_without:file', 'prohibits:file', 'file', 'max:51200'],
            'entrypoint' => ['required_with:bundle', 'string', 'max:255', 'not_regex:/\.\.|^\/|\\\\/'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:120'],
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'readable' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', Rule::in(['public', 'unlisted', 'private'])],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:200'],
            'kind' => ['sometimes', Rule::in(self::KINDS)],
            // A phiz plot description of an SVG or PNG figure, stored beside it as figure.phiz.json
            'plot_description' => ['sometimes', 'nullable', 'prohibits:bundle', 'file', 'max:'.intdiv(PlotDescription::MAX_BYTES, 1024)],
            // What its embeds show, as a JSON object of setting names to true or false (ArtifactDisplay)
            'display' => ['sometimes', 'nullable'],
        ];
    }

    public function publish(Project $project, array $input): DocumentVersion
    {
        // Checked first, so a bad setting stores nothing
        $display = ArtifactDisplay::parse($input['display'] ?? null);
        $bundle = isset($input['bundle']);
        $upload = $bundle ? $input['bundle'] : $input['file'];
        $filename = basename(str_replace('\\', '/', $upload->getClientOriginalName()));
        $slug = Str::slug($input['slug'] ?? pathinfo($bundle ? $input['entrypoint'] : $filename, PATHINFO_FILENAME)) ?: 'artifact';
        // All new content is private and served through authorization-aware routes.
        $disk = config('framework.artifacts_disk', 'local');
        $prefix = 'artifacts/'.$project->id.'/'.Str::uuid();
        try {
            if ($bundle) {
                $stats = BundleExtractor::extract($upload, $disk, $prefix);
                if (! Storage::disk($disk)->exists($prefix.'/'.$input['entrypoint'])) {
                    throw ValidationException::withMessages(['entrypoint' => 'The bundle does not contain the declared entrypoint.']);
                }
                $type = BundleExtractor::contentType($input['entrypoint']);
                if (! str_starts_with($type, 'text/html')) {
                    throw ValidationException::withMessages(['entrypoint' => 'Choose an HTML entrypoint for the rendered bundle.']);
                }
                if (! Storage::disk($disk)->putFileAs($prefix, $upload, '.source.zip')) {
                    throw new RuntimeException('Could not retain the original bundle.');
                }
                $stored = ['source_path' => $prefix.'/.source.zip', 'disk_path' => $prefix, 'entrypoint' => $input['entrypoint'], 'size_bytes' => $stats['bytes'], 'manifest' => $stats['manifest']];
            } else {
                $type = $this->contentType($upload->getMimeType(), $filename);
                $path = $prefix.'/content';
                if (! Storage::disk($disk)->putFileAs($prefix, $upload, 'content')) {
                    throw new RuntimeException('Could not store the artifact.');
                }
                $stored = ['source_path' => null, 'disk_path' => $path, 'entrypoint' => null, 'size_bytes' => $upload->getSize(), 'manifest' => [['path' => $filename, 'size_bytes' => $upload->getSize(), 'content_type' => $type]]];
                if (isset($input['plot_description'])) {
                    if (! in_array(strtolower(trim(explode(';', $type)[0])), PlotDescription::FIGURE_TYPES, true)) {
                        throw ValidationException::withMessages(['plot_description' => 'A plot description accompanies an SVG or PNG figure.']);
                    }
                    // Kept as sent: phiz checks it when it compiles, and a refused one leaves the figure an image
                    if (! Storage::disk($disk)->putFileAs($prefix, $input['plot_description'], PlotDescription::FILENAME)) {
                        throw new RuntimeException('Could not store the plot description.');
                    }
                }
                // A CSV or TSV also gets a typed table with column summaries; without one it still publishes
                if (DataFile::isTabular($filename, $type)) {
                    $representation = DataFile::representation($upload->getRealPath(), DataFile::delimiter($filename, $type), $filename);
                    if ($representation !== null) {
                        Storage::disk($disk)->put($prefix.'/'.DocumentVersion::REPRESENTATION, $representation);
                    }
                }
            }
            $representationKind = null;
            $snippet = null;
            $json = ! $bundle && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'json';
            // A code snippet is checked strictly: a broken one fails here, not as a broken page or embed
            if (($input['kind'] ?? null) === ArtifactSnippet::KIND && ! $json) {
                throw ValidationException::withMessages(['file' => 'A code snippet is published as a JSON file.']);
            }
            // A DAG is checked strictly too: DAGitty text, or JSON that carries it
            $dagFile = ! $bundle && ($input['kind'] ?? null) === ArtifactDag::KIND && ! $json;
            if (! $bundle && (ArtifactDag::isDagittyFile($filename) || $dagFile)) {
                if ($upload->getSize() > DagittyParser::MAX_BYTES) {
                    throw ValidationException::withMessages(['file' => 'DAGitty code can be at most 64 KB.']);
                }
                try {
                    ArtifactDag::fromDagitty((string) file_get_contents($upload->getRealPath()));
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['file' => $e->getMessage()]);
                }
                $representationKind = ArtifactDag::KIND;
            } elseif ($json && $upload->getSize() <= ArtifactRepresentation::MAX_BYTES) {
                $content = (string) file_get_contents($upload->getRealPath());
                if (($input['kind'] ?? null) === ArtifactDag::KIND || ArtifactDag::claims($content)) {
                    try {
                        ArtifactDag::fromJson($content);
                    } catch (InvalidArgumentException $e) {
                        throw ValidationException::withMessages(['file' => $e->getMessage()]);
                    }
                    $representationKind = ArtifactDag::KIND;
                } elseif (($input['kind'] ?? null) === ArtifactSnippet::KIND || ArtifactSnippet::claims($content)) {
                    try {
                        $snippet = ArtifactSnippet::fromJson($content);
                    } catch (InvalidArgumentException $e) {
                        throw ValidationException::withMessages(['file' => $e->getMessage()]);
                    }
                    $representationKind = ArtifactSnippet::KIND;
                } else {
                    $representationKind = ArtifactRepresentation::parse($content)['kind'] ?? null;
                }
            } elseif (($input['kind'] ?? null) === ArtifactSnippet::KIND) {
                throw ValidationException::withMessages(['file' => 'A code snippet can be at most 2 MB.']);
            } elseif (($input['kind'] ?? null) === ArtifactDag::KIND) {
                throw ValidationException::withMessages(['file' => 'A DAG can be at most 1 MB.']);
            }
            if (! $bundle && ArtifactPreview::isVegaLite($filename)) {
                $representationKind = 'chart';
            }
            // A client that can't tell a DAG from any other file sends file or text; a valid DAG is a dag all the same
            $kind = $representationKind === ArtifactDag::KIND && in_array($input['kind'] ?? null, [null, 'file', 'text'], true) ? ArtifactDag::KIND : ($input['kind'] ?? null);
            $stored += ['content_type' => $type, 'original_filename' => $filename, 'storage_disk' => $disk, 'kind' => $kind ?? $representationKind ?? $this->kind($type, $filename)];

            $unchanged = false;
            $version = DB::transaction(function () use ($project, $slug, $stored, $input, $snippet, $display, &$unchanged) {
                PublishingQuota::check($project, (int) $stored['size_bytes']);
                Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
                // Publishing again under a deleted artifact's name brings it back from the Trash
                $document = Document::withTrashed()->firstOrNew(['project_id' => $project->id, 'slug' => $slug]);
                if ($document->trashed()) {
                    $document->restore();
                }
                if (($document->exists && ($document->driver ?? 'cloud') !== 'cloud') || $document->merged_into_id || $document->merged_at) {
                    throw ValidationException::withMessages(['slug' => 'This artifact has been merged. Publish to the destination artifact instead.']);
                }
                if ($document->exists) {
                    $document->preserveImplicitVersion();
                }
                // Every render of a notebook publishes its snippets again; an unchanged one adds no version
                if ($snippet !== null && $document->exists && self::sameSnippet($document->latestVersion(), $snippet)) {
                    $unchanged = true;
                    $stored = [];
                }
                $number = max((int) $document->next_version_number, (int) $document->versions()->withTrashed()->max('number') + 1, 1);
                $document->fill($stored + ['key' => $document->key ?? Document::newKey(), 'title' => $input['title'] ?? $document->title ?? self::titleFromSlug($slug)]);
                if (array_key_exists('description', $input)) {
                    $document->description = $input['description'];
                }
                if ($display !== null) {
                    $document->display = ArtifactDisplay::merge($document->display, $display);
                }
                $document->visibility = Document::visibilityFor($document->visibility, $input);
                // Existing external publications must first be migrated through settings.
                if (array_key_exists('password', $input)) {
                    if (filled($input['password']) && $document->exists && $document->hasUnprotectedLegacyContent()) {
                        throw ValidationException::withMessages(['password' => 'This artifact has legacy versions outside managed private storage. Remove those versions before adding a password.']);
                    }
                    $document->password_hash = filled($input['password']) ? Hash::make($input['password']) : null;
                }
                if ($unchanged) {
                    $document->save();
                    $latest = $document->latestVersion()->load('document.project.user');
                    $latest->unchanged = true;

                    return $latest;
                }
                $document->next_version_number = $number + 1;
                $document->save();

                // Who published, when it was not the project's own package (an agent over MCP)
                $provenance = isset($input['provenance']) && is_array($input['provenance']) ? ['provenance' => $input['provenance']] : [];

                return $document->versions()->create($stored + ['number' => $number] + $provenance)->load('document.project.user');
            });

            if ($unchanged) {
                Storage::disk($disk)->deleteDirectory($prefix);

                return $version;
            }
            // The page's preview and chart are built now, once, not when someone first opens it
            try {
                ArtifactPreview::forVersion($version);
                PlotDescription::spec($version);
                ChartPage::store($version);
            } catch (Throwable $e) {
                report($e);
            }

            return $version;
        } catch (Throwable $exception) {
            Storage::disk($disk)->deleteDirectory($prefix);
            if ($exception instanceof RuntimeException && ! $exception instanceof ValidationException) {
                throw ValidationException::withMessages(['file' => $exception->getMessage()]);
            }
            throw $exception;
        }
    }

    public function merge(Document $target, Document $source): Document
    {
        if ($source->id === $target->id || $source->project_id !== $target->project_id) {
            throw ValidationException::withMessages(['source_id' => 'Choose a different artifact in the same project.']);
        }
        $copies = [];
        try {
            return DB::transaction(function () use ($target, $source, &$copies) {
                Project::whereKey($target->project_id)->lockForUpdate()->firstOrFail();
                $target->refresh();
                $source->refresh();
                if ($target->merged_into_id || $target->merged_at || $source->merged_into_id || $source->merged_at) {
                    throw ValidationException::withMessages(['source_id' => 'Choose artifacts that have not already been merged.']);
                }
                // A merge cannot expose a password-protected source through a weaker target.
                if ($source->isPasswordProtected()) {
                    throw ValidationException::withMessages(['source_id' => 'Remove the source password before merging its versions into another artifact.']);
                }
                $target->preserveImplicitVersion();
                $source->preserveImplicitVersion();
                $number = max((int) $target->next_version_number, (int) $target->versions()->withTrashed()->max('number') + 1);
                foreach ($source->versions()->reorder()->orderBy('number')->get() as $version) {
                    $prefix = 'artifacts/'.$target->project_id.'/'.Str::uuid();
                    $copies[] = $prefix;
                    $stored = $version->only(self::VERSION_FIELDS);
                    $stored['disk_path'] = $version->isBundle() ? $prefix : $prefix.'/content';
                    $stored['source_path'] = $version->source_path ? $prefix.'/.source.zip' : null;
                    $stored['storage_disk'] = config('framework.artifacts_disk', 'local');
                    $files = $version->isBundle() ? Storage::disk($version->disk())->allFiles($version->disk_path) : [$version->disk_path];
                    $sidecar = $version->representationPath();
                    $description = PlotDescription::path($version);
                    foreach ([$sidecar, $description] as $extra) {
                        if ($extra !== null && Storage::disk($version->disk())->exists($extra)) {
                            $files[] = $extra;
                        }
                    }
                    if ($files === []) {
                        throw new RuntimeException('The source artifact has no stored files.');
                    }
                    foreach ($files as $file) {
                        $destination = match (true) {
                            $version->isBundle() => $prefix.substr($file, strlen($version->disk_path)),
                            $file === $sidecar => $prefix.'/'.DocumentVersion::REPRESENTATION,
                            $file === $description => $prefix.'/'.PlotDescription::FILENAME,
                            default => $stored['disk_path'],
                        };
                        $stream = Storage::disk($version->disk())->readStream($file);
                        if (! is_resource($stream)) {
                            throw new RuntimeException('Could not read a source artifact file.');
                        }
                        try {
                            if (! Storage::disk(config('framework.artifacts_disk', 'local'))->put($destination, $stream)) {
                                throw new RuntimeException('Could not copy the source artifact.');
                            }
                        } finally {
                            fclose($stream);
                        }
                    }
                    $target->versions()->create($stored + ['number' => $number++, 'provenance' => ['source_document_id' => $source->id, 'source_key' => $source->key, 'source_version' => $version->number, 'merged_at' => now()->toIso8601String()]]);
                    $target->fill($stored);
                }
                $target->next_version_number = $number;
                $target->save();
                $source->update(['merged_into_id' => $target->id, 'merged_at' => now()]);

                return $target;
            });
        } catch (Throwable $exception) {
            foreach ($copies as $prefix) {
                Storage::disk(config('framework.artifacts_disk', 'local'))->deleteDirectory($prefix);
            }
            throw $exception;
        }
    }

    /** Whether a version holds this snippet already, as Cloud normalizes it. */
    private static function sameSnippet(DocumentVersion $version, array $snippet): bool
    {
        if ($version->kind !== ArtifactSnippet::KIND || $version->isBundle() || ! $version->exists) {
            return false;
        }
        try {
            $disk = Storage::disk($version->disk());
            if (! $disk->exists($version->disk_path) || $disk->size($version->disk_path) > ArtifactSnippet::MAX_BYTES) {
                return false;
            }

            return ArtifactSnippet::fromJson((string) $disk->get($version->disk_path)) === $snippet;
        } catch (Throwable) {
            return false;
        }
    }

    private function contentType(?string $detected, string $filename): string
    {
        $extensionType = BundleExtractor::contentType($filename);
        // Keep HTML/text previews useful when finfo identifies a small render as plain text.
        if (str_starts_with($extensionType, 'text/') && in_array($detected, ['text/plain', 'text/html', 'application/octet-stream'])) {
            return $extensionType;
        }

        return $detected ?: 'application/octet-stream';
    }

    private const DATA_EXTENSIONS = ['xlsx', 'xls', 'parquet', 'feather', 'arrow', 'rds', 'rda', 'rdata', 'dta', 'sav', 'zsav', 'por', 'sas7bdat'];

    private function kind(string $type, string $filename = ''): string
    {
        return match (true) {
            in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::DATA_EXTENSIONS, true) => 'data',
            str_starts_with($type, 'text/html'), $type === 'application/xhtml+xml' => 'html',
            str_starts_with($type, 'image/') => 'image',
            $type === 'application/pdf' => 'pdf',
            str_starts_with($type, 'text/csv'), str_starts_with($type, 'text/tab-separated') => 'table',
            str_starts_with($type, 'text/'), $type === 'application/json' => 'text',
            default => 'file',
        };
    }

    /**
     * A readable title from an artifact name, in sentence case: "mass-by-species"
     * becomes "Mass by species". Letters the person capitalized stay as they are.
     */
    public static function titleFromSlug(string $slug): string
    {
        $words = trim(preg_replace('/[\s_-]+/', ' ', $slug) ?? $slug);

        return Str::ucfirst($words);
    }
}

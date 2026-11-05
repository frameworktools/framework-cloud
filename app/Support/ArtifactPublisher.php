<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ArtifactPublisher
{
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
            'kind' => ['sometimes', Rule::in(['html', 'image', 'chart', 'table', 'data', 'model', 'test', 'text', 'pdf', 'file'])],
        ];
    }

    public function publish(Project $project, array $input): DocumentVersion
    {
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
                // A CSV or TSV also gets a typed table with column summaries; without one it still publishes
                if (DataFile::isTabular($filename, $type)) {
                    $representation = DataFile::representation($upload->getRealPath(), DataFile::delimiter($filename, $type), $filename);
                    if ($representation !== null) {
                        Storage::disk($disk)->put($prefix.'/'.DocumentVersion::REPRESENTATION, $representation);
                    }
                }
            }
            $representationKind = null;
            if (! $bundle && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'json' && $upload->getSize() <= ArtifactRepresentation::MAX_BYTES) {
                $representationKind = ArtifactRepresentation::parse(file_get_contents($upload->getRealPath()))['kind'] ?? null;
            }
            if (! $bundle && ArtifactPreview::isVegaLite($filename)) {
                $representationKind = 'chart';
            }
            $stored += ['content_type' => $type, 'original_filename' => $filename, 'storage_disk' => $disk, 'kind' => $input['kind'] ?? $representationKind ?? $this->kind($type, $filename)];

            return DB::transaction(function () use ($project, $slug, $stored, $input) {
                PublishingQuota::check($project, (int) $stored['size_bytes']);
                Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
                $document = Document::firstOrNew(['project_id' => $project->id, 'slug' => $slug]);
                if (($document->exists && ($document->driver ?? 'cloud') !== 'cloud') || $document->merged_into_id || $document->merged_at) {
                    throw ValidationException::withMessages(['slug' => 'This artifact has been merged. Publish to the destination artifact instead.']);
                }
                if ($document->exists) {
                    $document->preserveImplicitVersion();
                }
                $number = max((int) $document->next_version_number, (int) $document->versions()->max('number') + 1, 1);
                $document->fill($stored + ['key' => $document->key ?? Document::newKey(), 'title' => $input['title'] ?? $document->title ?? Str::headline($slug)]);
                if (array_key_exists('description', $input)) {
                    $document->description = $input['description'];
                }
                $document->visibility = Document::visibilityFor($document->visibility, $input);
                // Existing external publications must first be migrated through settings.
                if (array_key_exists('password', $input)) {
                    if (filled($input['password']) && $document->exists && $document->hasUnprotectedLegacyContent()) {
                        throw ValidationException::withMessages(['password' => 'This artifact has legacy versions outside managed private storage. Remove those versions before adding a password.']);
                    }
                    $document->password_hash = filled($input['password']) ? Hash::make($input['password']) : null;
                }
                $document->next_version_number = $number + 1;
                $document->save();

                return $document->versions()->create($stored + ['number' => $number])->load('document.project.user');
            });
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
                $number = max((int) $target->next_version_number, (int) $target->versions()->max('number') + 1);
                foreach ($source->versions()->reorder()->orderBy('number')->get() as $version) {
                    $prefix = 'artifacts/'.$target->project_id.'/'.Str::uuid();
                    $copies[] = $prefix;
                    $stored = $version->only(self::VERSION_FIELDS);
                    $stored['disk_path'] = $version->isBundle() ? $prefix : $prefix.'/content';
                    $stored['source_path'] = $version->source_path ? $prefix.'/.source.zip' : null;
                    $stored['storage_disk'] = config('framework.artifacts_disk', 'local');
                    $files = $version->isBundle() ? Storage::disk($version->disk())->allFiles($version->disk_path) : [$version->disk_path];
                    $sidecar = $version->representationPath();
                    if ($sidecar !== null && Storage::disk($version->disk())->exists($sidecar)) {
                        $files[] = $sidecar;
                    }
                    if ($files === []) {
                        throw new RuntimeException('The source artifact has no stored files.');
                    }
                    foreach ($files as $file) {
                        $destination = match (true) {
                            $version->isBundle() => $prefix.substr($file, strlen($version->disk_path)),
                            $file === $sidecar => $prefix.'/'.DocumentVersion::REPRESENTATION,
                            default => $stored['disk_path'],
                        };
                        $stream = Storage::disk($version->disk())->readStream($file);
                        if ($stream === false) {
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
}

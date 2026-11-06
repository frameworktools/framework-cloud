<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Moves stored artifact versions to another disk, such as from the server's
 * disk to R2. Each version's files are copied and their sizes checked before
 * the version points at the new disk, so a failure leaves it where it was.
 * The source files stay unless --delete-source is given.
 */
class MoveArtifactsCommand extends Command
{
    protected $signature = 'fw:move-artifacts
        {--to= : The disk to move to (default: FW_ARTIFACTS_DISK)}
        {--delete-source : Delete each version\'s old files after it moves}
        {--dry-run : List what would move}';

    protected $description = 'Move stored artifact files to another disk, such as R2';

    public function handle(): int
    {
        $to = (string) ($this->option('to') ?: config('framework.artifacts_disk'));
        if (! array_key_exists($to, config('filesystems.disks'))) {
            $this->error("No disk named {$to}.");

            return self::FAILURE;
        }

        $versions = DocumentVersion::query()->with('document')
            ->where(fn ($q) => $q->whereNull('driver')->orWhere('driver', 'cloud'))
            ->whereNotNull('disk_path')->get()
            ->filter(fn (DocumentVersion $v) => $v->disk() !== $to);

        $this->info($versions->count()." version(s) to move to {$to}.");
        $moved = 0;

        foreach ($versions as $version) {
            $from = $version->disk();
            $label = ($version->document?->slug ?? 'document '.$version->document_id).' v'.$version->number;
            $files = $this->files($version);

            if ($this->option('dry-run')) {
                $this->line("  {$label}: ".count($files)." file(s) from {$from}");

                continue;
            }

            try {
                foreach ($files as $file) {
                    $this->copy($from, $to, $file);
                }
            } catch (RuntimeException $e) {
                $this->error("  {$label}: {$e->getMessage()} Left on {$from}.");

                continue;
            }

            $version->forceFill(['storage_disk' => $to])->save();
            // The document mirrors its newest version's storage fields
            $document = $version->document;
            if ($document && $document->disk_path === $version->disk_path) {
                $document->forceFill(['storage_disk' => $to])->save();
            }
            if ($this->option('delete-source')) {
                Storage::disk($from)->delete($files);
                if ($version->isBundle()) {
                    Storage::disk($from)->deleteDirectory($version->disk_path);
                }
            }

            $moved++;
            $this->line("  {$label}: moved ".count($files)." file(s) from {$from}");
        }

        // Documents saved before version rows keep their storage on the document itself
        foreach (Document::whereNotNull('disk_path')->doesntHave('versions')->get() as $document) {
            $this->warn("  {$document->slug}: has no version rows; not moved.");
        }

        $this->info("Moved {$moved} version(s).");

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function files(DocumentVersion $version): array
    {
        $disk = Storage::disk($version->disk());
        if ($version->isBundle()) {
            return $disk->allFiles($version->disk_path);
        }

        $sidecar = $version->representationPath();

        return array_values(array_filter([$version->disk_path, $sidecar !== null && $disk->exists($sidecar) ? $sidecar : null]));
    }

    private function copy(string $from, string $to, string $file): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);
        if (! $source->exists($file)) {
            throw new RuntimeException("{$file} is missing.");
        }

        $stream = $source->readStream($file);
        try {
            if (! $target->put($file, $stream)) {
                throw new RuntimeException("Could not write {$file}.");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($target->size($file) !== $source->size($file)) {
            throw new RuntimeException("{$file} copied with the wrong size.");
        }
    }
}

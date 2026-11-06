<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportPublisher
{
    public static function rules(bool $creating = false): array
    {
        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:120'],
            'visibility' => ['sometimes', Rule::in(['private', 'unlisted', 'public'])],
            'password' => ['sometimes', 'nullable', 'string', 'max:200', function ($attribute, $value, $fail) {
                if (is_string($value) && trim($value) !== '' && mb_strlen($value) < 8) {
                    $fail('The report password must contain at least 8 characters.');
                }
            }],
            'remove_password' => ['sometimes', 'boolean'],
            'blocks' => ['sometimes', 'array', 'max:100'],
        ];
    }

    public function create(Project $project, array $data): Report
    {
        $data = Validator::make($data, self::rules(true))->validate();

        return DB::transaction(function () use ($project, $data) {
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $report = new Report(['project_id' => $project->id, 'key' => Str::lower(Str::random(24)),
                'slug' => substr(Str::slug($data['title']) ?: 'report', 0, 111).'-'.Str::lower(Str::random(8)),
                'visibility' => 'private', 'draft_blocks' => []]);
            $this->apply($report, $data);
            $report->save();

            return $report;
        });
    }

    public function saveDraft(Report $report, array $data): Report
    {
        return DB::transaction(function () use ($report, $data) {
            $this->lock($report);
            $this->apply($report, $data);
            $report->save();

            return $report;
        });
    }

    public function publish(Report $report, array $data = []): ReportVersion
    {
        return DB::transaction(function () use ($report, $data) {
            $this->lock($report);
            $this->apply($report, $data);
            $blocks = $this->validateBlocks($report, $report->draft_blocks, pin: true, publishing: true);
            $number = max($report->next_version_number, (int) $report->versions()->max('number') + 1);
            $version = $report->versions()->create(['number' => $number, 'title' => $report->title,
                'description' => $report->description, 'blocks' => $blocks]);
            $report->next_version_number = $number + 1;
            $report->save();

            return $version;
        });
    }

    private function lock(Report $report): void
    {
        Project::whereKey($report->project_id)->lockForUpdate()->firstOrFail();
        $report->refresh();
    }

    private function apply(Report $report, array $data): void
    {
        $data = Validator::make($data, self::rules(! $report->exists))->validate();
        foreach (['title', 'description', 'visibility'] as $field) {
            if (array_key_exists($field, $data)) {
                $report->$field = $data[$field];
            }
        }
        if (array_key_exists('slug', $data) && filled($data['slug'])) {
            $slug = substr(Str::slug($data['slug']), 0, 120);
            // A report in the Trash keeps its name, so it can be restored
            if (! $slug || Report::withTrashed()->where('project_id', $report->project_id)->where('slug', $slug)->when($report->exists, fn ($q) => $q->whereKeyNot($report->id))->exists()) {
                throw ValidationException::withMessages(['slug' => 'Choose a unique report name within this project.']);
            }
            $report->slug = $slug;
        }
        if (! empty($data['remove_password'])) {
            $report->password_hash = null;
        } elseif (isset($data['password']) && trim($data['password']) !== '') {
            $report->password_hash = Hash::make($data['password']);
        }
        $report->draft_blocks = $this->validateBlocks($report, $data['blocks'] ?? $report->draft_blocks);
    }

    private function validateBlocks(Report $report, array $blocks, bool $pin = false, bool $publishing = false): array
    {
        $output = [];
        $ids = [];
        if (count($blocks) > 100 || ! array_is_list($blocks)) {
            throw ValidationException::withMessages(['blocks' => 'A report can contain up to 100 ordered blocks.']);
        }
        foreach ($blocks as $index => $block) {
            $prefix = 'blocks.'.$index;
            if (! is_array($block) || ! is_string($block['id'] ?? null) || $block['id'] === '' || mb_strlen($block['id']) > 80 || isset($ids[$block['id']])) {
                throw ValidationException::withMessages([$prefix.'.id' => 'Give each report block a unique identifier.']);
            }
            $ids[$block['id']] = true;
            if (($block['type'] ?? null) === 'text') {
                $data = Validator::make($block, ['id' => ['required', 'string', 'max:80'], 'text' => ['present', 'nullable', 'string', 'max:50000'], 'format' => ['sometimes', Rule::in(['html'])]])->validate();
                $html = ($data['format'] ?? null) === 'html';
                $output[] = ['id' => $data['id'], 'type' => 'text', 'text' => $html ? ReportText::sanitize($data['text'] ?? '') : ($data['text'] ?? '')]
                    + ($html ? ['format' => 'html'] : []);

                continue;
            }
            if (($block['type'] ?? null) !== 'artifact') {
                throw ValidationException::withMessages([$prefix.'.type' => 'Choose a text or artifact block.']);
            }
            $data = Validator::make($block, ['id' => ['required', 'string', 'max:80'],
                'document_id' => ['required', 'integer'], 'version_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'caption' => ['sometimes', 'nullable', 'string', 'max:2000'], 'share_unlisted' => ['sometimes', 'boolean']])->validate();
            $document = Document::availableTo($report->project_id)->where('driver', 'cloud')->find($data['document_id']);
            if (! $document) {
                throw ValidationException::withMessages([$prefix.'.document_id' => 'Choose an artifact from this project.']);
            }
            $number = isset($data['version_number']) ? (int) $data['version_number'] : null;
            $version = $number === null ? $document->latestVersion() : $document->findVersion($number);
            if (! $version) {
                throw ValidationException::withMessages([$prefix.'.version_number' => 'That artifact version is unavailable.']);
            }
            $share = (bool) ($data['share_unlisted'] ?? false);
            if ($publishing && $report->visibility === 'public' && $document->visibility !== 'public') {
                throw ValidationException::withMessages([$prefix.'.document_id' => 'Public reports can contain only public artifacts. Make “'.($document->title ?? $document->slug).'” public or remove it.']);
            }
            if ($publishing && $report->visibility === 'unlisted' && $document->visibility !== 'public' && ! $share) {
                throw ValidationException::withMessages([$prefix.'.share_unlisted' => 'Allow report readers to access this unlisted artifact before publishing.']);
            }
            $output[] = ['id' => $data['id'], 'type' => 'artifact', 'document_id' => $document->id,
                'version_number' => $pin ? $version->number : $number, 'caption' => $data['caption'] ?? null, 'share_unlisted' => $share]
                + ($pin ? ['artifact_title' => $document->title ?? $document->slug, 'artifact_kind' => $version->kind ?? $document->kind] : []);
        }

        return $output;
    }
}

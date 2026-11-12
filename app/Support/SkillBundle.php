<?php

namespace App\Support;

use App\Models\SkillVersion;
use Illuminate\Validation\ValidationException;

/** Exact-byte, executable-aware snapshots shared by all three clients. */
class SkillBundle
{
    public static function normalize(array $files): array
    {
        $out = [];
        $seen = [];
        $bytes = 0;
        if (count($files) > 256) {
            self::fail('A skill supports at most 256 files.');
        }
        foreach ($files as $file) {
            $path = $file['path'] ?? '';
            if (! is_string($path) || strlen($path) > 240 || ! preg_match('#^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$#D', $path)) {
                self::fail('Invalid skill file path.');
            }
            foreach (explode('/', $path) as $part) {
                if (in_array(strtolower($part), ['.', '..', '.git', '.env', '.framework', '.framework-skill.json', '__pycache__', 'node_modules', 'id_rsa']) || str_starts_with(strtolower($part), '.env.') || preg_match('/\.(pem|key)$/i', $part)) {
                    self::fail('Excluded skill file path.');
                }
            }
            if (isset($seen[strtolower($path)])) {
                self::fail('Duplicate skill file path.');
            }
            $seen[strtolower($path)] = true;
            if ($path === 'SKILL.md' && ($file['executable'] ?? false)) {
                self::fail('SKILL.md cannot be executable.');
            }
            $raw = array_key_exists('content', $file) && (is_string($file['content']) || $file['content'] === null) ? base64_decode($file['content'] ?? '', true) : false;
            if ($raw === false || strlen($raw) > 4 * 1024 * 1024) {
                self::fail('Invalid or oversized skill file.');
            }
            $bytes += strlen($raw);
            if ($bytes > 16 * 1024 * 1024) {
                self::fail('A skill supports at most 16 MB.');
            }
            $out[] = ['path' => $path, 'content' => base64_encode($raw), 'executable' => (bool) ($file['executable'] ?? false)];
        }
        usort($out, fn ($a, $b) => strcmp($a['path'], $b['path']));
        $paths = array_column($out, 'path');
        foreach ($paths as $path) {
            foreach ($paths as $other) {
                if (str_starts_with(strtolower($other), strtolower($path).'/')) {
                    self::fail('A skill path cannot be both a file and a directory.');
                }
            }
        }
        if (! in_array('SKILL.md', $paths, true)) {
            self::fail('SKILL.md is required.');
        }

        return $out;
    }

    public static function hash(array $files): string
    {
        $manifest = "framework-skill-bundle-v1\n";
        foreach (self::normalize($files) as $file) {
            $raw = self::hashBytes(base64_decode($file['content']));
            $manifest .= $file['path']."\t".hash('sha256', $raw)."\t".($file['executable'] ? '755' : '644')."\t".strlen($raw)."\n";
        }

        return hash('sha256', $manifest);
    }

    /**
     * A file's bytes as hashed. Text files hash with LF line endings, so a
     * CRLF checkout (git's core.autocrlf on Windows) matches its LF twin. A
     * file with a NUL byte is binary and hashes raw. R, Python, and fw
     * normalize the same way.
     */
    public static function hashBytes(string $raw): string
    {
        return str_contains($raw, "\0") ? $raw : str_replace("\r\n", "\n", $raw);
    }

    public static function files(SkillVersion $version, string $variant): array
    {
        $files = $version->bundle_files[$variant] ?? null;
        if ($files === null) {
            $files = [];
            foreach ($version->files ?? [] as $path => $content) {
                $files[] = ['path' => $path, 'content' => base64_encode($content), 'executable' => false];
            }
        }
        $files[] = ['path' => 'SKILL.md', 'content' => base64_encode($version->contentFor($variant)), 'executable' => false];

        return self::normalize($files);
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['bundle' => $message]);
    }
}

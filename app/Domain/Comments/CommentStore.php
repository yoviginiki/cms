<?php

namespace App\Domain\Comments;

use App\Models\Site;
use Illuminate\Support\Facades\File;

/**
 * File-backed comment store (F24, audit 2026-09-22).
 *
 * The public comments endpoints previously did an unlocked read-modify-write
 * on a JSON file (concurrent posts lost each other), collapsed non-ASCII
 * slugs into one file, and returned the raw record (email, IP) once a
 * comment was approved. Writes are now serialized with an exclusive lock,
 * files are keyed by a hash of the exact slug, and the public projection is
 * explicit. There is still no moderation UI: approval happens through
 * setStatus() (CLI/tinker) — the feature is a receiver, not a finished module.
 */
final class CommentStore
{
    public const MAX_PER_POST = 500;

    public function path(Site $site, string $slug): string
    {
        return storage_path("app/comments/{$site->id}/" . sha1($slug) . '.json');
    }

    /** @return array<int,array<string,mixed>> every stored comment (moderation view) */
    public function all(Site $site, string $slug): array
    {
        $path = $this->path($site, $slug);
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? array_values($data) : [];
    }

    /** Append one comment under an exclusive lock; returns the stored record. */
    public function append(Site $site, string $slug, array $comment): array
    {
        $record = [
            'id' => 'cmt_' . bin2hex(random_bytes(8)),
            'name' => (string) ($comment['name'] ?? ''),
            'email' => (string) ($comment['email'] ?? ''),
            'body' => (string) ($comment['body'] ?? ''),
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
            'ip' => (string) ($comment['ip'] ?? ''),
        ];

        $this->mutate($site, $slug, function (array $comments) use ($record) {
            $comments[] = $record;
            if (count($comments) > self::MAX_PER_POST) {
                $comments = array_slice($comments, -self::MAX_PER_POST);
            }

            return $comments;
        });

        return $record;
    }

    public function setStatus(Site $site, string $slug, string $id, string $status): bool
    {
        $found = false;
        $this->mutate($site, $slug, function (array $comments) use ($id, $status, &$found) {
            foreach ($comments as &$c) {
                if (($c['id'] ?? null) === $id) {
                    $c['status'] = $status;
                    $found = true;
                }
            }

            return $comments;
        });

        return $found;
    }

    /** @return array<int,array{id:string,name:string,body:string,created_at:string}> */
    public function approvedPublic(Site $site, string $slug): array
    {
        return array_values(array_map(
            [self::class, 'publicView'],
            array_filter($this->all($site, $slug), fn ($c) => ($c['status'] ?? 'pending') === 'approved'),
        ));
    }

    /** Public projection — never email, IP or moderation fields. */
    public static function publicView(array $c): array
    {
        return [
            'id' => (string) ($c['id'] ?? ''),
            'name' => (string) ($c['name'] ?? ''),
            'body' => (string) ($c['body'] ?? ''),
            'created_at' => (string) ($c['created_at'] ?? ''),
        ];
    }

    /** Read → transform → write the file under LOCK_EX (safe across processes). */
    private function mutate(Site $site, string $slug, callable $fn): void
    {
        $path = $this->path($site, $slug);
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Cannot open comment store.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock comment store.');
            }
            $raw = stream_get_contents($handle);
            $comments = json_decode((string) $raw, true);
            $comments = is_array($comments) ? array_values($comments) : [];

            $comments = $fn($comments);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}

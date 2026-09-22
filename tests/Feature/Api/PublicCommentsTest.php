<?php

namespace Tests\Feature\Api;

use App\Domain\Comments\CommentStore;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F24 (audit 2026-09-22) — the public comments API returned the stored
 * record (email + IP) for approved comments, lost concurrent writes
 * (read-modify-write on a JSON file), collapsed non-ASCII slugs and accepted
 * comments for posts that don't exist.
 */
class PublicCommentsTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        File::deleteDirectory(storage_path("app/comments/{$this->site->id}"));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path("app/comments/{$this->site->id}"));
        parent::tearDown();
    }

    private function makePost(string $slug, string $status = 'published'): Post
    {
        return Post::factory()->create(['site_id' => $this->site->id, 'slug' => $slug, 'status' => $status]);
    }

    private function submit(string $slug, string $name = 'Ana'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v1/sites/{$this->site->id}/comments/{$slug}", [
            'name' => $name, 'email' => 'ana@example.test', 'body' => 'Nice <b>post</b>',
        ]);
    }

    public function test_public_read_never_exposes_email_or_ip(): void
    {
        $this->makePost('hello');
        $this->submit('hello')->assertOk();

        $store = app(CommentStore::class);
        $all = $store->all($this->site, 'hello');
        $this->assertCount(1, $all);
        $this->assertSame('ana@example.test', $all[0]['email']); // kept for moderation
        $store->setStatus($this->site, 'hello', $all[0]['id'], 'approved');

        $res = $this->getJson("/api/v1/sites/{$this->site->id}/comments/hello")->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame(['id', 'name', 'body', 'created_at'], array_keys($res->json('data.0')));
        $this->assertStringNotContainsString('example.test', $res->getContent());
        $this->assertStringNotContainsString('"ip"', $res->getContent());
    }

    public function test_pending_comments_are_not_public(): void
    {
        $this->makePost('hello');
        $this->submit('hello')->assertOk();
        $this->getJson("/api/v1/sites/{$this->site->id}/comments/hello")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unknown_or_unpublished_posts_reject_comments(): void
    {
        $this->makePost('draft-post', 'draft');
        $this->submit('draft-post')->assertNotFound();
        $this->submit('nope')->assertNotFound();
        $this->getJson("/api/v1/sites/{$this->site->id}/comments/nope")->assertNotFound();
        $this->assertDirectoryDoesNotExist(storage_path("app/comments/{$this->site->id}"));
    }

    public function test_non_ascii_slugs_do_not_collide(): void
    {
        $this->makePost('тест-а');
        $this->makePost('тест-б');
        $this->submit(rawurlencode('тест-а'), 'A')->assertOk();
        $this->submit(rawurlencode('тест-б'), 'B')->assertOk();

        $store = app(CommentStore::class);
        $this->assertCount(1, $store->all($this->site, 'тест-а'));
        $this->assertCount(1, $store->all($this->site, 'тест-б'));
        $this->assertSame('A', $store->all($this->site, 'тест-а')[0]['name']);
    }

    public function test_concurrent_appends_from_two_processes_are_all_kept(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl not available');
        }
        $this->makePost('busy');
        $store = app(CommentStore::class);
        $store->append($this->site, 'busy', ['name' => 'seed', 'email' => 's@x', 'body' => 'seed', 'ip' => '127.0.0.1']);

        $children = [];
        foreach (['p1', 'p2'] as $tag) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                // child: no DB use, pure file appends
                for ($i = 0; $i < 40; $i++) {
                    $store->append($this->site, 'busy', ['name' => "{$tag}-{$i}", 'email' => 'c@x', 'body' => 'b', 'ip' => '127.0.0.1']);
                }
                // Replace the child's process image: a normal exit would run
                // PHP shutdown and roll back the PARENT's DB transaction over
                // the inherited socket.
                pcntl_exec('/bin/true');
                exit(1);
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $this->assertCount(81, $store->all($this->site, 'busy'));
    }
}

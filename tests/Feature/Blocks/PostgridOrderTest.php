<?php

namespace Tests\Feature\Blocks;

use App\Domain\Blocks\Definitions\PostgridBlockDefinition;
use App\Models\Post;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PostgridOrderTest extends TestCase
{
    public function test_order_option_is_validated(): void
    {
        $rules = (new PostgridBlockDefinition())->validationRules();
        foreach (['latest', 'oldest', 'title', 'title_desc'] as $o) {
            $this->assertTrue(Validator::make(['orderBy' => $o], $rules)->passes(), $o);
        }
        $this->assertTrue(Validator::make(['orderBy' => 'random'], $rules)->fails());
    }

    public function test_posts_follow_the_chosen_order(): void
    {
        $site = $this->createSiteWithPages(0);
        foreach ([['Бреза', '2026-01-10'], ['Акация', '2026-03-10'], ['Върба', '2026-02-10']] as [$title, $date]) {
            Post::factory()->create(['site_id' => $site->id, 'title' => $title, 'status' => 'published', 'published_at' => $date]);
        }
        $order = function (string $orderBy) use ($site): array {
            $html = view('blocks.postgrid', ['data' => ['orderBy' => $orderBy, 'showExcerpt' => false], 'site' => $site])->render();
            preg_match_all('/Акация|Бреза|Върба/u', $html, $m);

            return array_values(array_unique($m[0]));
        };

        $this->assertSame(['Акация', 'Върба', 'Бреза'], $order('latest'));
        $this->assertSame(['Бреза', 'Върба', 'Акация'], $order('oldest'));
        $this->assertSame(['Акация', 'Бреза', 'Върба'], $order('title'));
        $this->assertSame(['Върба', 'Бреза', 'Акация'], $order('title_desc'));
    }
}

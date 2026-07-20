<?php

namespace Tests\Feature\Forms;

use App\Domain\Blocks\Services\BlockService;
use App\Models\Block;
use App\Models\Page;
use App\Models\Site;
use Tests\TestCase;

/** S5 — Form Wizard appends a configured customform block to a page. */
class FormWizardTest extends TestCase
{
    private Site $site;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->page = Page::factory()->published()->create(['site_id' => $this->site->id]);
        app(BlockService::class)->syncBlocks($this->page, [
            ['type' => 'heading', 'order' => 0, 'data' => ['text' => 'Keep me', 'level' => 'h1']],
        ]);
    }

    private function wizardPayload(string $name = 'Artwork inquiry'): array
    {
        return [
            'name' => $name,
            'page_id' => $this->page->id,
            'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => true],
                ['label' => 'Email', 'type' => 'email', 'required' => true],
                ['label' => 'Question', 'type' => 'textarea'],
            ],
            'notify_email' => 'gallery@example.com',
            'success_message' => 'We will get back to you.',
        ];
    }

    public function test_wizard_appends_form_preserving_existing_blocks(): void
    {
        $existingHeading = Block::where('type', 'heading')->firstOrFail();

        $response = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/form-wizard", $this->wizardPayload())
            ->assertStatus(201);

        $this->assertSame('artwork-inquiry', $response->json('data.form_key'));

        // Existing block survived with its id (append, not replace)
        $this->assertNotNull(Block::find($existingHeading->id));

        $form = Block::where('type', 'customform')->firstOrFail();
        $this->assertSame('artwork-inquiry', $form->data['formKey']);
        $this->assertSame('gallery@example.com', $form->data['notifyEmail']);
        $this->assertCount(3, $form->data['fields']);
        $this->assertTrue($this->page->fresh()->needs_republish);

        // The appended form is inside a proper section→row→column chain
        $column = Block::find($form->parent_block_id);
        $row = Block::find($column->parent_block_id);
        $section = Block::find($row->parent_block_id);
        $this->assertSame(['column', 'row', 'section'], [$column->type, $row->type, $section->type]);
    }

    public function test_form_keys_are_unique_per_site(): void
    {
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/form-wizard", $this->wizardPayload())->assertStatus(201);
        $second = $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/form-wizard", $this->wizardPayload());

        $second->assertStatus(201);
        $this->assertSame('artwork-inquiry-2', $second->json('data.form_key'));
    }

    public function test_wizard_validates_fields_and_page(): void
    {
        $bad = $this->wizardPayload();
        $bad['fields'] = [];
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/form-wizard", $bad)->assertStatus(422);

        $foreign = $this->wizardPayload();
        $foreign['page_id'] = '00000000-0000-0000-0000-000000000000';
        $this->actingAsOwner()->postJson("/api/v1/sites/{$this->site->id}/form-wizard", $foreign)->assertStatus(422);
    }
}

<?php

namespace Tests\Feature\Forms;

use App\Http\Controllers\Api\V1\FormController;
use App\Models\FormSubmission;
use App\Models\Site;
use Tests\TestCase;

/**
 * F30 (audit 2026-09-22) — the CSV export keeps every field across schema
 * versions/forms and neutralises spreadsheet formula triggers (raw mode
 * available for machine consumers).
 */
class FormExportTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function csv(array $query = []): array
    {
        $res = $this->actingAsOwner()->get("/api/v1/sites/{$this->site->id}/form-submissions/export?" . http_build_query($query), $this->apiHeaders());
        $res->assertOk();
        $body = $res->streamedContent();
        $rows = array_values(array_filter(array_map('str_getcsv', explode("\n", trim($body)))));

        return $rows;
    }

    public function test_columns_are_the_union_across_schema_versions_and_forms(): void
    {
        FormSubmission::create(['site_id' => $this->site->id, 'form_key' => 'contact', 'data' => ['name' => 'Ana', 'email' => 'a@x']]);
        FormSubmission::create(['site_id' => $this->site->id, 'form_key' => 'contact', 'data' => ['name' => 'Ben', 'email' => 'b@x', 'phone' => '123']]);
        FormSubmission::create(['site_id' => $this->site->id, 'form_key' => 'newsletter', 'data' => ['email' => 'c@x', 'consent' => true]]);

        $rows = $this->csv();
        $this->assertSame(['submitted_at', 'form', 'name', 'email', 'phone', 'consent'], $rows[0]);
        $this->assertCount(4, $rows);
        $this->assertSame(['Ben', 'b@x', '123', ''], array_slice($rows[2], 2));
        $this->assertSame(['', 'c@x', '', 'yes'], array_slice($rows[3], 2));

        $only = $this->csv(['form_key' => 'newsletter']);
        $this->assertSame(['submitted_at', 'form', 'email', 'consent'], $only[0]);
    }

    public function test_formula_triggers_are_neutralised_unless_raw(): void
    {
        FormSubmission::create(['site_id' => $this->site->id, 'form_key' => 'f', 'data' => [
            'a' => '=HYPERLINK("http://evil")', 'b' => '+1+1', 'c' => '-2', 'd' => '@SUM(A1)', 'e' => "\tcmd", 'f' => '-12.5', 'g' => 'plain',
        ]]);
        $row = $this->csv()[1];
        $this->assertSame("'=HYPERLINK(\"http://evil\")", $row[2]);
        $this->assertSame("'+1+1", $row[3]);
        $this->assertSame('-2', $row[4], 'negative numbers stay numbers');
        $this->assertSame("'@SUM(A1)", $row[5]);
        $this->assertSame("'\tcmd", $row[6]);
        $this->assertSame('-12.5', $row[7]);
        $this->assertSame('plain', $row[8]);

        $raw = $this->csv(['raw' => 1])[1];
        $this->assertSame('=HYPERLINK("http://evil")', $raw[2]);
        $this->assertSame("'", '\'' );
        $this->assertSame('=x', FormController::csvSafe('=x')[1] . 'x');
    }
}

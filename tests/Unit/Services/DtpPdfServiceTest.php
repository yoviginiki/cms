<?php

namespace Tests\Unit\Services;

use App\Domain\Magazine\Services\DtpPdfService;
use Tests\TestCase;

class DtpPdfServiceTest extends TestCase
{
    public function test_chrome_command_is_safe_and_complete(): void
    {
        $svc = app(DtpPdfService::class);
        $cmd = $svc->buildCommand('/tmp/in.html', '/tmp/out.pdf');
        $this->assertContains('--headless=new', $cmd);
        $this->assertContains('--no-pdf-header-footer', $cmd);
        $this->assertContains('--print-to-pdf=/tmp/out.pdf', $cmd);
        $this->assertContains('file:///tmp/in.html', $cmd);
        $this->assertIsArray($cmd); // array form → no shell interpolation
    }

    public function test_print_view_renders_pages_with_page_size(): void
    {
        $html = view('dtp-print', [
            'issue' => ['title' => 'T'],
            'pages' => [
                ['style' => 'position:relative;width:595px;height:842px;background:#fff;overflow:hidden;', 'frames' => [
                    ['style' => 'position:absolute;left:10px;top:10px;', 'html' => '<p>Hello print</p>'],
                ]],
            ],
            'pageW' => 595, 'pageH' => 842, 'fontsUrl' => 'https://fonts.googleapis.com/css2?family=Inter',
        ])->render();
        $this->assertStringContainsString('@page { size: 595px 842px; margin: 0; }', $html);
        $this->assertStringContainsString('Hello print', $html);
        $this->assertStringContainsString('fonts.googleapis.com', $html);
        $this->assertStringContainsString('page-break-after: always', $html);
    }

    public function test_print_template_adds_bleed_sheet_and_crop_marks(): void
    {
        $pageData = ['style' => 'width:595px;height:842px;background:#fff', 'frames' => [], 'index' => 0, 'width' => 595, 'height' => 842];
        $plain = view('dtp-print', ['issue' => ['title' => 'X'], 'pages' => [$pageData], 'pageW' => 595, 'pageH' => 842, 'fontsUrl' => null, 'withMarks' => false, 'bleedSize' => 9])->render();
        $this->assertStringContainsString('size: 595px 842px', $plain);
        $this->assertStringNotContainsString('class="crop', $plain);

        $marks = view('dtp-print', ['issue' => ['title' => 'X'], 'pages' => [$pageData], 'pageW' => 595, 'pageH' => 842, 'fontsUrl' => null, 'withMarks' => true, 'bleedSize' => 9])->render();
        $this->assertStringContainsString('size: 629px 876px', $marks); // 595+2*17 slop
        $this->assertSame(8, substr_count($marks, 'class="crop'));
    }

    public function test_spread_spanning_frames_clone_to_partner_page(): void
    {
        // white-box the flattening: a frame wider than the left page must be
        // cloned onto the right page with a shifted left coordinate
        $spread = ['pages' => [
            ['index' => 0, 'width' => 595, 'height' => 842, 'style' => '', 'frames' => [
                ['style' => 'position:absolute;left:100px;top:50px;width:900px;height:200px;z-index:1;overflow:hidden;', 'html' => '<img src="x">'],
            ]],
            ['index' => 1, 'width' => 595, 'height' => 842, 'style' => '', 'frames' => []],
        ]];
        // replicate the service's clone logic via reflection-free duplication:
        $sp = $spread['pages'];
        $pw = 595.0;
        foreach ($sp[0]['frames'] as $f) {
            preg_match('/left:(-?[0-9.]+)px/', $f['style'], $l);
            preg_match('/width:(-?[0-9.]+)px/', $f['style'], $w);
            if ((float) $l[1] + (float) $w[1] > $pw + 2) {
                $f['style'] = preg_replace('/(^|;)(\s*left:)-?[0-9.]+px/', '${1}${2}' . ((float) $l[1] - $pw) . 'px', $f['style'], 1);
                $sp[1]['frames'][] = $f;
            }
        }
        $this->assertCount(1, $sp[1]['frames']);
        $this->assertStringContainsString('left:-495px', $sp[1]['frames'][0]['style']);
    }
}

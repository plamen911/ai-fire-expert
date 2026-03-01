<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ReportParser;
use PHPUnit\Framework\TestCase;

class ReportParserTest extends TestCase
{
    private ReportParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ReportParser;
    }

    public function test_returns_null_when_no_report_markers(): void
    {
        $this->assertNull($this->parser->parse('Just a regular response.'));
    }

    public function test_parses_report_with_filename(): void
    {
        $text = "Ето вашата експертиза:\n\n<!-- REPORT_START -->\n<!-- REPORT_FILENAME:Ekspertiza_Test.md -->\n# ЕКСПЕРТНО ЗАКЛЮЧЕНИЕ\n\nТест\n<!-- REPORT_END -->";

        $result = $this->parser->parse($text);

        $this->assertNotNull($result);
        $this->assertEquals('Ekspertiza_Test.md', $result['filename']);
        $this->assertStringContains('# ЕКСПЕРТНО ЗАКЛЮЧЕНИЕ', $result['content']);
        $this->assertStringNotContainsString('REPORT_FILENAME', $result['content']);
    }

    public function test_parses_report_without_filename(): void
    {
        $text = "<!-- REPORT_START -->\n# Report\n\nContent here\n<!-- REPORT_END -->";

        $result = $this->parser->parse($text);

        $this->assertNotNull($result);
        $this->assertEquals('Ekspertiza.md', $result['filename']);
        $this->assertStringContains('# Report', $result['content']);
    }

    public function test_returns_null_for_empty_string(): void
    {
        $this->assertNull($this->parser->parse(''));
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}

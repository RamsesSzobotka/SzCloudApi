<?php

namespace Tests\Unit;

use App\utils\NameSanitizer;
use PHPUnit\Framework\TestCase;

class NameSanitizerTest extends TestCase
{
    public function test_isValid_returns_true_for_clean_name(): void
    {
        $this->assertTrue(NameSanitizer::isValid('Mi archivo'));
        $this->assertTrue(NameSanitizer::isValid('doc-2024.pdf'));
    }

    public function test_isValid_returns_false_for_invalid_chars(): void
    {
        $this->assertFalse(NameSanitizer::isValid('Mi archivo:name'));
        $this->assertFalse(NameSanitizer::isValid('test/file'));
        $this->assertFalse(NameSanitizer::isValid('file"with"quotes'));
    }

    public function test_analyze_returns_correct_structure(): void
    {
        $result = NameSanitizer::analyze('Mi archivo:name');

        $this->assertSame('Mi archivo:name', $result['original_name']);
        $this->assertFalse($result['valid']);
    }

    public function test_analyze_marks_valid_name(): void
    {
        $result = NameSanitizer::analyze('Mi archivo');

        $this->assertSame('Mi archivo', $result['original_name']);
        $this->assertTrue($result['valid']);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the ALTO library.
 *
 * © 2025–present Simon André
 *
 * For full copyright and license information, please see
 * the LICENSE file distributed with this source code.
 */

namespace Alto\Code\Diff\Tests\Util;

use Alto\Code\Diff\Exception\BinaryInputException;
use Alto\Code\Diff\Util\BinaryGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinaryGuard::class)]
final class BinaryGuardTest extends TestCase
{
    public function testEmptyStringDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        BinaryGuard::assertText('');
    }

    public function testPlainTextDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        BinaryGuard::assertText('Hello, World!');
    }

    public function testMultilineTextDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        BinaryGuard::assertText("Line 1\nLine 2\nLine 3\n");
    }

    public function testTextWithTabsDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        BinaryGuard::assertText("Column1\tColumn2\tColumn3");
    }

    public function testTextWithCarriageReturnDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        BinaryGuard::assertText("Line 1\r\nLine 2\r\n");
    }

    public function testTextWithAllowedControlCharactersDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        BinaryGuard::assertText("Tab:\tNewline:\nCarriage return:\r");
    }

    public function testNullByteThrows(): void
    {
        $this->expectException(BinaryInputException::class);
        $this->expectExceptionMessage('Input appears to be binary (contains null bytes)');
        BinaryGuard::assertText("Text with null\x00byte");
    }

    public function testNullByteAtStartThrows(): void
    {
        $this->expectException(BinaryInputException::class);
        $this->expectExceptionMessage('Input appears to be binary (contains null bytes)');
        BinaryGuard::assertText("\x00Start with null");
    }

    public function testNullByteAtEndThrows(): void
    {
        $this->expectException(BinaryInputException::class);
        $this->expectExceptionMessage('Input appears to be binary (contains null bytes)');
        BinaryGuard::assertText("End with null\x00");
    }

    public function testTooManyNonPrintableCharactersThrows(): void
    {
        // Create a string with 40% non-printable characters (above the 30% threshold)
        $text = str_repeat("\x01\x02ABC", 100); // 40% non-printable

        $this->expectException(BinaryInputException::class);
        $this->expectExceptionMessage('Input appears to be binary (too many non-printable characters)');
        BinaryGuard::assertText($text);
    }

    public function testJustBelowThresholdDoesNotThrow(): void
    {
        // Create a string with ~29% non-printable characters (below the 30% threshold)
        $text = str_repeat("\x01ABC", 100).str_repeat('A', 50); // ~29% non-printable

        $this->expectNotToPerformAssertions();
        BinaryGuard::assertText($text);
    }

    public function testLargeTextOnlyChecksFirst8192Bytes(): void
    {
        // Create a large text file where only the first part is clean
        $cleanPart = str_repeat("Clean text line\n", 550); // ~8800 bytes (more than 8192)
        $binaryPart = str_repeat("\x00", 10000); // Binary content after 8192 bytes

        // Should not throw because the null bytes are beyond the CHECK_BYTES limit
        $this->expectNotToPerformAssertions();
        BinaryGuard::assertText($cleanPart.$binaryPart);
    }

    public function testBinaryInFirst8192BytesThrows(): void
    {
        // Create text with binary content within the first 8192 bytes
        $text = str_repeat("Text\n", 100)."\x00".str_repeat("More text\n", 100);

        $this->expectException(BinaryInputException::class);
        $this->expectExceptionMessage('Input appears to be binary (contains null bytes)');
        BinaryGuard::assertText($text);
    }

    public function testTypicalSourceCodeDoesNotThrow(): void
    {
        $sourceCode = <<<'PHP'
<?php

namespace App;

class Example
{
    public function method(): void
    {
        $variable = "value";
        echo "Hello\n";
    }
}
PHP;

        $this->expectNotToPerformAssertions();
        BinaryGuard::assertText($sourceCode);
    }

    public function testUnicodeTextDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        BinaryGuard::assertText('Hello 世界! Привет мир! مرحبا بالعالم!');
    }

    public function testControlCharacterBesideAllowedOnesThrows(): void
    {
        // Use control character 0x01 (SOH) which is not tab/newline/CR
        // Create enough of them to exceed the threshold
        $text = str_repeat("\x01\x02\x03ABC", 50);

        $this->expectException(BinaryInputException::class);
        $this->expectExceptionMessage('Input appears to be binary (too many non-printable characters)');
        BinaryGuard::assertText($text);
    }
}

<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application;

use Paxofi\CorporateWebsite\Application\Contact\EnquiryValidator;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnquiryValidatorTest extends TestCase
{
    private const VALID = ['name' => 'Ada Lovelace', 'email' => 'Ada@Example.com', 'company' => 'Analytical Ltd', 'message' => "Hello\nWorld"];

    public function testNormalisesValidInput(): void
    {
        $submission = (new EnquiryValidator())->validate(['name' => "  Ada\x07 Lovelace "] + self::VALID);

        self::assertSame('Ada Lovelace', $submission->name);
        self::assertSame('ada@example.com', $submission->email);
        self::assertSame('Analytical Ltd', $submission->company);
        self::assertSame("Hello\nWorld", $submission->message, 'newlines are preserved in the message');
        self::assertFalse($submission->isLikelySpam);
    }

    public function testEmptyCompanyBecomesNull(): void
    {
        self::assertNull((new EnquiryValidator())->validate(['company' => '   '] + self::VALID)->company);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidInputs(): iterable
    {
        yield 'missing name' => [['name' => ''], 'name'];
        yield 'whitespace message' => [['message' => " \n "], 'message'];
        yield 'bad email' => [['email' => 'not-an-email'], 'email'];
        yield 'array injection' => [['email' => ['a@b.co']], 'email'];
        yield 'name too long' => [['name' => str_repeat('a', 161)], 'name'];
        yield 'message too long' => [['message' => str_repeat('a', 10001)], 'message'];
        yield 'invalid utf-8' => [['name' => "\xC3\x28"], 'name'];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidInput(array $override, string $field): void
    {
        try {
            (new EnquiryValidator())->validate($override + self::VALID);
            self::fail('Expected ValidationFailed');
        } catch (ValidationFailed $exception) {
            self::assertArrayHasKey($field, $exception->fieldErrors());
        }
    }

    public function testLengthIsMeasuredInCharactersNotBytes(): void
    {
        $submission = (new EnquiryValidator())->validate(['name' => str_repeat('é', 160)] + self::VALID);

        self::assertSame(160, mb_strlen($submission->name));
    }

    public function testFilledHoneypotFlagsSpam(): void
    {
        self::assertTrue((new EnquiryValidator())->validate(['website' => 'https://spam.example'] + self::VALID)->isLikelySpam);
    }
}

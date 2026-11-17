<?php

namespace Tests\Feature\Delivery;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Every delivery string exists in both English and Swahili, with the same placeholders. */
class DeliveryLanguageFilesTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function files(): array
    {
        return ['delivery' => ['delivery']];
    }

    #[DataProvider('files')]
    public function test_english_and_swahili_have_the_same_keys_and_placeholders(string $file): void
    {
        $en = Arr::dot(require lang_path("en/{$file}.php"));
        $sw = Arr::dot(require lang_path("sw/{$file}.php"));

        $this->assertSame([], array_keys(array_diff_key($en, $sw)), "missing in sw/{$file}.php");
        $this->assertSame([], array_keys(array_diff_key($sw, $en)), "missing in en/{$file}.php");

        foreach ($en as $key => $text) {
            preg_match_all('/:([a-z_]+)/', (string) $text, $a);
            preg_match_all('/:([a-z_]+)/', (string) $sw[$key], $b);
            $this->assertEqualsCanonicalizing(array_unique($a[1]), array_unique($b[1]), "placeholders differ in {$file}.{$key}");
        }
    }
}

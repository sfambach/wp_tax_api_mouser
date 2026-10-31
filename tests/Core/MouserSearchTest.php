<?php declare(strict_types=1);

namespace TaxApiMouser\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiMouser\Core\MouserApiError;
use TaxApiMouser\Core\MouserSearch;

/** The fixture is a real answer of the Search API (2026-10-31, keyword BC547C), cut to the fields the module reads. */
final class MouserSearchTest extends TestCase
{
    public function testAMouserNumberIsLookedUpExactlyAndAnythingElseByKeyword(): void
    {
        self::assertSame('/search/partnumber', MouserSearch::request('637-BC547C')['path']);
        self::assertSame('/search/keyword', MouserSearch::request('BC547C')['path']);
        self::assertSame('LM317 TO-220', MouserSearch::request('  LM317 TO-220 ')['body']['SearchByKeywordRequest']['keyword']);

        $this->expectException(MouserApiError::class);
        MouserSearch::request(' ');
    }

    public function testTheAnswerBecomesOffersWithThePriceOfTheBreakTheQuantityReaches(): void
    {
        $answer = json_decode((string) file_get_contents(__DIR__ . '/fixtures/search-bc547c.json'), true);
        $offers = MouserSearch::offers($answer, 25);

        self::assertSame('637-BC547C', $offers[0]['number']);
        self::assertSame(['Mouser', 'BC547C', 20280, '0.081', 'EUR'], [$offers[0]['merchant'], $offers[0]['maker_number'], $offers[0]['stock'], $offers[0]['price'], $offers[0]['currency']]);
        self::assertSame('0.129', MouserSearch::offers($answer, 1)[0]['price']);
    }

    public function testPricesInEveryNotation(): void
    {
        self::assertSame('0.129', MouserSearch::decimal('0,129 €'));
        self::assertSame('1234.50', MouserSearch::decimal('1.234,50 €'));
        self::assertSame('0.13', MouserSearch::decimal('$0.13'));
        self::assertSame('5', MouserSearch::decimal('5 €'));
        self::assertNull(MouserSearch::decimal('—'));
    }

    public function testErrorsAndARefusedKeyAreRefusals(): void
    {
        try {
            MouserSearch::offers(['Errors' => [['Code' => 'Invalid', 'Message' => 'Invalid unique identifier.']]], 1);
            self::fail('errors are no offers');
        } catch (MouserApiError $e) {
            self::assertSame('refused', $e->reason);
        }

        $this->expectException(MouserApiError::class);
        MouserSearch::offers(['Message' => 'Authorization has been denied for this request.'], 1);
    }
}

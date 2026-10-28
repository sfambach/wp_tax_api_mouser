<?php declare(strict_types=1);

namespace TaxApiMouser\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiMouser\Core\MouserMailParser;
use Taxmod\Core\Import\Mail\MimeParser;

/**
 * Each expectation is what the Python importer (legacy/ in Taxmod Mail) read from the same synthetic mail.
 */
final class MouserMailParserTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function read(string $fixture): array
    {
        $parser = new MouserMailParser('0.0.1');
        $mail = (new MimeParser())->parse(1, (string) file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.eml'));
        self::assertTrue($parser->recognises($mail), 'not recognised: ' . $fixture);

        return $parser->parse($mail)->toArray();
    }

    public function testMouserConfirmationSkipsTheMakersNumber(): void
    {
        $order = self::read('m1');

        self::assertSame(['12345678', '20', '4.38', '27.43'], [$order['order_number'], $order['shipping_cost'], $order['fees'], $order['total']]);
        self::assertSame([['title' => 'LM317TG Linear Voltage Regulator', 'quantity' => 5.0, 'unit_price' => '0.61', 'article_number' => '595-LM317TG']], $order['lines']);
    }

    public function testItNamesWhatItReads(): void
    {
        self::assertNotSame([], (new MouserMailParser('1.2.3'))->kinds());
        self::assertSame('1.2.3', (new MouserMailParser('1.2.3'))->version());
    }
}

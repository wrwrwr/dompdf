<?php
namespace Dompdf\Tests;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Dompdf\Options;
use Dompdf\Outline;
use Dompdf\Tests\TestCase;

class OutlineTest extends TestCase
{
    public function testTreeMethods(): void
    {
        $outline = new Outline(new Options());
        $node1 = new DOMElement('n1');
        $node2 = new DOMElement('n2');
        $outline->add($node1, null);
        $outline->add($node2, $node1);
        $this->assertTrue($outline->contains($node1));
        $this->assertTrue($outline->contains($node2));
        $this->assertNull($outline->get_parent($node1));
        $this->assertEquals($outline->get_parent($node2), $node1);
    }

    /**
     * @dataProvider fromDocumentProvider
     */
    public function testFromDocument(string $content, string $selector, array $expected): void
    {
        $outline = new Outline(new Options(['outline_selector' => $selector]));
        $document = new DOMDocument();
        $document->loadHTML($content);
        $xpath = new DOMXPath($document);
        $outline->from_document($xpath);

        $headings = $xpath->query($selector);
        $this->assertEquals(count($headings), $outline->get_count());
        foreach ($headings as $heading) {
            $this->assertTrue($outline->contains($heading));
            $this->assertEquals(
                $expected[$heading->textContent],
                $outline->get_parent($heading)->textContent ?? null
            );
        }
    }

    public static function fromDocumentProvider(): array {
        return [
            [
                '<h1>T</h1>',
                '//h1',
                ['T' => null]
            ],
            [
                '<h1>T</h1>',
                '//h2 | //h3',
                []
            ],
            [
                '<h3>A</h3>',
                '//h2 | //h3',
                ['A' => null]
            ],
            [
                '<h2>A</h2><h3>a</h3><h4>b</h4><h3>c</h3>',
                '//h2 | //h3',
                ['A' => null, 'a' => 'A', 'c' => 'A']
            ],
            [
                '<h1>T</h1><h2>A</h2><h3>a</h3><h4>z</h4><h2>B</h2><h3>b</h3><h3>c</h3>',
                '//h2 | //h3',
                ['A' => null, 'B' => null, 'a' => 'A', 'b' => 'B', 'c' => 'B']
            ]
        ];
    }

}

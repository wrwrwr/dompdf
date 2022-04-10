<?php
namespace Dompdf\Tests;

use Dompdf\Cpdf;
use Dompdf\Tests\TestCase;

class CpdfTest extends TestCase
{
    public function testOutlineItems()
    {
        $cpdf = new Cpdf();
        $cpdf->addDestination('dest_A', []);
        $cpdf->addOutlineItem('A', null, 'title_A', 'dest_A');
        $cpdf->addDestination('dest_b', []);
        $cpdf->addOutlineItem('b', 'A', 'title_b', 'dest_b');
        $cpdf->addDestination('dest_c', []);
        $cpdf->addOutlineItem('c', 'A', 'title_c', 'dest_c');
        $output = $cpdf->output();

        $catalog = $this->getCatalog($output);
        $this->assertIsReference($catalog['Outlines']);

        $outlineId = $this->parseReference($catalog['Outlines']);
        $outline = $this->getDict($outlineId, $output);
        $this->assertEquals($outline['Type'], '/Outlines');
        $this->assertIsReference($outline['First']);
        $this->assertIsReference($outline['Last']);
        $this->assertEquals($outline['First'], $outline['Last']);
        $this->assertEquals($outline['Count'], 1);

        $parentId = $this->parseReference($outline['First']);
        $parent = $this->getDict($parentId, $output);
        $this->assertEquals($parent['Title'], '(title_A)');
        $this->assertIsReference($parent['Dest']);
        $parentParentId = $this->assertIsReference($parent['Parent']);
        $this->assertEquals($parentParentId, $outlineId);
        $this->assertNotContains('Prev', $parent);
        $this->assertNotContains('Next', $parent);
        $this->assertIsReference($parent['First']);
        $this->assertIsReference($parent['Last']);
        $this->assertEquals($parent['Count'], -2);

        $childId = $this->parseReference($parent['First']);
        $child = $this->getDict($childId, $output);
        $this->assertEquals($child['Title'], '(title_b)');
        $this->assertIsReference($child['Dest']);
        $childParentId = $this->assertIsReference($child['Parent']);
        $this->assertEquals($childParentId, $parentId);
        $this->assertNotContains('Prev', $child);
        $this->assertIsReference($child['Next']);
        $this->assertEquals($child['Next'], $parent['Last']);
        $this->assertNotContains('First', $child);
        $this->assertNotContains('Last', $child);
        $this->assertNotContains('Count', $child);
    }

    protected function getCatalog($output)
    {
        $trailer = $this->getTrailer($output);
        $id = $this->assertIsReference($trailer['Root']);
        return $this->getDict($id, $output);
    }

    protected function getReference($id, $output)
    {
        $contents = $this->getObject($id, $output);
        return $this->assertIsReference($contents);
    }

    protected function assertIsReference($contents)
    {
        $this->assertIsString($contents);
        $id = $this->parseReference($contents);
        $this->assertNotNull($id);
        return $id;
    }

    protected function parseReference($contents)
    {
        preg_match('~(\d+) 0 R~', $contents, $matches);
        return $matches[1] ?? null;
    }

    protected function getDict($id, $output)
    {
        $contents = $this->getObject($id, $output);
        return $this->assertIsDict($contents);
    }

    protected function assertIsDict($contents)
    {
        $this->assertIsString($contents);
        $this->assertStringStartsWith('<<', $contents);
        $this->assertStringEndsWith('>>', $contents);
        // Nested dictionaries or other complex values are not supported.
        $this->assertStringNotContainsString('<<', substr($contents, 2));
        $dict = $this->parseDict($contents);
        $this->assertNotNull($dict);
        return $dict;
    }

    protected function parseDict($contents)
    {
        $dict = [];
        $lines = preg_split('~\n~', trim(substr($contents, 2, -2)));
        foreach ($lines as $line) {
            preg_match('~/(\w+)(.+)~', $line, $matches);
            if (!$matches) {
                return null;
            }
            $dict[$matches[1]] = trim($matches[2]);
        }
        return $dict;
    }

    protected function getObject($id, $output)
    {
        $offsets = $this->getOffsets($output);
        preg_match(
            '~^\d+ 0 obj(.+?)\nendobj\n~s',
            substr($output, $offsets[$id]),
            $matches
        );
        $this->assertNotEmpty($matches);
        return trim($matches[1]);
    }

    private function getTrailer($output)
    {
        preg_match('~trailer\n(.+)\nstartxref[^<]+$~s', $output, $matches);
        $this->assertNotEmpty($matches);
        return $this->parseDict($matches[1]);
    }

    private function getOffsets($output)
    {
        preg_match('~(\d+)\n%%EOF\n$~', $output, $matches);
        $this->assertNotEmpty($matches);
        $lines = preg_split('~\n~', substr($output, $matches[1]));
        [$first, $count] = explode(' ', next($lines));
        $offsets = [];
        for ($index = 0; $index < $count; $index++) {
            $offsets[$first + $index] = (int)substr(next($lines), 0, 10);
        }
        return $offsets;
    }
}

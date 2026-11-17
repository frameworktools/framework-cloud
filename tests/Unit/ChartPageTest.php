<?php

namespace Tests\Unit;

use App\Support\ChartPage;
use PHPUnit\Framework\TestCase;

class ChartPageTest extends TestCase
{
    public function test_a_single_view_without_a_width_fills_the_frame(): void
    {
        $spec = ChartPage::responsive(['mark' => 'bar', 'encoding' => ['x' => ['field' => 'cyl', 'type' => 'nominal']]]);

        $this->assertSame('container', $spec['width']);
        $this->assertSame(['type' => 'fit', 'contains' => 'padding'], $spec['autosize']);
        $this->assertSame('container', ChartPage::responsive(['layer' => [['mark' => 'line']]])['width']);
    }

    public function test_sized_composite_and_faceted_views_keep_their_own_sizes(): void
    {
        foreach ([
            ['mark' => 'bar', 'width' => 300],
            ['hconcat' => [['mark' => 'bar'], ['mark' => 'point']]],
            ['facet' => ['field' => 'species'], 'spec' => ['mark' => 'point']],
            ['mark' => 'point', 'encoding' => ['column' => ['field' => 'species']]],
            ['$schema' => 'https://vega.github.io/schema/vega/v5.json', 'marks' => []],
        ] as $spec) {
            $this->assertSame($spec, ChartPage::responsive($spec));
        }
    }
}

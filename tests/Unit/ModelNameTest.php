<?php

namespace Tests\Unit;

use App\Support\ModelName;
use PHPUnit\Framework\TestCase;

/** The reader page names models as framework-embed does (tests/fixtures/model-names.json, from its spec). */
class ModelNameTest extends TestCase
{
    public function test_names_match_the_embed_renderer(): void
    {
        $cases = json_decode(file_get_contents(__DIR__.'/../fixtures/model-names.json'), true);
        $named = 0;
        foreach ($cases as $case) {
            $name = ModelName::for($case['model']);
            $meta = explode(' · Response: ', $case['meta'])[0];
            if ($name !== null) {
                $this->assertSame($meta, $name, json_encode($case['model']));
                $named++;
            }
        }
        $this->assertGreaterThan(20, $named);
        $this->assertSame('Logistic regression', ModelName::for(['type' => 'glm', 'family' => 'binomial', 'link' => 'logit']));
        $this->assertNull(ModelName::for(['type' => 'other']));
    }
}

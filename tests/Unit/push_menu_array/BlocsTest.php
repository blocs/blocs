<?php

namespace push_menu_array;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';

use Blocs\Option;
use Blocs\Tests\BlocsTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;

class BlocsTest extends BlocsTestCase
{
    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        Option::add('type', [
            'sales' => 'sales',
            'service' => 'サービス',
        ]);

        Option::add('sex2', [
            'sales' => 'sales',
            'service' => 'サービス',
            'part' => '品番',
        ]);

        Option::add('size', ['xl' => 'XL']);
        Option::add('size', ['xxl' => 'XXL']);

        $path = $this->templatePath();
        $actual = $this->generate();
        $actual .= json_encode(Option::get($path, 'type')).'<br />';
        $actual .= json_encode(Option::get($path, 'sex2')).'<br />';

        $this->assertSnapshot($actual);
    }
}

<?php

namespace push_menu;

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
        Option::add('type', 'sales');
        Option::add('type', ['service' => 'サービス']);

        Option::add('sex2', 'sales');
        Option::add('sex2', ['service' => 'サービス']);
        Option::add('sex2', ['part' => '品番']);

        Option::add('size', ['xl' => 'XL']);
        Option::add('size', ['xxl' => 'XXL']);

        $path = $this->templatePath();
        $actual = $this->generate();
        $actual .= json_encode(Option::get($path, 'type')).'<br />';
        $actual .= json_encode(Option::get($path, 'sex2')).'<br />';
        $actual .= json_encode(Option::get($path, 'size')).'<br />';

        $this->assertSnapshot($actual);
    }
}

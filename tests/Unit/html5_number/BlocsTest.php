<?php

namespace html5_number;

require_once dirname(__DIR__, 2).'/BlocsTestCase.php';

use Blocs\Tests\BlocsTestCase;
use Blocs\Validate;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;

class BlocsTest extends BlocsTestCase
{
    #[Test, RunInSeparateProcess]
    public function test(): void
    {
        $this->generate();
        [$rules] = Validate::get($this->templatePath());

        // step を書かない場合の既定は step=1 なので整数のみ
        $this->assertSame(['nullable', 'integer'], $rules['price']);
        $this->assertSame(['nullable', 'integer', 'min:1'], $rules['qty']);

        // 小数の step は小数を許容する
        $this->assertSame(['nullable', 'numeric', 'min:0', 'max:100'], $rules['amount']);

        // "any" は数値以外の指定なので小数を許容する
        $this->assertSame(['nullable', 'numeric'], $rules['anystep']);

        // 整数の step は整数のみ
        $this->assertSame(['nullable', 'integer'], $rules['evenstep']);
        $this->assertSame(['nullable', 'integer'], $rules['onestep']);

        // "any" は大文字小文字を区別しない
        $this->assertSame(['nullable', 'numeric'], $rules['upperany']);

        // 0以下・解釈できない step は既定の step=1 に戻るのでブラウザと同じく整数のみ
        $this->assertSame(['nullable', 'integer'], $rules['zerostep']);
        $this->assertSame(['nullable', 'integer'], $rules['negstep']);
        $this->assertSame(['nullable', 'integer'], $rules['badstep']);
    }
}

<?php

namespace html5_pattern;

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

        // 区切り文字は # を使い、/ はそのまま残る
        $this->assertSame(['nullable', 'regex:#^(?:[A-Z]+/[0-9]+)$#'], $rules['plain']);

        // テンプレート側の \/ はそのまま残る
        $this->assertSame(['nullable', 'regex:#^(?:[A-Z]+\/[0-9]+)$#'], $rules['escaped']);

        // / を含まないパターンはそのまま
        $this->assertSame(['nullable', 'regex:#^(?:^[0-9]{3}-[0-9]{4}$)$#'], $rules['noslash']);

        // パターン内の # はエスケープされる
        $this->assertSame(['nullable', 'regex:#^(?:a\\#b)$#'], $rules['hash']);

        // 末尾の \ が区切り文字を壊さない
        $this->assertSame(['nullable', 'regex:#^(?:abc\\\\)$#'], $rules['backslash']);

        // 生成された正規表現がそのまま preg_match で使える
        foreach (['plain', 'escaped', 'noslash', 'hash', 'backslash'] as $formName) {
            $regex = substr($rules[$formName][1], strlen('regex:'));
            $this->assertNotFalse(@preg_match($regex, ''), $formName.' の正規表現が不正です');
        }

        $this->assertSame(1, preg_match(substr($rules['plain'][1], strlen('regex:')), 'AB/12'));
        $this->assertSame(0, preg_match(substr($rules['plain'][1], strlen('regex:')), 'xAB/12'));
        $this->assertSame(0, preg_match(substr($rules['plain'][1], strlen('regex:')), 'AB/12x'));
        $this->assertSame(1, preg_match(substr($rules['hash'][1], strlen('regex:')), 'a#b'));
        $this->assertSame(1, preg_match(substr($rules['backslash'][1], strlen('regex:')), 'abc\\'));
    }
}

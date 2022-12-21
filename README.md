<div align="center"><img src="https://raw.githubusercontent.com/blocs/blocs/main/logo.png" /></div>

# The PHP Template engine for Laravel
Laravelのためのテンプレートエンジン

[![Latest stable version](https://img.shields.io/packagist/v/blocs/blocs)](https://packagist.org/packages/blocs/blocs)
[![Total downloads](https://img.shields.io/packagist/dt/blocs/blocs)](https://packagist.org/packages/blocs/blocs)
[![GitHub code size](https://img.shields.io/github/languages/code-size/blocs/blocs)](https://github.com/blocs/blocs)
[![GitHub license](https://img.shields.io/github/license/blocs/blocs)](https://github.com/blocs/blocs)
[![Laravel awesome](https://img.shields.io/badge/Awesome-Laravel-green)](https://github.com/blocs/blocs)
[![Laravel version](https://img.shields.io/badge/laravel-%3E%3D7-green)](https://github.com/blocs/blocs)
[![PHP version](https://img.shields.io/badge/php-%3E%3D7.2.5-blue)](https://github.com/blocs/blocs)

[**Website**](https://blocs.jp/)
| [**Document**](https://blocs.jp/reference/)
| [**English**](https://blocs.jp/en/readme.html)

# 概要
BLOCSは、PHPのテンプレートエンジンです。BLOCSは、プログラムで作ったデータとテンプレートで指定したデータ属性でHTMLを動的に生成します。テンプレートエンジンを使うことで、プログラム（ロジック）とテンプレート（デザイン）を分離することができます。

ロジックとデザインを分離して疎な関係にすることで、プログラマーとコーダーのお互いのソース変更や開発の遅れなどの影響を最小限にし、効率的な開発、維持を行うことができます。

LaravelにはBladeがありますが、より使い勝手のいいテンプレートエンジンを目指して開発しています。

## 特徴
- HTMLと相性のよい記述方法（タグ記法、コメント記法）、Bladeも使える
```html
<font color="red" data-exist=$error data-val=$message>Message</font>
```

- テンプレートでバリデーションを指定
```html
<form method="post">
@csrf
<label for="name">名前</label>
<input type="text" name="name" data-filter="katakana" required />
<!-- data-form="name" data-validate="required" data-lang="必須入力です。" -->

@error("name") <div>{{ $message }}</div> @enderror
<input type="submit" />
</form>
```

- `select` `radio` `checkbox`の項目を動的に追加

# 導入方法
composerで導入してください。

```sh
hiroyuki@blocs test-laravel % composer require blocs/blocs    
Info from https://repo.packagist.org: #StandWithUkraine
Using version dev-main for blocs/blocs
./composer.json has been updated
Running composer update blocs/blocs
Loading composer repositories with package information
Info from https://repo.packagist.org: #StandWithUkraine
Updating dependencies
Lock file operations: 1 install, 0 updates, 0 removals
  - Locking blocs/blocs (dev-main 1c25ad6)
Writing lock file
Installing dependencies from lock file (including require-dev)
```

## システム要件
Laravel >= 7  
php >= 7.2.5

# 使い方
BLOCSテンプレートのファイル名は`*.blocs.html`です。データ属性は、HTMLタグに属性を追加するタグ記法と、コメントで記述するコメント記法の２つの記述方法があります。4種類のデータ属性をタグ記法とコメント記法で記述してHTMLを動的に生成します。

## タグ記法
タグ記法は、HTMLタグにデータ属性`data-*`を追加する記述方法です。開始タグに追加したデータ属性は、終了タグまで影響します。下記の例では`$message`の値で`font`の間のコンテンツをすべて置換します。追加したデータ属性は、BLOCSが生成したHTMLでは削除されます。

/resources/views/example.blocs.html  
2行目 `$error`があれば、`$message`を表示
```html
<html>
<font color="red" data-exist=$error data-val=$message>Message</font>
</html>
```

/routes/web.php
```php
Route::get("/blocs", function () {
    return view("example", [
		"error" => true,
		"message" => "A fatal error has occurred."
    ]);
});
```

http://127.0.0.1:8000/blocs
```html
<html>
<font color="red">A fatal error has occurred.</font>
</html>
```

## コメント記法
他のテンプレートを読み込む時や、HTMLタグに属性を動的に追加する時に、コメント記法で記述します。データ属性`data-attribute`は、コメント記法の次にあるHTMLタグの属性値を置換します。下記の例では`$error`がない（エラーが発生しなかった）時は、`font`の`color`に`blue`をセットします。タグ記法とコメント記法は併用できます。

/resources/views/example.blocs.html  
2行目 `$error`がなければ、`font`の`color`を`blue`にする   
```html
<html>
<!-- data-attribute="color" data-val="blue" data-none=$error -->
<font color="red" data-val=$message>Message</font>
</html>
```

/routes/web.php
```php
Route::get("/blocs", function () {
    return view("example", [
		"error" => false,
		"message" => "No error has occurred."
    ]);
});
```

http://127.0.0.1:8000/blocs
```html
<html>
<font color="blue">No error has occurred.</font>
</html>
```

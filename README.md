<div align="center"><img src="logo.svg" width="400" /></div>

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
BLOCSは、LaravelのBladeをもっと便利にする拡張パッケージです。Bladeのテンプレートでは、本の一覧表示などの繰り返し処理を以下のように記述します。

```html
@foreach($books as $book)
    <tr>
        <td>{{ $book->id }}</td>
        <td>{{ $book->title }}</td>
        ...
        <td>
            @foreach($book->tags as $bookTag)
                <span>{{ $bookTag->name }}</span><br>
            @endforeach
        </td>
        ...
    </tr>
@endforeach
```

BLOCSを使うと、同様の処理を以下のように記述できます。テンプレートで指定したデータ属性`data-*`で、HTMLを動的に生成します。HTMLをくずさずに構造を活かして、シンプルに記述できるテンプレートエンジンを目指して、BLOCSを開発しています。

```html
<tr data-loop=$books>
    <td>{{ $book->id }}</td>
    <td>{{ $book->title }}</td>
    ...
    <td>
        <span data-loop=$book->tags data-assign=$bookTag>{{ $bookTag->name }}</br></span>
    </td>
    ...
</tr>
```

## 特徴
- HTMLをくずさない記述方法（タグ記法、コメント記法）

```html
<div class="error" data-exist=$error>{{ $message }}</div>
```

- テンプレートでバリデーションを指定

```html
<form method="post">
@csrf
<label for="name">名前</label>
<input type="text" id="name" name="name" data-filter="katakana" required />
<!-- data-form="name" data-validate="required" data-lang="必須入力です。" -->

@error("name") <div>{{ $message }}</div> @enderror
<input type="submit" />
</form>
```

- `select` `radio` `checkbox`の項目を動的に追加

/routes/web.php  
2行目 `Warning`を追加
```php
Route::get("/blocs", function () {
  \Blocs\Option::add("level", ["2" => "Warning"]);

  return view("example", [
    "level" => "2"
  ]);
});
```

/resources/views/example.blocs.html
```html
<html>
<form>
  <select name="level">
    <option value="">No error</option>
    <option value="1">Fatal error</option>
  </select>
</form>
</html>
```

http://127.0.0.1:8000/blocs
```html
<html>
<form>
  <select name="level">
    <option value="">No error</option>
    <option value="1">Fatal error</option>
    <option value="2" selected>Warning</option>
  </select>
</form>
</html>
```

# 導入方法
composerで導入してください。

```sh
laravel-app % composer require blocs/blocs    
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
BLOCSテンプレートのファイル名は`*.blocs.html`です。データ属性`data-*`は、HTMLタグに属性を追加するタグ記法と、コメントで記述するコメント記法の２つの記述方法があります。4種類のデータ属性をタグ記法とコメント記法で記述して、HTMLを動的に生成します。

## タグ記法
タグ記法は、HTMLタグにデータ属性を追加する記述方法です。開始タグに追加したデータ属性は、終了タグまで影響します。下記の例では、`$message`の値で`div`の間のコンテンツをすべて置換します。追加したデータ属性は、BLOCSが生成したHTMLでは削除されます。

/routes/web.php
```php
Route::get("/blocs", function () {
  return view("example", [
    "error" => true,
    "message" => "A fatal error has occurred."
  ]);
});
```

/resources/views/example.blocs.html  
2行目 `$error`があれば、`$message`を表示
```html
<html>
<div class="error" data-exist=$error data-val=$message>Message</div>
</html>
```

http://127.0.0.1:8000/blocs
```html
<html>
<div class="error">A fatal error has occurred.</div>
</html>
```

## コメント記法
他のテンプレートを読み込む時や、HTMLタグに属性を動的に追加する時に、コメント記法で記述します。データ属性`data-attribute`は、コメント記法の次にあるHTMLタグの属性値を置換します。下記の例では`$error`がない（エラーが発生しなかった）時は、`div`の`class`に`success`をセットします。タグ記法とコメント記法は併用できます。

/routes/web.php
```php
Route::get("/blocs", function () {
  return view("example", [
    "error" => false,
    "message" => "No error has occurred."
  ]);
});
```

/resources/views/example.blocs.html  
2行目 `$error`がなければ、`div`の`class`を`success`にする
```html
<html>
<!-- data-attribute="class" data-val="success" data-none=$error -->
<div class="error" data-val=$message>Message</div>
</html>
```

http://127.0.0.1:8000/blocs
```html
<html>
<div class="success">No error has occurred.</div>
</html>
```

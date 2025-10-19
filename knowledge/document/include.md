# 他のテンプレートを読み込む
## **data-include** の使い方
`data-include` を使うと、他のテンプレートファイルや `data-bloc` で定義されたブロックを読み込むことができます。ヘッダーやフッターなど、複数ページで共通して使う部品を一元管理する際に便利です。読み込むテンプレートのパスを、現在のテンプレートからの相対パスか、テンプレートのルートディレクトリからの絶対パスで指定します。`data-include` は コメント記法のみで使用できます。

### サンプルコード
以下の例では、`header.html` テンプレートが読み込まれた後に「テストです。」という本文が表示されます。
```html
<!-- data-include="header.html" -->
テストです。
```

## テンプレートに引数を渡す方法
`data-include` を使ってテンプレートを読み込む際に、変数（引数）を渡すことができます。これにより、テンプレートの内容を柔軟にカスタマイズできます。

以下の例では、`button_href` ブロックに `$buttonHref` という変数を渡し、リンク先を "./" に設定しています。
```html
<!-- data-bloc="button_href" -->
    <a :href=$buttonHref>Link</a>
<!-- data-endbloc -->

<!--
    data-include="button_href"
    $buttonHref="./"
-->
```

## テンプレートの自動読み込み（Auto Include）
`data-include` で指定したブロック名に対応するテンプレートファイルが、`resources/views/admin/autoinclude` フォルダに存在する場合、明示的に `data-include` を記述しなくても自動で読み込まれます。

以下のように指定すると、`button_href` の `_` より前の部分（この場合は `button`） に `.html` を付けたファイル名、つまり `button.html` が `resources/views/admin/autoinclude` に存在する場合、自動で読み込まれます。
```html
<!-- data-include="button_href" -->
```

自動読み込みされたテンプレートの中で、`data-bloc` で定義されていない部分（ブロック以外の部分）は、`<!-- data-include="auto" -->` を記述した場所に読み込まれます。この機能は、JavaScriptなどをページの一番下で読み込みたい場合などに便利です。

また、同じテンプレートを複数回 `data-include` で自動読み込みしても、実際に読み込まれるのは最初の1回だけです。これにより、重複を気にせず何度でも `data-include` を記述できます。以下の場合でも、`button.html` は1度だけ読み込まれます。
```html
<!--
    data-include="button_href"
    $buttonHref="./1.html"
-->

<!--
    data-include="button_href"
    $buttonHref="./2.html"
-->
```
複数のテンプレートで `<!-- data-include="jquery" -->` と記述していても、`resources/views/admin/autoinclude/jquery.html` は 1度だけしか読み込まれないため、jQueryの重複読み込みを防ぐことができます。

## テンプレートが定義されている時のみ読み込む方法
テンプレートが存在するかどうかを確認してから読み込みたい場合は、`data-exist` 属性を併用します。これにより、テンプレートが存在しない場合でもエラーを回避できます。以下の記述では、`footer` テンプレートが存在する場合のみ読み込まれます。
```html
<!-- data-include="footer" data-exist -->
```

## テンプレートのルートディレクトリを変更する方法
`app/Consts/Blocs.php` に定義されている `BLOCS_ROOT_DIR` 定数の値を変更することで、テンプレートのルートディレクトリを別のディレクトリに変更することができます。
```php
defined('BLOCS_ROOT_DIR') || define('BLOCS_ROOT_DIR', resource_path('views'));
```

## Auto Include のディレクトリを変更する方法
`app/Consts/Blocs.php` に定義されている `BLOCS_AUTOINCLUDE_DIR` 定数の値を変更することで、Auto Include のディレクトリを別のディレクトリに変更することができます。
```php
$GLOBALS['BLOCS_AUTOINCLUDE_DIR'] = resource_path('views/admin/autoinclude');
```

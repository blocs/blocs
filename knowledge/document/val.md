# テンプレートで変数の値を表示する
## **data-val** の使い方
**data-val** は、コントローラーから渡された変数の値をテンプレート上に表示するためのデータ属性です。この属性は、タグ記法とコメント記法の両方で使用できます。

### サンプルコード
タグ記法では、該当タグに挟まれる内容が、変数の値で置き換えられます。コメント記法では、コメント部分が変数の値で置き換えられます。
```html
<div data-val=$name>Name</div>
```

```html
<div data-val=$name>Name</div>
```

### 省略記法（簡略化された書き方）
表示する内容が変数の値だけの場合は、より短く **省略記法** で記述できます。
```html
<div $name></div>
```

```html
<div><!-- $name --></div>
```

### 出力時の処理について
`data-val` を使用すると、表示される値に以下の処理が自動的に行われます。
- メニュー項目の自動変換
- htmlspecialchars によるHTMLエスケープ

そのため、HTMLタグなどをそのまま表示したい場合は、`data-convert="raw"` を指定してください。
```html
<span><!-- $htmlSource data-convert="raw" --></span>
```

## メニュー項目の自動置換
BLOCSは、メニューのラベルを取得して、`data-val` でメニューのラベルに変換して表示します。テンプレートにメニュー項目がなくても、同一フォルダ内のテンプレートにメニュー項目があれば変換します。例えば、入力画面のメニューのラベルで確認画面の表示項目を変換することができます。変換せずに表示したい時は、`data-convert="raw"`を指定してください。

BLOCSでは、`data-val` を使って変数を表示する際に、対応するメニューのラベルに自動変換する機能があります。テンプレート内にメニュー項目がなくても、同一フォルダ内の他のテンプレートにメニュー項目が定義されていれば、自動的にラベルへ変換されます。たとえば、入力画面で選択された値を、確認画面ではラベルに変換して表示することができます。

```html
<select id="type" name="type">
<option value="company">法人のお客様</option>
<option value="private">個人のお客様</option>
<option value="other">その他</option>
</select>

<span><!-- $type --></span>
```

ラベル変換を行わず、変数の値をそのまま表示したい場合は、`data-convert="raw"` を指定してください。
```html
<span><!-- $type data-convert="raw" --></span>
```

## 複数選択されたメニュー項目の区切り文字を変更する方法
複数のメニュー項目を選択した際に、それらを表示する際の「区切り文字」を変更するには、`app/Consts/Blocs.php` の　`BLOCS_OPTION_SEPARATOR` の定数の値を変更します。以下のように定義されている箇所の値を、希望する区切り文字に変更してください。
```php
defined('BLOCS_OPTION_SEPARATOR') || define('BLOCS_OPTION_SEPARATOR', ', ');
```

## 回答例
- **user**
`$name` がセットされていない時に「名無し」と表示したい。
- **assistant**
`data-val` 属性は、指定された変数に値がある場合に、その値でタグの内容を置き換えます。変数に値がない場合は、タグ内の元のテキストがそのまま表示されます。
```html
<div $name>名無し</div>
```
- **assistant**
`$name` に値がある場合、その値が表示される。`$name` に値がない場合、「名無し」が表示される。
---
- **user**
タグ記法とコメント記法を併用できますか？
- **assistant**
同じ内容をタグ記法とコメント記法で同時に記述することはできません。特に `data-val` を使って、タグの表示内容とコメントの両方を制御しようとする記述は **誤り** です。以下のような記述は避けてください。タグの表示を制御したい時は、`data-exist`、`data-none`、`data-if`、`data-unless` を使います。

```html
<div data-val=$name><!-- data-val=$name --></div>
```
---
- **user**
`addOption` で追加したメニュー項目の値は `data-val` で自動的に変換されますか？
- **assistant**
はい。`addOption` で追加した項目も、他のメニュー項目と同様に `data-val` で自動的に変換されます。例えば、コントローラーで `addOption("type", ["foreign" => "外国のお客様"]);` を追加していれば、テンプレート内で `<!-- $type -->` と書くと、自動的に「外国のお客様」と表示されます。変換を行わずにvalue値（foreign）を表示したい場合は、`data-convert="raw"`を指定します
---

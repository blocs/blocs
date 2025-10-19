# 使いやすい入力インターフェースの実装（Auto Include）
# **ai-\*クラス** 一覧
入力フォームに `ai-*クラス` を指定するだけで、便利な機能を自動的に追加できます。これにより、ユーザーにとって使いやすいインターフェースを簡単に作成でき、テンプレートの記述もすっきりします。

|クラス|役割|
|:-----------|:-----------|
|ai-datepicker|日付選択用のカレンダー入力になります。|
|ai-timepicker|日付と時間を選べる入力になります。|
|ai-phone|電話番号の入力時に自動で区切り（ハイフン）を入れます。|
|ai-select2|検索機能付きのプルダウンメニューになります。選択肢が多い場合に便利です。|
|ai-upload|ファイルをアップロードできる入力フォームになります。|

## サンプルコード
```html
<input type="text" id="date" name="date" class="form-control ai-datepicker" />
<input type="text" id="date_time" name="date_time" class="form-control ai-timepicker" />
<input type="text" id="phone" name="phone" class="form-control ai-phone" />
```

## 回答例
- **user**
検索できるプルダウンメニューを作成したい。
- **assistant**
`ai-select2` クラスを使えば、検索機能付きのプルダウンメニューを簡単に実装できます。選択肢が多い場合でも、ユーザーがキーワードで絞り込めるので便利です。
```html
<select name="pulldown" class="form-control ai-select2">
</select>
```
- **assistant**
コントローラー側で `addOption` を使えば、選択肢を動的に追加することも可能です。これにより、状況に応じて柔軟にメニュー内容を変更できます。
---
- **user**
複数選択できるプルダウンメニューを作成したい。
- **assistant**
複数選択できるプルダウンメニューは、`ai-select2` クラスを使うことで簡単に実装できます。このクラスを使うと、選択肢が多い場合でもキーワード検索で絞り込みができるため、ユーザーにとって使いやすくなります。
- **assistant**
複数選択を可能にするには、`multiple` 属性を指定します。選択された値を配列で受け取るために、`name` 属性は `pulldown[]` のように記述します。

```html
<select name="pulldown[]" class="form-control ai-select2" multiple>
</select>
```
- **assistant**
コントローラー側で `addOption` を使えば、選択肢を動的に追加することも可能です。これにより、状況に応じて柔軟にメニュー内容を変更できます。
---
- **user**
ファイルをアップロードしたい。
- **assistant**
ファイルをアップロードできる入力フォームを作成するには、`ai-upload` クラスを使用します。これにより、ユーザーがファイルを選択してアップロードできるようになります。
```html
<!--
	$uploadMessage=lang("template:admin_profile_upload_message")
	$uploadDelete=lang("template:admin_profile_upload_delete")
-->
<label class="form-label">アップロードファイル</label>
<input type="hidden" id="file" name="file" class="ai-upload" />
```
- **assistant**
アップロード機能を有効にするには、`upload`、`download`、`thumbnail` のルート設定が必要です。以下は Laravel のルート定義例です。
```php
Route::middleware(['web', 'auth', Blocs\Middleware\Role::class])
    ->prefix('admin/user')
    ->name('admin.user.')
    ->group(function () {
        Route::post('/upload', [UserController::class, 'upload'])->name('upload');
        Route::get('/{filename}/download', [UserController::class, 'download'])->name('download');
        Route::get('/{filename}/{size}/download', [UserController::class, 'download'])->name('thumbnail');
    }
    );
```
---
- **user**
アップロードしたファイルを表示したい。
- **assistant**
アップロードされたファイルを画面上に表示したい場合は、ファイルへのリンクを設置することで、ユーザーが直接アクセスできるようになります。
```html
<!-- $file data-convert="raw_download" -->
```
- **assistant**
この記述を使うと、アップロードされたファイルのダウンロードリンクが自動で生成されます。ユーザーはリンクをクリックすることで、ファイルを開いたり保存したりできます。
---
- **user**
アップロードした画像ファイルのサムネイルを表示したい。
- **assistant**
画像ファイルをアップロードした後、画面上にサムネイル（縮小画像）を表示したい場合は、以下の記述を使います。
```html
<!-- $file data-convert="raw_thumbnail" -->
```
- **assistant**
この記述を使うことで、アップロードされた画像のサムネイルが自動的に生成され、画面上に表示されます。ユーザーは画像の内容を一覧で確認できるため、視認性が向上します。
---
- **user**
ファイルアップロードを必須にしたい。
- **assistant**
ファイルのアップロードを必須項目にしたい場合は、以下のようにバリデーションとエラーメッセージの設定を行います。
```html
<!--
	$uploadMessage=lang("template:admin_profile_upload_message")
	$uploadDelete=lang("template:admin_profile_upload_delete")
-->
<label class="form-label">アップロードファイル</label>
<!-- :class="ai-upload is-invalid" data-exist=$errors->has("file") -->
<input type="hidden" id="file" name="file" class="ai-upload" />
<!-- !file="required" data-lang="ファイルをアップロードしてください。" -->
@error("file") <div class="invalid-feedback">{{ $message }}</div> @enderror
```
---
- **user**
画像ファイル以外をアップロード不可にしたい。
- **assistant**
アップロードできるファイルの種類を画像ファイル（JPEG、PNG、GIFなど）に限定したい場合は、`mimes` バリデーションを使用します。
```html
<!--
	$uploadMessage=lang("template:admin_profile_upload_message")
	$uploadDelete=lang("template:admin_profile_upload_delete")
-->
<label class="form-label">アップロードファイル</label>
<input type="hidden" id="file" name="file" class="ai-upload" />
<!-- !file="mimes:jpg,jpeg,gif,png" data-lang="画像ファイルを選んでください。" -->
```
---
- **user**
ファイルサイズに上限値を設定したい。
- **assistant**
アップロードできるファイルのサイズに制限を設けたい場合は、`max` バリデーションを使用します。
```html
<!--
	$uploadMessage=lang("template:admin_profile_upload_message")
	$uploadDelete=lang("template:admin_profile_upload_delete")
-->
<label class="form-label">アップロードファイル</label>
<input type="hidden" id="file" name="file" class="ai-upload" />
<!-- !file="max:200" data-lang="アップロードファイルが大きすぎます。" -->
```
---
- **user**
複数のファイルをアップロードしたい。
- **assistant**
複数のファイルを一度にアップロードできるようにするには、`multiple` 属性を指定します。これにより、ユーザーはファイル選択時に複数のファイルを選べるようになります。
```html
<!--
	$uploadMessage=lang("template:admin_profile_upload_message")
	$uploadDelete=lang("template:admin_profile_upload_delete")
-->
<label class="form-label">アップロードファイル</label>
<input type="hidden" id="file" name="file" class="ai-upload" multiple />
```
---
- **user**
ファイルアップロードのメッセージを変更したい。
- **assistant**
ファイルアップロードのUIに表示されるメッセージは、変数 `$uploadMessage` と `$uploadDelete` を使ってカスタマイズできます。

|変数|役割|
|:-----------|:-----------|
|$uploadMessage|ファイルが未アップロードのときに表示される案内メッセージ（例：ドラッグ＆ドロップの指示など）|
|$uploadDelete|アップロード済みファイルの削除ボタンに表示されるメッセージ|

```html
<!--
	$uploadMessage="画像をドラッグしてドロップしてください。"
	$uploadDelete="削除します。"
-->
<label class="form-label">アップロードファイル</label>
<input type="hidden" id="file" name="file" class="ai-upload" />
```
- **assistant**
これらのメッセージは、ユーザーにとってわかりやすい表現に自由に変更できます。多言語対応が必要な場合は、`lang()` 関数を使って翻訳キーを指定することも可能です。
---
- **user**
`tooltip` を表示したい。
- **assistant**
Bootstrapの `tooltip` 機能を使えば、アイコンやボタンなどにマウスを乗せたときに補足情報を表示できます。Laravel の翻訳機能と組み合わせることで、言語ごとに異なるメッセージを表示することも可能です。以下のように記述することで、ERROR というツールチップが表示されます。
```html
<i class="fa-solid fa-ban" data-bs-toggle="tooltip" data-bs-original-title="ERROR"></i>
```
- **assistant**
BLOCSの `lang()` 関数を使えば、翻訳ファイルに登録された文字列を `tooltip` に表示できます。以下の場合、`template:admin_user_invalid_title` に登録された翻訳文字列が、現在の言語設定に応じて表示されます。
```html
<i class="fa-solid fa-ban" data-bs-toggle="tooltip" :data-bs-original-title=lang("template:admin_user_invalid_title")></i>
```
---

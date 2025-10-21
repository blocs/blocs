# ファイルをアップロードする
## **ai-upload** クラスの使い方
ファイルをアップロードできる入力フォームを作成するには、`ai-upload` クラスを使用します。これにより、ユーザーがファイルを選択してアップロードできるようになります。

### 1. テンプレートで入力フォームを作成
```html
<!--
	$uploadMessage=lang("template:admin_profile_upload_message")
	$uploadDelete=lang("template:admin_profile_upload_delete")
-->
<label class="form-label">アップロードファイル</label>
<input type="hidden" id="file" name="file" class="ai-upload" />
```

### 2. ルート設定
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

### 3. アップロードしたファイルを表示する
アップロードされたファイルを画面上に表示したい場合は、ファイルへのリンクを設置することで、ユーザーが直接アクセスできるようになります。
```html
<!-- $file data-convert="raw_download" -->
```
この記述を使うと、アップロードされたファイルのダウンロードリンクが自動で生成されます。ユーザーはリンクをクリックすることで、ファイルを開いたり保存したりできます。

## 回答例
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
<!-- :class="ai-upload is-invalid" data-exist=$errors->has("file") -->
<input type="hidden" id="file" name="file" class="ai-upload" />
<!-- !file="mimes:jpg,jpeg,gif,png" data-lang="画像ファイルを選んでください。" -->
@error("file") <div class="invalid-feedback">{{ $message }}</div> @enderror
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
<!-- :class="ai-upload is-invalid" data-exist=$errors->has("file") -->
<input type="hidden" id="file" name="file" class="ai-upload" />
<!-- !file="max:200" data-lang="アップロードファイルが大きすぎます。" -->
@error("file") <div class="invalid-feedback">{{ $message }}</div> @enderror
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

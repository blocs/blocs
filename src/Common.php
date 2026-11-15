<?php

namespace Blocs;

use Illuminate\Support\Facades\Route;

class Common
{
    private static $path;

    private static $config;

    /**
     * 設定ファイルの書式バージョン。書式を変えたら上げる（古い設定ファイルは作り直される）
     */
    private const CONFIG_VERSION = 2;

    /**
     * 設定ファイルの読み込み結果（動的な選択肢を取り込む前）
     *
     * @var array<string, array{mtime: int|false, settled: bool, config: array}>
     */
    private static array $configCache = [];

    /**
     * 動的な選択肢を取り込んだ設定
     *
     * @var array<string, array{revision: int, config: array}>
     */
    private static array $mergedCache = [];

    /**
     * 再構築中のテンプレート（再構築の無限ループを防ぐ）
     *
     * @var array<string, bool>
     */
    private static array $rebuilding = [];

    /**
     * 直近に読み込んだテンプレートのパスと設定を破棄する。
     * 常駐ワーカー（Octane）でリクエストをまたいで残らないよう、リクエスト開始時に呼ぶ。
     */
    public static function flush(): void
    {
        self::$path = null;
        self::$config = null;
        self::$configCache = [];
        self::$mergedCache = [];
        self::$rebuilding = [];
    }

    // テンプレート名から対象ファイルのフルパスを取得する
    public static function getPath($name)
    {
        if (defined('BLOCS_NO_LARAVEL')) {
            return self::normalizeRealPath($name);
        }

        $viewPaths = config('view.paths');
        foreach ($viewPaths as $path) {
            $resolvedPath = self::locateTemplatePath($path, $name);
            if ($resolvedPath !== '') {
                return $resolvedPath;
            }
        }

        return '';
    }

    /**
     * data-valの標準コンバーターとして値を整形する（rawを指定すると適用されない）
     *
     * @param  mixed  $str
     * @param  string|int|null  $key  選択肢のラベルへ置き換えるフォーム名
     * @param  bool  $convertNewline  改行を <br /> に変換するか（属性値では false）
     * @param  string|null  $templatePath  表示中のテンプレート。指定するとそのテンプレートの選択肢を使う
     */
    public static function convertDefault($str, $key = null, $convertNewline = true, $templatePath = null)
    {
        $menuLabel = self::buildMenuLabelMap($key, $templatePath);
        if (! empty($menuLabel)) {
            // 選択項目をラベルで置き換える
            $values = is_array($str) ? $str : explode("\t", (string) $str);

            $query = '';
            foreach ($values as $buff) {
                if (! (is_string($buff) || is_int($buff)) || ! isset($menuLabel[$buff])) {
                    continue;
                }

                strlen($query) && $query .= BLOCS_OPTION_SEPARATOR;

                $label = (string) $menuLabel[$buff]['label'];

                // Option::add() で追加したラベルはアプリのデータなので、テンプレートとして解釈しない
                if ($menuLabel[$buff]['dynamic'] || ! self::menuLabelContainsTemplateMarkup($label)) {
                    $query .= htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
                } else {
                    isset($blocsCompiler) || $blocsCompiler = new Compiler\BlocsCompiler;
                    $query .= $blocsCompiler->render($label);
                }
            }

            return $query;
        }

        if ($str === null || is_array($str) || is_object($str)) {
            return '';
        }

        // 文字列のエスケープを実施する
        $escaped = htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');

        return $convertNewline ? nl2br($escaped) : $escaped;
    }

    // selectなどのフォーム部品にchecked、selectedを付与する
    public static function addChecked($str, $value, $checked, $checkFlg)
    {
        $normalized = self::normalizeSelectableValue($str);

        if (! isset($normalized)) {
            // 未入力の場合の既定動作
            if ($checked) {
                echo ' '.$checkFlg;
            }

            return;
        }

        // 選択項目にcheckedを付与する
        $optionValue = self::canonicalSelectableValue($value);
        foreach ($normalized as $selectedValue) {
            if (self::canonicalSelectableValue($selectedValue) === $optionValue) {
                echo ' '.$checkFlg;

                return;
            }
        }
    }

    /**
     * 選択値を比較用の文字列にする。
     * "1" と "01"、"10" と "1e1" のようなコード値を同じとみなさないよう、文字列として比較する。
     * ただし decimal 型の "1.00" と "1" のように、通常の10進表記で値が同じものは同じとみなす。
     */
    private static function canonicalSelectableValue($value): ?string
    {
        if (! is_scalar($value) && $value !== null) {
            return null;
        }

        $value = (string) $value;

        if (preg_match('/^(-?(?:0|[1-9][0-9]*))\.([0-9]+)$/', $value, $matchList)) {
            $fraction = rtrim($matchList[2], '0');

            return $fraction === '' ? $matchList[1] : $matchList[1].'.'.$fraction;
        }

        return $value;
    }

    // 設定ファイルを読み込み設定内容をキャッシュする
    public static function readConfig($path = null)
    {
        if (isset($path)) {
            self::$path = $path;
        } else {
            $path = self::$path;
        }
        if (empty(self::$path)) {
            return [];
        }
        $path = self::normalizeRealPath($path);
        if ($path === '') {
            // 存在しないパスでは、直前のテンプレートの設定を残さない
            self::$config = null;

            return [];
        }

        $configPath = self::getConfigPath(dirname($path));
        $config = self::loadRawConfig($configPath, true);

        // 設定ファイルが無い・壊れている・このテンプレートの設定がまだ無い場合は作り直す
        // （同じディレクトリの別テンプレートだけがコンパイル済みでも、バリデーションを取りこぼさない）
        // ディレクトリなど、テンプレートファイル以外はコンパイルできないので作り直さない
        if (is_file($path) && ! isset($config['timestamp'][$path])) {
            return self::rebuildConfig($path);
        }

        self::$config = $config === null ? null : self::mergeDynamicOptions($configPath, $config);

        return self::$config ?? [];
    }

    /**
     * 設定ファイルを書き込み直近の情報を反映する
     *
     * @param  int|null  $timestamp  コンパイル結果を書き出した時点の時刻（省略時は現在時刻）。
     *                               コンパイル結果を書き出していない場合は 0 を渡す（次回の表示で必ず再コンパイルさせる）
     */
    public static function writeConfig($path, $blocsConfig, ?int $timestamp = null)
    {
        $path = self::normalizeRealPath($path);
        $configPath = self::getConfigPath(dirname($path));

        self::ensureCacheDirectory(! defined('BLOCS_NO_LARAVEL'));

        // ロック用の別ファイルを作らず、設定ファイル自身をロック対象にする
        try {
            $lockHandle = @fopen($configPath, 'c+');
        } catch (\Throwable) {
            $lockHandle = false;
        }
        if ($lockHandle === false) {
            throw self::cacheWriteException();
        }

        flock($lockHandle, LOCK_EX);
        try {
            self::writeConfigLocked($path, $configPath, $blocsConfig, $lockHandle, $timestamp ?? time());
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }

        unset(self::$configCache[$configPath], self::$mergedCache[$configPath]);

        // readConfig() は再生成のため writeConfig() を呼び戻すことがあるので、ロックの外で実行する
        return self::readConfig($path);
    }

    /**
     * テンプレートをコンパイルし、設定ファイルを更新する
     *
     * 設定の timestamp は「コンパイル結果がこの時刻のテンプレートを反映している」という印で、
     * include されたテンプレートの更新確認（includesAreStale）に使う。そのため、
     * - コンパイル結果を書き出せた後で設定を書く（書き出しに失敗したら、古い結果を最新とみなさない）
     * - コンパイル結果を書き出さない場合（設定だけ作り直す場合）は 0 にして、次回の表示で再コンパイルさせる
     *
     * @param  callable(string): void|null  $storeCompiled  コンパイル結果を書き出す処理
     */
    public static function compileTemplate(string $path, ?callable $storeCompiled = null): void
    {
        $startedAt = time();

        $blocsCompiler = new Compiler\BlocsCompiler;
        $compiledContents = $blocsCompiler->compile($path);

        $storeCompiled === null || $storeCompiled($compiledContents);

        self::writeConfig($path, $blocsCompiler->getConfig(), $storeCompiled === null ? 0 : $startedAt);
    }

    /**
     * 設定 JSON を共有ロック付きで読む（ファイルが無い・壊れている場合は null）
     */
    public static function loadConfigFile(string $configPath): ?array
    {
        if (! is_file($configPath)) {
            return null;
        }

        return self::decodeConfig(self::readConfigFileShared($configPath));
    }

    public static function cacheWriteException(): \RuntimeException
    {
        return new \RuntimeException('B001: Can not write cache file into directory');
    }

    private static function writeConfigLocked(string $path, string $configPath, $blocsConfig, $lockHandle, int $timestamp): void
    {
        rewind($lockHandle);

        // 壊れた設定や、書式の違う古い設定は引き継がない
        $config = self::currentVersionConfig(self::decodeConfig(stream_get_contents($lockHandle))) ?? [];
        $config['version'] = self::CONFIG_VERSION;

        // ファイルアップロードのバリデーション設定を整理する
        // テンプレートごとに保持し、テンプレートが無くなったら一緒に消えるようにする
        $validateUpload = self::extractUploadValidation($blocsConfig);
        $config = self::syncConfigSection($config, 'uploadByPath', $path, $validateUpload);

        $config = self::syncConfigSection($config, 'include', $path, $blocsConfig->include);
        $config['timestamp'][$path] = $timestamp;
        $config = self::syncConfigSection($config, 'filter', $path, $blocsConfig->filter);
        $config = self::syncConfigSection($config, 'option', $path, $blocsConfig->option);
        $config = self::syncConfigSection($config, 'validate', $path, $blocsConfig->validate);
        $config = self::syncConfigSection($config, 'message', $path, $blocsConfig->message);
        $config = self::syncConfigSection($config, 'label', $path, $blocsConfig->label);

        $config = self::pruneMissingTemplates($config);

        $config['upload'] = [];
        foreach ($config['uploadByPath'] as $uploadList) {
            $config['upload'] = array_merge($config['upload'], $uploadList);
        }

        $config['menu'] = self::buildMenu($config['option']);

        $encoded = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false || ftruncate($lockHandle, 0) === false) {
            throw self::cacheWriteException();
        }

        $payload = $encoded."\n";
        rewind($lockHandle);
        $written = fwrite($lockHandle, $payload);
        if ($written === false || $written < strlen($payload) || fflush($lockHandle) === false) {
            throw self::cacheWriteException();
        }

        chmod($configPath, 0644);
    }

    /**
     * 各テンプレートのOptionをフォーム名ごとに集約してmenuを生成する（同じvalueは最初のものを採用する）
     */
    private static function buildMenu(array $optionSection): array
    {
        $menu = [];
        $existValueList = [];
        foreach ($optionSection as $configOption) {
            foreach ($configOption as $formName => $optionList) {
                foreach ($optionList as $option) {
                    if (! isset($option['value']) || isset($existValueList[$formName][$option['value']])) {
                        continue;
                    }

                    $menu[$formName][] = $option;
                    $existValueList[$formName][$option['value']] = true;
                }
            }
        }

        return $menu;
    }

    public static function getConfigPath($path)
    {
        return BLOCS_CACHE_DIR.'/'.md5($path).'.json';
    }

    /**
     * include されたテンプレートが、キャッシュ生成時より新しければ true
     */
    public static function includesAreStale(string $path, array $config): bool
    {
        // 設定は正規化した実パスで保持している（シンボリックリンク経由のパスでも一致させる）
        $normalizedPath = self::normalizeRealPath($path);
        $normalizedPath === '' || $path = $normalizedPath;

        if (! isset($config['include'][$path]) || ! is_array($config['include'][$path])) {
            return true;
        }

        $timestamp = $config['timestamp'][$path] ?? null;
        if (! isset($timestamp)) {
            return true;
        }

        foreach ($config['include'][$path] as $includeFile) {
            if (! file_exists($includeFile) || filemtime($includeFile) > $timestamp) {
                return true;
            }
        }

        return false;
    }

    /**
     * 設定ファイルの内容を配列へ復元する（空・壊れている場合は null）
     *
     * @param  string|false  $contents
     */
    private static function decodeConfig($contents): ?array
    {
        if ($contents === false || $contents === '') {
            return null;
        }

        $config = json_decode($contents, true);

        return is_array($config) ? $config : null;
    }

    /**
     * 現在の書式の設定だけを通す（古い書式なら null）
     */
    private static function currentVersionConfig(?array $config): ?array
    {
        return ($config['version'] ?? null) === self::CONFIG_VERSION ? $config : null;
    }

    /**
     * 共有ロックを取得して設定ファイルを読む（書き込み完了まで待つ）
     *
     * @return string|false
     */
    private static function readConfigFileShared(string $configPath)
    {
        $handle = fopen($configPath, 'r');
        if ($handle === false) {
            return false;
        }

        flock($handle, LOCK_SH);
        try {
            $contents = stream_get_contents($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $contents;
    }

    private static function rebuildConfig(string $path): array
    {
        if (isset(self::$rebuilding[$path])) {
            // 書き込んだ直後の設定を読めない（キャッシュディレクトリに問題がある）
            throw self::cacheWriteException();
        }
        self::$rebuilding[$path] = true;

        try {
            // 設定ファイルが見つからない、または不備があるためコンパイルして作り直す
            self::compileTemplate($path);

            return self::readConfig($path);
        } finally {
            unset(self::$rebuilding[$path]);
        }
    }

    /**
     * キャッシュディレクトリに書き込めることを確認する。書き込めない場合は B001。
     * Laravel では view.compiled のディレクトリが無ければ作る（Blade と同じ）。
     * Laravel を使わない場合（\Blocs\View）は、指定されたディレクトリを勝手には作らない。
     *
     * @param  bool  $create  ディレクトリが無ければ作るか
     */
    public static function ensureCacheDirectory(bool $create = false): void
    {
        if ($create && ! is_dir(BLOCS_CACHE_DIR)) {
            try {
                // エラーを例外に変換する環境でも B001 として知らせる
                @mkdir(BLOCS_CACHE_DIR, 0777, true);
            } catch (\Throwable) {
            }
        }

        if (! is_dir(BLOCS_CACHE_DIR) || ! is_writable(BLOCS_CACHE_DIR)) {
            throw self::cacheWriteException();
        }
    }

    /**
     * 表示中のテンプレートの設定を返す（直近に読み込んだ設定は差し替えない）
     */
    private static function configFor(string $templatePath): ?array
    {
        $configPath = self::getConfigPath(dirname($templatePath));
        $config = self::loadRawConfig($configPath, false);

        return $config === null ? null : self::mergeDynamicOptions($configPath, $config);
    }

    /**
     * 設定ファイルを読む。$validate が true なら、更新されていた場合に読み直す。
     */
    private static function loadRawConfig(string $configPath, bool $validate): ?array
    {
        if (isset(self::$configCache[$configPath])) {
            if (! $validate) {
                return self::$configCache[$configPath]['config'];
            }

            // mtime は秒精度なので、読み込んだのと同じ秒に書かれた設定は、その後の書き換えを検知できない
            // そのような設定は信用せず読み直す
            clearstatcache(true, $configPath);
            if (self::$configCache[$configPath]['settled'] && self::modifiedTime($configPath) === self::$configCache[$configPath]['mtime']) {
                return self::$configCache[$configPath]['config'];
            }
        }

        unset(self::$configCache[$configPath], self::$mergedCache[$configPath]);

        if (! is_file($configPath)) {
            return null;
        }

        clearstatcache(true, $configPath);
        $mtime = self::modifiedTime($configPath);
        $config = self::currentVersionConfig(self::loadConfigFile($configPath));
        if ($config === null) {
            return null;
        }

        self::$configCache[$configPath] = ['mtime' => $mtime, 'settled' => $mtime !== false && $mtime < time(), 'config' => $config];

        return $config;
    }

    /**
     * @return int|false
     */
    private static function modifiedTime(string $path)
    {
        return is_file($path) ? filemtime($path) : false;
    }

    /**
     * Option::add() で登録した動的な選択肢を取り込む
     */
    private static function mergeDynamicOptions(string $configPath, array $config): array
    {
        $revision = Option::revision();
        $cached = self::$mergedCache[$configPath] ?? null;
        if ($cached !== null && $cached['revision'] === $revision) {
            return $cached['config'];
        }

        $merged = $config;
        foreach (Option::append() as $menuName => $menu) {
            isset($merged['menu'][$menuName]) || $merged['menu'][$menuName] = [];
            foreach ($menu as $entry) {
                // テンプレート由来でないラベルは、テンプレートとして解釈させない
                Option::isTemplateLabel($menuName, $entry['value']) || $entry['dynamic'] = true;
                $merged['menu'][$menuName][] = $entry;
            }
        }

        self::$mergedCache[$configPath] = ['revision' => $revision, 'config' => $merged];

        return $merged;
    }

    /**
     * 無くなったテンプレートの設定を取り除く
     */
    private static function pruneMissingTemplates(array $config): array
    {
        foreach (['include', 'timestamp', 'filter', 'option', 'validate', 'message', 'label', 'uploadByPath'] as $section) {
            foreach (array_keys($config[$section] ?? []) as $templatePath) {
                if (! is_file($templatePath)) {
                    unset($config[$section][$templatePath]);
                }
            }
        }

        return $config;
    }

    private static function syncConfigSection($config, $configName, $path, $blocsConfig)
    {
        isset($config[$configName]) || $config[$configName] = [];
        if (empty($blocsConfig)) {
            unset($config[$configName][$path]);
        } else {
            $config[$configName][$path] = $blocsConfig;
        }

        return $config;
    }

    public static function routePrefix()
    {
        $currentName = Route::currentRouteName();

        if (empty($currentName)) {
            return $currentName;
        }

        // ルート名の最後の要素を除いたもの
        return implode('.', array_slice(explode('.', $currentName), 0, -1));
    }

    /**
     * 実パスを「/」区切りで返す（存在しなければ空文字列）
     */
    public static function normalizeRealPath($path)
    {
        $realPath = realpath($path);

        if ($realPath === false) {
            return '';
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $realPath);
    }

    private static function locateTemplatePath($basePath, $name)
    {
        $formattedName = str_replace('.', '/', $name);

        $fileCandidate = $basePath.'/'.$formattedName.'.blocs.html';
        $normalizedFile = self::normalizeRealPath($fileCandidate);
        if ($normalizedFile !== '' && is_file($normalizedFile)) {
            return $normalizedFile;
        }

        $directoryCandidate = $basePath.'/'.$formattedName;
        $normalizedDirectory = self::normalizeRealPath($directoryCandidate);
        if ($normalizedDirectory !== '' && is_dir($normalizedDirectory)) {
            return $normalizedDirectory;
        }

        return '';
    }

    private static function menuLabelContainsTemplateMarkup(string $label): bool
    {
        return (bool) preg_match(
            '/\bdata-(?:val|exist|none|if|unless|loop|include|assign|attribute|convert|prefix|postfix|lang|notice|form|validate|filter|repeat|bloc|chdir|end(?:exist|none|if|unless|loop|repeat|bloc))\b/',
            $label
        );
    }

    /**
     * @return array<int|string, array{label: mixed, dynamic: bool}>
     */
    private static function buildMenuLabelMap($key, $templatePath = null)
    {
        if (! is_string($key) && ! is_int($key)) {
            return [];
        }

        $config = null;
        if (is_string($templatePath) && $templatePath !== '') {
            $config = self::configFor($templatePath);
        }
        $config ??= self::$config;

        if (empty($config['menu'][$key]) || ! is_array($config['menu'][$key])) {
            return [];
        }

        $menuLabel = [];
        foreach ($config['menu'][$key] as $menu) {
            if (! isset($menu['value']) || ! strlen((string) $menu['value'])) {
                continue;
            }

            $menuLabel[$menu['value']] = [
                'label' => $menu['label'] ?? '',
                'dynamic' => ! empty($menu['dynamic']),
            ];
        }

        return $menuLabel;
    }

    private static function normalizeSelectableValue($value)
    {
        if (is_array($value)) {
            return count($value) ? $value : null;
        }

        if (! isset($value) || ! strlen($value)) {
            return null;
        }

        return explode("\t", $value);
    }

    private static function extractUploadValidation($blocsConfig)
    {
        $validateUpload = [];
        foreach ($blocsConfig->upload as $formName) {
            // ai-upload は validate 無しでも upload に登録する（未宣言フィールド拒否と区別するため）
            $validateUpload[$formName] = [];
            $matchedValidate = self::findValidateKey($blocsConfig->validate, $formName);

            if (isset($blocsConfig->validate[$matchedValidate])) {
                $validateUpload[$formName]['validate'] = $blocsConfig->validate[$matchedValidate];
                unset($blocsConfig->validate[$matchedValidate]);

                // requiredだけはhidden側をチェックする挙動を維持する
                if (in_array('required', $validateUpload[$formName]['validate'])) {
                    $blocsConfig->validate[$matchedValidate] = ['required'];
                }
            }
            if (isset($blocsConfig->message[$matchedValidate])) {
                $validateUpload[$formName]['message'] = $blocsConfig->message[$matchedValidate];
                unset($blocsConfig->message[$matchedValidate]);

                // requiredだけはhidden側をチェックする挙動を維持する
                if (isset($validateUpload[$formName]['message']['required'])) {
                    $blocsConfig->message[$matchedValidate]['required'] = $validateUpload[$formName]['message']['required'];
                }
            }
        }

        return $validateUpload;
    }

    /**
     * フォーム名に対応するバリデーションのキーを探す（ループ内の項目は "配列名.*.フォーム名"）
     * 戻り値は必ず配列キーとして使われるため、見つからない場合もフォーム名を返す
     */
    private static function findValidateKey($validateConfig, $formName)
    {
        if (! is_array($validateConfig) || ! strlen($formName)) {
            return $formName;
        }

        foreach (array_keys($validateConfig) as $key) {
            if ($key === $formName || str_ends_with((string) $key, '.'.$formName)) {
                return $key;
            }
        }

        return $formName;
    }
}

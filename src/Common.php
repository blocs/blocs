<?php

namespace Blocs;

use Illuminate\Support\Facades\Route;

class Common
{
    private static $path;

    private static $config;

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

    // data-valの標準コンバーターとして値を整形する
    // rawを指定すると適用されない
    public static function convertDefault($str, $key = null)
    {
        if (! empty(self::$config['menu'][$key])) {
            // 選択項目をラベルで置き換える
            $values = is_array($str) ? $str : explode("\t", (string) $str);

            $menuLabel = self::buildMenuLabelMap($key);

            if (empty($key) || empty(self::$config['menu'][$key])) {
                return '';
            }

            $query = '';
            foreach ($values as $buff) {
                if (! isset($menuLabel[$buff])) {
                    continue;
                }

                strlen($query) && $query .= BLOCS_OPTION_SEPARATOR;

                if (strpos($menuLabel[$buff], 'data-') === false) {
                    $query .= $menuLabel[$buff];
                } else {
                    isset($blocsCompiler) || $blocsCompiler = new Compiler\BlocsCompiler;
                    $query .= $blocsCompiler->render($menuLabel[$buff]);
                }
            }

            return $query;
        }

        // 文字列のエスケープを実施する
        $escaped = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        $escaped = nl2br($escaped);

        return $escaped;
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
        if (in_array($value, $normalized)) {
            echo ' '.$checkFlg;
        }
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

        $configPath = self::getConfigPath(dirname($path));
        if (! is_file($configPath)) {
            // 設定ファイルが見つからないためコンパイルを実行する
            $blocsCompiler = new Compiler\BlocsCompiler;
            $blocsCompiler->compile($path);

            // 設定ファイルを作成する
            $blocsConfig = $blocsCompiler->getConfig();

            return self::writeConfig($path, $blocsConfig);
        }

        self::$config = json_decode(file_get_contents($configPath), true);

        // 動的メニューの取り込みを実施する
        $appendOption = Option::append();
        foreach ($appendOption as $menuName => $menu) {
            isset(self::$config['menu'][$menuName]) || self::$config['menu'][$menuName] = [];
            self::$config['menu'][$menuName] = array_merge(self::$config['menu'][$menuName], $menu);
        }

        return self::$config;
    }

    // 設定ファイルを書き込み直近の情報を反映する
    public static function writeConfig($path, $blocsConfig)
    {
        $path = self::normalizeRealPath($path);
        $configPath = self::getConfigPath(dirname($path));
        if (is_file($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
        } else {
            // 設定ファイルが見つからない場合は新規作成する
            $config = [];
        }

        // ファイルアップロードのバリデーション設定を整理する
        $validateUpload = self::extractUploadValidation($blocsConfig);
        isset($config['upload']) || $config['upload'] = [];
        $config['upload'] = array_merge($config['upload'], $validateUpload);

        $config = self::syncConfigSection($config, 'include', $path, $blocsConfig->include);
        $config = self::syncConfigSection($config, 'timestamp', $path, time());
        $config = self::syncConfigSection($config, 'filter', $path, $blocsConfig->filter);
        $config = self::syncConfigSection($config, 'option', $path, $blocsConfig->option);
        $config = self::syncConfigSection($config, 'validate', $path, $blocsConfig->validate);
        $config = self::syncConfigSection($config, 'message', $path, $blocsConfig->message);
        $config = self::syncConfigSection($config, 'label', $path, $blocsConfig->label);

        // Optionをフォーム名ごとに集約してmenuを生成する
        $existValueList = [];
        $config['menu'] = [];
        foreach ($config['option'] as $path => $configOption) {
            foreach ($configOption as $formName => $optionList) {
                foreach ($optionList as $option) {
                    if (! isset($option['value'])) {
                        continue;
                    }
                    if (isset($existValueList[$formName][$option['value']])) {
                        // valueが重複している場合はスキップする
                        continue;
                    }

                    isset($config['menu'][$formName]) || $config['menu'][$formName] = [];
                    $config['menu'][$formName][] = $option;

                    $existValueList[$formName][$option['value']] = true;
                }
            }
        }

        // 設定ファイルはディレクトリごとに作成する
        file_put_contents($configPath, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n") && chmod($configPath, 0666);

        // 設定ファイルを読み込みキャッシュを更新する
        return self::readConfig($path);
    }

    public static function getConfigPath($path)
    {
        return BLOCS_CACHE_DIR.'/'.md5($path).'.json';
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

        $currentNameList = explode('.', $currentName);
        array_pop($currentNameList);
        $currentPrefix = implode('.', $currentNameList);

        return $currentPrefix;
    }

    private static function normalizeRealPath($path)
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

    private static function buildMenuLabelMap($key)
    {
        $menuLabel = [];
        foreach (self::$config['menu'][$key] as $menu) {
            if (! strlen($menu['value'])) {
                continue;
            }

            $menuLabel[$menu['value']] = $menu['label'];
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

        $normalized = explode("\t", $value);

        return $normalized;
    }

    private static function extractUploadValidation($blocsConfig)
    {
        $validateUpload = [];
        foreach ($blocsConfig->upload as $formName) {
            unset($validateUpload[$formName]);
            $matchedValidate = self::pullValidateByFormName($blocsConfig->validate, $formName);

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

    private static function pullValidateByFormName(&$validateConfig, $formName)
    {
        if (! is_array($validateConfig) || ! strlen($formName)) {
            return [];
        }

        $pulled = [];
        $suffix = '.'.$formName;
        $suffixLength = strlen($suffix);

        foreach ($validateConfig as $key => $validate) {
            $isExactMatch = $key === $formName;
            $isSuffixMatch = $suffixLength > 0 && substr($key, -$suffixLength) === $suffix;

            if (! $isExactMatch && ! $isSuffixMatch) {
                continue;
            }

            return $key;
        }

        return $formName;
    }
}

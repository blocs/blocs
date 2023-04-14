<?php

namespace Blocs;

class Common
{
    private static $path;
    private static $config;

    // テンプレートのフルパスを取得
    public static function getPath($name)
    {
        if (!function_exists('config')) {
            return realpath($name);
        }

        $viewPaths = config('view.paths');
        foreach ($viewPaths as $path) {
            $realPath = realpath($path.'/'.str_replace('.', '/', $name).'.blocs.html');
            if ($realPath && is_file($realPath)) {
                return $realPath;
            }

            $realPath = realpath($path.'/'.str_replace('.', '/', $name));
            if ($realPath && is_dir($realPath)) {
                return $realPath;
            }
        }

        return '';
    }

    // data-valの標準コンバーター
    // rawを指定すると適用されない
    public static function convertDefault($str, $key = null)
    {
        if (!empty(self::$config['menu'][$key])) {
            // 選択項目をラベルで置き換え
            is_array($str) || $str = explode("\t", $str);

            $menuLabel = [];
            foreach (self::$config['menu'][$key] as $buff) {
                $menuLabel[$buff['value']] = $buff['label'];
            }

            if (empty($key) || empty(self::$config['menu'][$key])) {
                return '';
            }

            $query = '';
            foreach ($str as $buff) {
                if (!isset($menuLabel[$buff])) {
                    continue;
                }

                strlen($query) && $query .= BLOCS_OPTION_SEPARATOR;
                $query .= $menuLabel[$buff];
            }

            $str = $query;
        }

        // 文字列のエスケープ
        $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        $str = nl2br($str);

        return $str;
    }

    // selectなどのフォーム部品にchecked、selectedをつける
    public static function addChecked($str, $value, $checked, $checkFlg)
    {
        if (is_array($str)) {
            count($str) || $str = null;
        } else {
            isset($str) && strlen($str) || $str = null;
        }

        if (!isset($str)) {
            // 未入力
            if ($checked) {
                echo ' '.$checkFlg;

                return;
            }
            $str = '';
        }

        is_array($str) || $str = explode("\t", $str);

        // 選択項目にcheckedを付与
        if (in_array($value, $str)) {
            echo ' '.$checkFlg;
        }
    }

    // 設定ファイルを読み込み
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

        $configPath = self::getConfigPath(dirname($path));
        if (!is_file($configPath)) {
            // autoincludeの指定
            if (class_exists('\Route') && isset($GLOBALS[\Route::currentRouteAction()])) {
                foreach ($GLOBALS[\Route::currentRouteAction()] as $key => $value) {
                    $GLOBALS[$key] = $value;
                }
            }

            // 設定ファイルが見つからない
            $blocsCompiler = new Compiler\BlocsCompiler();
            $contents = $blocsCompiler->compile($path);

            // 設定ファイルを作成
            $blocsConfig = $blocsCompiler->getConfig();

            return self::writeConfig($path, $blocsConfig);
        }

        self::$config = json_decode(file_get_contents($configPath), true);

        // 動的メニューの取り込み
        $appendOption = \Blocs\Option::append();
        foreach ($appendOption as $menuName => $menu) {
            isset(self::$config['menu'][$menuName]) || self::$config['menu'][$menuName] = [];
            self::$config['menu'][$menuName] = array_merge(self::$config['menu'][$menuName], $menu);
        }

        return self::$config;
    }

    // 設定ファイルを書き込み
    public static function writeConfig($path, $blocsConfig)
    {
        $configPath = self::getConfigPath(dirname($path));
        if (is_file($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
        } else {
            // 設定ファイルが見つからない
            $config = [];
        }

        // ファイルアップロードのバリデーション
        $validateUpload = [];
        foreach ($blocsConfig->upload as $formName) {
            unset($validateUpload[$formName]);

            if (isset($blocsConfig->validate[$formName])) {
                $validateUpload[$formName]['validate'] = $blocsConfig->validate[$formName];
                unset($blocsConfig->validate[$formName]);
            }
            if (isset($blocsConfig->message[$formName])) {
                $validateUpload[$formName]['message'] = $blocsConfig->message[$formName];
                unset($blocsConfig->message[$formName]);
            }
        }
        isset($config['upload']) || $config['upload'] = [];
        $config['upload'] = array_merge($config['upload'], $validateUpload);

        $config = self::updateConfig($config, 'include', $path, $blocsConfig->include);
        $config = self::updateConfig($config, 'timestamp', $path, time());
        $config = self::updateConfig($config, 'filter', $path, $blocsConfig->filter);
        $config = self::updateConfig($config, 'option', $path, $blocsConfig->option);
        $config = self::updateConfig($config, 'validate', $path, $blocsConfig->validate);
        $config = self::updateConfig($config, 'message', $path, $blocsConfig->message);

        // Optionをフォーム名ごとに集約
        $existValueList = [];
        $config['menu'] = [];
        foreach ($config['option'] as $path => $configOption) {
            foreach ($configOption as $formName => $optionList) {
                foreach ($optionList as $option) {
                    if (!isset($option['value'])) {
                        continue;
                    }
                    if (isset($existValueList[$formName][$option['value']])) {
                        // valueが重複している
                        continue;
                    }

                    isset($config['menu'][$formName]) || $config['menu'][$formName] = [];
                    $config['menu'][$formName][] = $option;

                    $existValueList[$formName][$option['value']] = true;
                }
            }
        }

        // 設定ファイルはディレクトリごとに作成
        file_put_contents($configPath, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n") && chmod($configPath, 0666);

        // 設定ファイルを読み込み
        return self::readConfig($path);
    }

    public static function getConfigPath($path)
    {
        return BLOCS_CACHE_DIR.'/'.md5($path).'.json';
    }

    private static function updateConfig($config, $configName, $path, $blocsConfig)
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
        $currentName = \Route::currentRouteName();

        if (empty($currentName)) {
            return $currentName;
        }

        $currentNameList = explode('.', $currentName);
        array_pop($currentNameList);
        $currentPrefix = implode('.', $currentNameList);

        return $currentPrefix;
    }
}

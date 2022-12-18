<?php

namespace Blocs;

class Validate
{
    private static $path;
    private static $config;

    /**
     * テンプレートで指定したバリデーションとメッセージを取得する
     * 取得したバリデーションとメッセージはLaravelのvalidateに渡す
     * $requestを渡すと指定したフィルターをかける
     *
     * list($validate, $message) = \Blocs\Validate::get('insert', $request);
     * empty($validate) || $request->validate($validate, $message);
     *
     * @param string  $templateName テンプレート名
     * @param Request $request      リクエスト
     *
     * @return array テンプレートで指定したバリデーション
     * @return array テンプレートで指定したメッセージ
     */
    public static function get($templateName, $request = null)
    {
        // 設定ファイルを読み込み
        self::$path = Common::getPath($templateName);
        self::$config = Common::readConfig(self::$path);

        if (isset($request)) {
            $requestAll = $request->all();
            $requestAll = self::filter($templateName, $requestAll);
            empty($requestAll) || $request->merge($requestAll);
        }

        if (empty(self::$config['validate'][self::$path])) {
            return [[], []];
        }
        $configValidate = self::$config['validate'][self::$path];

        foreach ($configValidate as $formName => $validateList) {
            foreach ($validateList as $validateNum => $validate) {
                list($className) = explode('(', $validate, 2);
                if (class_exists('\App\Rules\\'.$className)) {
                    eval("\$configValidate[\$formName][\$validateNum] = new \\App\Rules\\{$validate};");
                }
            }
        }

        if (empty(self::$config['message'][self::$path])) {
            $configMessage = [];
        } else {
            $configMessage = self::$config['message'][self::$path];
        }

        $validateMessage = [];
        foreach ($configMessage as $formName => $messageList) {
            foreach ($messageList as $dataValidate => $message) {
                $validateMessage[$formName.'.'.$dataValidate] = \Blocs\Lang::get($message);
            }
        }

        return [$configValidate, $validateMessage];
    }

    /**
     * テンプレートで指定したバリデーションとメッセージを取得する
     * 取得したバリデーションとメッセージはLaravelのvalidateに渡す
     * 新規入力、編集など複数のテンプレートで同じバリデーションを使うケースが多いので
     * テンプレートではなくディレクトリを指定する
     *
     * list($validate, $message) = \Blocs\Validate::upload('/', 'upload');
     * empty($validate) || $request->validate($validate, $message);
     *
     * @param string $templateDir テンプレートのあるディレクトリ
     * @param string $formName    フォーム名
     *
     * @return array テンプレートで指定したバリデーション
     * @return array テンプレートで指定したメッセージ
     */
    public static function upload($templateDir, $formName)
    {
        $templateDir = Common::getPath($templateDir);
        $configPath = BLOCS_CACHE_DIR.'/'.md5($templateDir).'.json';
        if (!is_file($configPath)) {
            return [[], []];
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (!isset($config['upload'][$formName])) {
            return [[], []];
        }

        if (isset($config['upload'][$formName]['validate'])) {
            $uploadValidate = ['upload' => $config['upload'][$formName]['validate']];
        } else {
            $uploadValidate = [];
        }

        $uploadMessage = [];
        if (isset($config['upload'][$formName]['message'])) {
            $messageList = $config['upload'][$formName]['message'];
            foreach ($messageList as $dataValidate => $message) {
                $uploadMessage['upload.'.$dataValidate] = \Blocs\Lang::get($message);
            }
        }

        return [$uploadValidate, $uploadMessage];
    }

    /**
     * $requestAllにテンプレートで指定したフィルターをかける
     *
     * $requestAll = \Blocs\Validate::filter('insert', $requestAll);
     *
     * @param string $templateName テンプレート名
     * @param array  $$requestAll  フィルターをかける配列
     *
     * @return array フィルターをかけた配列
     */
    public static function filter($templateName, $requestAll)
    {
        // 設定ファイルを読み込み
        self::$path = Common::getPath($templateName);
        self::$config = Common::readConfig(self::$path);

        return self::filterArray($requestAll);
    }

    private static function filterArray($requestArray)
    {
        if (empty(self::$config['filter'][self::$path])) {
            return $requestArray;
        }
        $configFilter = self::$config['filter'][self::$path];

        foreach ($requestArray as $key => $value) {
            if (is_array($value)) {
                $requestArray[$key] = self::filterArray($value);
                continue;
            }

            if (empty($configFilter[$key])) {
                continue;
            }

            eval($configFilter[$key]);
            $requestArray[$key] = $value;
        }

        return $requestArray;
    }
}

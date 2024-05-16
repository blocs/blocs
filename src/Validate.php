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
     * list($rules, $messages) = \Blocs\Validate::get('insert', $request);
     * empty($rules) || $request->validate($rules, $messages);
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

        if (empty(self::$config['message'][self::$path])) {
            $configMessage = [];
        } else {
            $configMessage = self::$config['message'][self::$path];
        }

        $validateMessage = [];
        foreach ($configMessage as $formName => $messageList) {
            foreach ($messageList as $dataValidate => $message) {
                $validateMessage[$formName.'.'.$dataValidate] = Lang::get($message);
            }
        }

        foreach ($configValidate as $formName => $validateList) {
            foreach ($validateList as $validateNum => $validate) {
                $msgArgList = explode(':', $validate);
                $className = $msgArgList[0];
                $msgArgList = array_slice($msgArgList, 1);

                if (class_exists('\App\Rules\\'.$className)) {
                    if (isset($validateMessage[$formName.'.'.$className])) {
                        array_push($msgArgList, $validateMessage[$formName.'.'.$className]);
                    }

                    $reflClass = new \ReflectionClass('\App\Rules\\'.$className);
                    $configValidate[$formName][$validateNum] = call_user_func_array([$reflClass, 'newInstance'], $msgArgList);
                }
            }
        }

        return [$configValidate, $validateMessage];
    }

    public static function rules($templateName)
    {
        list($rules, $messages) = self::get($templateName);

        return $rules;
    }

    public static function messages($templateName)
    {
        list($rules, $messages) = self::get($templateName);

        return $messages;
    }

    /**
     * テンプレートで指定したバリデーションとメッセージを取得する
     * 取得したバリデーションとメッセージはLaravelのvalidateに渡す
     * 新規入力、編集など複数のテンプレートで同じバリデーションを使うケースが多いので
     * テンプレートではなくディレクトリを指定する
     *
     * list($rules, $messages) = \Blocs\Validate::upload('/', 'upload');
     * empty($rules) || $request->validate($rules, $messages);
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
        $configPath = Common::getConfigPath($templateDir);
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
                $uploadMessage['upload.'.$dataValidate] = Lang::get($message);
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

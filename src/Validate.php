<?php

namespace Blocs;

class Validate
{
    private static $path;

    private static $config;

    public static function flush(): void
    {
        self::$path = null;
        self::$config = null;
    }

    public static function get($templateName, $request = null)
    {
        // 設定ファイルを読み込みテンプレートの検証設定を確定
        self::initializeTemplateConfig($templateName);

        if (isset($request)) {
            $requestPayload = $request->all();
            $requestPayload = self::filter($templateName, $requestPayload);
            empty($requestPayload) || $request->merge($requestPayload);
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

        $configValidate = self::resolveRuleInstances($configValidate, $validateMessage);

        return [$configValidate, $validateMessage];
    }

    public static function rules($templateName)
    {
        [$rules, $messages] = self::get($templateName);

        return $rules;
    }

    public static function messages($templateName)
    {
        [$rules, $messages] = self::get($templateName);

        return $messages;
    }

    /**
     * アップロード項目のバリデーションを返す。
     *
     * @return array{0: array, 1: array}|null 未宣言の name は null（呼び出し側で拒否）。宣言済みで validate 無しは [[], []]。
     */
    public static function upload($templateName, $formName)
    {
        $resolvedDir = Common::getPath($templateName);
        if ($resolvedDir === '') {
            return null;
        }

        $configPath = Common::getConfigPath($resolvedDir);
        $config = self::resolveUploadConfig($templateName, $configPath, $formName);
        if ($config === null) {
            return null;
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

        $uploadValidate = self::resolveRuleInstances($uploadValidate, $uploadMessage);

        return [$uploadValidate, $uploadMessage];
    }

    /**
     * viewPrefix 配下の設定 JSON を取得する。未生成・対象 upload 未登録なら create/edit をコンパイルして再取得する。
     */
    private static function resolveUploadConfig(string $templateName, string $configPath, string $formName): ?array
    {
        $config = is_file($configPath) ? Common::loadConfigFile($configPath) : null;
        if (is_array($config) && isset($config['upload'][$formName])) {
            return $config;
        }

        self::ensureUploadTemplatesCompiled($templateName);

        $config = Common::loadConfigFile($configPath);
        if (! is_array($config) || ! isset($config['upload'][$formName])) {
            return null;
        }

        return $config;
    }

    private static function ensureUploadTemplatesCompiled(string $templateName): void
    {
        foreach ([$templateName.'.create', $templateName.'.edit', $templateName.'.index'] as $candidate) {
            $path = Common::getPath($candidate);
            if ($path === '' || ! is_file($path)) {
                continue;
            }

            $config = Common::readConfig($path);
            if (isset($config['timestamp'][$path])) {
                continue;
            }

            $compiler = new Compiler\BlocsCompiler;
            $compiler->compile($path);
            Common::writeConfig($path, $compiler->getConfig());
        }
    }

    public static function filter($templateName, $requestAll)
    {
        // 設定ファイルを読み込みテンプレートの検証設定を確定
        self::initializeTemplateConfig($templateName);

        return self::applyFiltersRecursively($requestAll);
    }

    private static function initializeTemplateConfig($templateName)
    {
        self::$path = Common::getPath($templateName);
        self::$config = Common::readConfig(self::$path);
    }

    private static function applyFiltersRecursively($requestArray)
    {
        if (empty(self::$config['filter'][self::$path])) {
            return $requestArray;
        }
        $configFilter = self::$config['filter'][self::$path];

        foreach ($requestArray as $key => $value) {
            if (is_array($value)) {
                $requestArray[$key] = self::applyFiltersRecursively($value);

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

    private static function resolveRuleInstances($configValidate, $validateMessage)
    {
        foreach ($configValidate as $formName => $validateList) {
            foreach ($validateList as $validateNum => $validate) {
                $ruleArguments = explode(':', $validate);
                $className = $ruleArguments[0];
                $ruleArguments = array_slice($ruleArguments, 1);

                if (! class_exists('\App\Rules\\'.$className)) {
                    continue;
                }

                if (isset($validateMessage[$formName.'.'.$className])) {
                    $ruleArguments[] = $validateMessage[$formName.'.'.$className];
                }

                if (! defined('BLOCS_NO_LARAVEL')) {
                    $reflClass = new \ReflectionClass('\App\Rules\\'.$className);
                    $configValidate[$formName][$validateNum] = call_user_func_array([$reflClass, 'newInstance'], $ruleArguments);
                }
            }
        }

        return $configValidate;
    }
}

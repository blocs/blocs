<?php

namespace Blocs;

class Validate
{
    private static $path;

    private static $config;

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
                $validateMessage[$formName.'.'.$dataValidate] = __($message);
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

    public static function upload($templateDir, $formName)
    {
        $templateDir = Common::getPath($templateDir);
        $configPath = Common::getConfigPath($templateDir);
        if (! is_file($configPath)) {
            return [[], []];
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (! isset($config['upload'][$formName])) {
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
                $uploadMessage['upload.'.$dataValidate] = __($message);
            }
        }

        $uploadValidate = self::resolveRuleInstances($uploadValidate, $uploadMessage);

        return [$uploadValidate, $uploadMessage];
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

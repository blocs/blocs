<?php

namespace Blocs;

class Validate
{
    private static $path;

    private static $config;

    // メッセージコードは「:」区切りのため、:attribute を直接は渡せない
    private const ATTRIBUTE_TOKEN = '{attribute}';

    // カスタムルールのクラスを探す名前空間
    private static string $ruleNamespace = 'App\\Rules';

    /**
     * アップロード項目を宣言しているテンプレート名（viewPrefix からの相対名）
     *
     * @var list<string>
     */
    private static array $uploadTemplateNames = ['create', 'edit', 'index'];

    /**
     * カスタムルールのクラスを探す名前空間を変更する（既定は App\Rules）
     */
    public static function useRuleNamespace(string $namespace): void
    {
        self::$ruleNamespace = trim($namespace, '\\');
    }

    /**
     * アップロード項目を宣言しているテンプレート名を変更する（既定は create / edit / index）
     *
     * @param  list<string>  $templateNames
     */
    public static function useUploadTemplateNames(array $templateNames): void
    {
        self::$uploadTemplateNames = array_values($templateNames);
    }

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
                $validateMessage[$formName.'.'.$dataValidate] = self::resolveMessage($message, $formName);
            }
        }

        $configValidate = self::resolveRuleInstances($configValidate, $validateMessage);

        return [$configValidate, $validateMessage];
    }

    /**
     * メッセージコードを文言に変換する。
     *
     * ラベルが登録されているフォームでは、メッセージコードの引数に書かれたフォーム名を
     * :attribute に置き換え、Laravel のバリデータにラベルへ差し替えさせる。
     * 例: validate:required:email → 「:attribute 欄は必須です。」→「ユーザーID 欄は必須です。」
     */
    private static function resolveMessage(string $message, string $formName): string
    {
        if (! self::shouldUseAttributePlaceholder($formName)) {
            return Lang::get($message);
        }

        $messageChunks = explode(':', $message);
        foreach ($messageChunks as $index => $messageChunk) {
            // 先頭2つはメッセージコード本体なので、引数だけを対象にする
            if ($index >= 2 && $messageChunk === $formName) {
                $messageChunks[$index] = self::ATTRIBUTE_TOKEN;
            }
        }

        return str_replace(self::ATTRIBUTE_TOKEN, ':attribute', Lang::get(implode(':', $messageChunks)));
    }

    private static function shouldUseAttributePlaceholder(string $formName): bool
    {
        // Laravel のバリデータを使わない環境では :attribute が置き換わらない
        if (defined('BLOCS_NO_LARAVEL')) {
            return false;
        }

        return isset(self::$config['label'][self::$path][$formName]);
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
        $config = Common::loadConfigFile($configPath);
        if (isset($config['upload'][$formName])) {
            return $config;
        }

        self::ensureUploadTemplatesCompiled($templateName);

        $config = Common::loadConfigFile($configPath);

        return isset($config['upload'][$formName]) ? $config : null;
    }

    private static function ensureUploadTemplatesCompiled(string $templateName): void
    {
        foreach (self::$uploadTemplateNames as $uploadTemplateName) {
            $path = Common::getPath($templateName.'.'.$uploadTemplateName);
            // 設定がまだ無いテンプレートは、readConfig() がコンパイルして設定を作る
            $path !== '' && is_file($path) && Common::readConfig($path);
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
            $filterList = $configFilter[$key] ?? [];
            is_array($filterList) || $filterList = [];

            if (is_array($value)) {
                $value = self::applyFiltersRecursively($value);

                // name="tags[]" のような複数値は、各値にフィルターを適用する
                foreach ($filterList ? $value : [] as $index => $item) {
                    is_int($index) && ! is_array($item) && $value[$index] = self::applyFilters($filterList, $item);
                }

                $requestArray[$key] = $value;

                continue;
            }

            if (empty($filterList)) {
                continue;
            }

            $requestArray[$key] = self::applyFilters($filterList, $value);
        }

        return $requestArray;
    }

    /**
     * @param  list<array{func: string, args: list<mixed>}>  $filterList
     * @param  mixed  $value
     * @return mixed
     */
    private static function applyFilters(array $filterList, $value)
    {
        foreach ($filterList as $filter) {
            if (! is_array($filter) || ! isset($filter['func']) || ! is_callable($filter['func'])) {
                continue;
            }

            $value = call_user_func($filter['func'], $value, ...array_values($filter['args'] ?? []));
        }

        return $value;
    }

    private static function resolveRuleInstances($configValidate, $validateMessage)
    {
        foreach ($configValidate as $formName => $validateList) {
            foreach ($validateList as $validateNum => $validate) {
                $ruleArguments = explode(':', $validate);
                $className = $ruleArguments[0];
                $ruleArguments = array_slice($ruleArguments, 1);

                $ruleClass = '\\'.self::$ruleNamespace.'\\'.$className;
                if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $className) || ! class_exists($ruleClass)) {
                    continue;
                }

                if (isset($validateMessage[$formName.'.'.$className])) {
                    $ruleArguments[] = $validateMessage[$formName.'.'.$className];
                }

                if (! defined('BLOCS_NO_LARAVEL')) {
                    $reflClass = new \ReflectionClass($ruleClass);
                    $configValidate[$formName][$validateNum] = call_user_func_array([$reflClass, 'newInstance'], $ruleArguments);
                }
            }
        }

        return $configValidate;
    }
}

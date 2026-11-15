<?php

namespace Blocs;

class Option
{
    private static array $appendOption = [];

    /**
     * テンプレートから取り込んだ選択肢（ラベルをテンプレートとして解釈してよいもの）
     *
     * @var array<string, array<string, bool>>
     */
    private static array $templateLabel = [];

    // 動的な選択肢が変わるたびに増える（設定キャッシュの無効化に使う）
    private static int $revision = 0;

    /**
     * addOption() で登録した動的な選択肢をすべて破棄する。
     * 常駐ワーカー（Octane）でリクエストをまたいで残らないよう、リクエスト開始時に呼ぶ。
     */
    public static function flush(): void
    {
        self::$appendOption = [];
        self::$templateLabel = [];
        self::$revision++;
    }

    public static function get($templateName, $formName)
    {
        $menu = self::templateMenu($templateName, $formName);

        return array_column($menu, 'label', 'value');
    }

    /**
     * 動的な選択肢を追加する。
     *
     * $optionList は [値 => ラベル]、[グループ名 => [値 => ラベル]]、値だけのリスト ['a', 'b']、単一の値のいずれか。
     * [0 => '無効', 1 => '有効'] のように 0 始まりの連番キーを値として使う場合は、
     * 値だけのリストと区別できないので $isValueList に false を渡す。
     *
     * @param  bool|null  $isValueList  null なら自動判定（0 始まりの連番キーは値だけのリストとみなす）
     */
    public static function add($formName, $optionList, ?bool $isValueList = null)
    {
        self::register($formName, self::buildMenuEntries($optionList, $isValueList), false);
    }

    public static function set($templateName, $formName)
    {
        // テンプレートの定義（値・ラベル・グループ）をそのまま引き継ぐ
        $menuEntries = [];
        foreach (self::templateMenu($templateName, $formName) as $menu) {
            if (! isset($menu['value'])) {
                continue;
            }

            $entry = ['value' => $menu['value'], 'label' => $menu['label'] ?? $menu['value']];
            isset($menu['optgroup']) && $entry['optgroup'] = $menu['optgroup'];
            empty($menu['dynamic']) || $entry['dynamic'] = true;

            $menuEntries[] = $entry;
        }

        self::register($formName, $menuEntries, true);
    }

    /**
     * 選択肢のラベルがテンプレート由来か（テンプレートとして解釈してよいか）
     *
     * @param  string|int  $value
     */
    public static function isTemplateLabel($formName, $value): bool
    {
        return isset(self::$templateLabel[$formName][(string) $value]);
    }

    public static function revision(): int
    {
        return self::$revision;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function templateMenu($templateName, $formName): array
    {
        $path = Common::getPath($templateName);

        // 設定ファイルを読み込みテンプレートに定義されたメニューを取得
        $config = Common::readConfig($path);
        if (empty($config['menu'][$formName])) {
            return [];
        }

        return $config['menu'][$formName];
    }

    /**
     * @param  list<array<string, mixed>>  $menuEntries
     */
    private static function register($formName, array $menuEntries, bool $fromTemplate): void
    {
        if (! isset(self::$appendOption[$formName])) {
            self::$appendOption[$formName] = [];
        }

        $registeredValues = array_map('strval', array_column(self::$appendOption[$formName], 'value'));

        foreach ($menuEntries as $entry) {
            $isTemplateLabel = $fromTemplate && empty($entry['dynamic']);
            unset($entry['dynamic']);

            if (in_array((string) $entry['value'], $registeredValues, true)) {
                continue;
            }

            self::$appendOption[$formName][] = $entry;
            $registeredValues[] = (string) $entry['value'];

            $isTemplateLabel && self::$templateLabel[$formName][(string) $entry['value']] = true;
        }

        self::$revision++;

        // 設定ファイルを読み込み
        Common::readConfig();
    }

    // テンプレートの初期処理で使用
    public static function append()
    {
        return self::$appendOption;
    }

    private static function buildMenuEntries($menu, ?bool $isValueList = null)
    {
        $menuLabelMap = [];
        $menuGroupMap = [];

        if (is_array($menu)) {
            if ($isValueList ?? array_is_list($menu)) {
                // valueだけが指定された場合は value をラベルとして再構成
                $menu = array_combine(array_values($menu), array_values($menu));
            }

            foreach ($menu as $menuKey => $menuValue) {
                if (is_array($menuValue)) {
                    foreach ($menuValue as $optionValue => $optionLabel) {
                        $menuLabelMap[$optionValue] = $optionLabel;
                        $menuGroupMap[$optionValue] = $menuKey;
                    }
                } else {
                    $menuLabelMap[$menuKey] = $menuValue;
                }
            }
        } else {
            // 単一の値は、値をラベルとして使う
            $menuLabelMap = [$menu => $menu];
        }

        $menuEntries = [];
        foreach ($menuLabelMap as $value => $labelName) {
            if (isset($menuGroupMap[$value])) {
                $menuEntries[] = ['value' => $value, 'label' => $labelName, 'optgroup' => $menuGroupMap[$value]];
            } else {
                $menuEntries[] = ['value' => $value, 'label' => $labelName];
            }
        }

        return $menuEntries;
    }
}

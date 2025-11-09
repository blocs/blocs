<?php

namespace Blocs;

class Option
{
    private static array $appendOption = [];

    public static function get($templateName, $formName)
    {
        $path = Common::getPath($templateName);

        // 設定ファイルを読み込みテンプレートに定義されたメニューを取得
        $config = Common::readConfig($path);
        if (empty($config['menu'][$formName])) {
            return [];
        }

        return array_column($config['menu'][$formName], 'label', 'value');
    }

    public static function add($formName, $optionList)
    {
        if (! isset(self::$appendOption[$formName])) {
            self::$appendOption[$formName] = [];
        }

        $registeredValues = array_column(self::$appendOption[$formName], 'value');
        $menuEntries = self::buildMenuEntries($optionList);

        foreach ($menuEntries as $entry) {
            in_array($entry['value'], $registeredValues, true) || self::$appendOption[$formName][] = $entry;
        }

        // 設定ファイルを読み込み
        Common::readConfig();
    }

    public static function set($templateName, $formName)
    {
        $optionList = self::get($templateName, $formName);
        self::add($formName, $optionList);
    }

    // テンプレートの初期処理で使用
    public static function append()
    {
        return self::$appendOption;
    }

    private static function buildMenuEntries($menu, $label = null, $optionGroupList = null)
    {
        $menuLabelMap = [];
        $menuGroupMap = [];

        if (is_array($menu)) {
            if (array_values($menu) === $menu) {
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
            $defaultLabel = $label ?? $menu;
            $menuLabelMap = [$menu => $defaultLabel];
            if (! is_null($optionGroupList)) {
                $menuGroupMap[$menu] = $optionGroupList;
            }
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

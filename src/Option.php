<?php

namespace Blocs;

class Option
{
    private static $appendOption = [];

    /**
     * テンプレートで定義したoptionを取得する
     *
     * $optionList = \Blocs\Option::get('insert', 'item');
     *
     * @param string $templateName テンプレート名
     * @param string $formName     フォーム名
     *
     * @return array optionの値とラベル
     */
    public static function get($templateName, $formName)
    {
        $path = Common::getPath($templateName);

        // 設定ファイルを読み込み
        $config = Common::readConfig($path);

        if (empty($config['menu'][$formName])) {
            return [];
        }

        $optionList = [];
        foreach ($config['menu'][$formName] as $buff) {
            $optionList[$buff['value']] = $buff['label'];
        }

        return $optionList;
    }

    /**
     * テンプレートで定義したoptionに動的に項目を追加する
     *
     * \Blocs\Option::add('item', ['value' => 'label']);
     *
     * @param string $formName   フォーム名
     * @param array  $optionList 値とラベルの配列
     */
    public static function add($formName, $optionList)
    {
        isset(self::$appendOption[$formName]) || self::$appendOption[$formName] = [];

        $valueList = [];
        foreach (self::$appendOption[$formName] as $option) {
            $valueList[] = $option['value'];
        }

        $optionList = self::addMenu($optionList);
        foreach ($optionList as $option) {
            in_array($option['value'], $valueList) || self::$appendOption[$formName][] = $option;
        }

        // 設定ファイルを読み込み
        Common::readConfig();
    }

    /**
     * 他のテンプレートで定義したoptionをセットする
     * 同じディレクトリ内のテンプレートはoptionが共有される
     * 違うディレクトリのテンプレートにoptionを共有したい時に使う
     *
     * \Blocs\Option::set('insert', 'item');
     *
     * @param string $templateName テンプレート名
     * @param string $formName     フォーム名
     *
     * @return array optionの値とラベル
     */
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

    private static function addMenu($menu, $label = null, $optionGroupList = null)
    {
        $menuList = [];
        $optionGroupList = [];
        if (is_array($menu)) {
            if (array_values($menu) === $menu) {
                // valueだけ指定された時
                $menu = array_combine(array_values($menu), array_values($menu));
            }

            foreach ($menu as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $optionKey => $optionValue) {
                        $menuList[$optionKey] = $optionValue;
                        $optionGroupList[$optionKey] = $key;
                    }
                } else {
                    $menuList[$key] = $value;
                }
            }
        } else {
            isset($label) || $label = $menu;
            $menuList = [$menu => $label];
            $optionGroupList[$menu] = $optionGroupList;
        }

        $menuDataList = [];
        foreach ($menuList as $value => $label) {
            if (isset($optionGroupList[$value])) {
                $menuDataList[] = ['value' => $value, 'label' => $label, 'optgroup' => $optionGroupList[$value]];
            } else {
                $menuDataList[] = ['value' => $value, 'label' => $label];
            }
        }

        return $menuDataList;
    }
}

<?php

// テンプレートのキャッシュを保存するディレクトリ
defined('BLOCS_CACHE_DIR') || define('BLOCS_CACHE_DIR', config('view.compiled'));

(realpath(BLOCS_CACHE_DIR) && is_writable(BLOCS_CACHE_DIR)) || trigger_error('B001: Can not write cache file into directory', E_USER_ERROR);

// テンプレートのルートディレクトリ
if (! defined('BLOCS_ROOT_DIR')) {
    $viewPathList = config('view.paths');
    define('BLOCS_ROOT_DIR', $viewPathList[0]);
}

// optionをつなぐ文字列
defined('BLOCS_OPTION_SEPARATOR') || define('BLOCS_OPTION_SEPARATOR', ', ');

// includeの上限設定
defined('BLOCS_INCLUDE_MAX') || define('BLOCS_INCLUDE_MAX', 50);

// compilerで使う定数
define('BLOCS_ENDIF_SCRIPT', "<?php endif; ?>\n");

// データ属性
define('BLOCS_DATA_INCLUDE', 'data-include');
define('BLOCS_DATA_BLOC', 'data-bloc');
define('BLOCS_DATA_ENDBLOC', 'data-endbloc');

define('BLOCS_DATA_VAL', 'data-val');
define('BLOCS_DATA_ATTRIBUTE', 'data-attribute');
define('BLOCS_DATA_ASSIGN', 'data-assign');
define('BLOCS_DATA_PREFIX', 'data-prefix');
define('BLOCS_DATA_POSTFIX', 'data-postfix');
define('BLOCS_DATA_CONVERT', 'data-convert');
define('BLOCS_DATA_NOTICE', 'data-notice');

define('BLOCS_DATA_EXIST', 'data-exist');
define('BLOCS_DATA_NONE', 'data-none');
define('BLOCS_DATA_IF', 'data-if');
define('BLOCS_DATA_UNLESS', 'data-unless');

define('BLOCS_DATA_ENDEXIST', 'data-endexist');
define('BLOCS_DATA_ENDNONE', 'data-endnone');
define('BLOCS_DATA_ENDIF', 'data-endif');
define('BLOCS_DATA_ENDUNLESS', 'data-endunless');

define('BLOCS_DATA_LOOP', 'data-loop');
define('BLOCS_DATA_ENDLOOP', 'data-endloop');

define('BLOCS_DATA_FORM', 'data-form');
define('BLOCS_DATA_VALIDATE', 'data-validate');
define('BLOCS_DATA_FILTER', 'data-filter');

define('BLOCS_DATA_CHDIR', 'data-chdir');

// データ属性のエイリアス
define('BLOCS_DATA_LANG', 'data-lang');
define('BLOCS_DATA_REPEAT', 'data-repeat');
define('BLOCS_DATA_ENDREPEAT', 'data-endrepeat');

define('BLOCS_TAG_NAME_REGREX', '[a-zA-Z\_\:\!\$][a-zA-Z0-9\_\:\-\.]*');
define('BLOCS_ATTR_NAME_REGREX', '[a-zA-Z0-9\_\:\!\-\/][a-zA-Z0-9\_\-\.\*]*');

define('BLOCS_CLASS_UPLOAD', 'ai-upload');

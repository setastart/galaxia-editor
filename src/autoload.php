<?php

use Galaxia\Director;


spl_autoload_register(function($className) {
    $className = ltrim($className, '\\');
    $classes   = explode('\\', $className);

    if ($classes[0] == 'Galaxia') {
        switch ($classes[1]) {
            case 'FastRoute':
                $fileName = __DIR__ . '/shared/fastroute/src/' . implode('/', array_slice($classes, 2)) . '.php';
                break;

            case 'RedisCli':
                $fileName = __DIR__ . '/shared/redis/src/RedisCli.php';
                break;

            default:
                $fileName = __DIR__ . '/shared/core/class/' . implode('/', array_slice($classes, 1)) . '.php';
                break;
        }
        require_once $fileName;
    }
});


// @formatter:off
if (Director::isDevEnv()) {
    include_once __DIR__ . '/shared/kint.phar';
    Kint\Renderer\RichRenderer::$folder = false;
    function dd(...$vars) {
        d(...$vars);
        exit;
    }
    function sd(...$vars) {
        s(...$vars);
        exit;
    }
    function db() {
        Kint::trace();
    }
} else if (Director::isCli()) {
    function d(...$vars) {
        foreach ($vars as $var) {
            ob_start();
            var_dump($var);
            $dump = ob_get_clean();
            $dump = preg_replace('/=>\n\s+/m', ' => ', (string)$dump);
            $dump = str_replace('<?php ', '', (string)$dump);
            echo $dump;
        }
    }
    function s(...$vars) { d($vars); }
    function dd(...$vars) {d($vars); exit; }
    function sd(...$vars) {d($vars); exit; }
    function db() {
        $backtrace = array_reverse(debug_backtrace());
        $r = '';
        foreach ($backtrace as $trace) {
            foreach (['file', 'class', 'function', 'line', 'type'] as $property) {
                if ($trace[$property] ?? '') $r .= ' - ' . $trace[$property];
            }
        }
        echo $r . PHP_EOL;
    }
} else {
    function d() {}
    function s() {}
    function dd() {}
    function sd() {}
    function db() {}
}
// @formatter:on



require_once __DIR__ . '/shared/core/function/polyfill.php';
require_once __DIR__ . '/shared/core/function/error.php';
require_once __DIR__ . '/shared/core/function/gFile.php';
require_once __DIR__ . '/shared/core/function/text.php';
require_once __DIR__ . '/shared/core/function/util.php';

require_once __DIR__ . '/shared/fastroute/src/functions.php';




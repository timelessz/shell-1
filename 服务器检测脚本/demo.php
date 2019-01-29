<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use think\exception\ClassNotFoundException;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\RouteNotFoundException;

/**
 * App 搴旂敤绠＄悊
 * @author  liu21st <liu21st@gmail.com>
 */
class App
{
    /**
     * @var bool 鏄惁鍒濆鍖栬繃
     */
    protected static $init = false;

    /**
     * @var string 褰撳墠妯″潡璺緞
     */
    public static $modulePath;

    /**
     * @var bool 搴旂敤璋冭瘯妯″紡
     */
    public static $debug = true;

    /**
     * @var string 搴旂敤绫诲簱鍛藉悕绌洪棿
     */
    public static $namespace = 'app';

    /**
     * @var bool 搴旂敤绫诲簱鍚庣紑
     */
    public static $suffix = false;

    /**
     * @var bool 搴旂敤璺敱妫€娴�
     */
    protected static $routeCheck;

    /**
     * @var bool 涓ユ牸璺敱妫€娴�
     */
    protected static $routeMust;

    protected static $dispatch;
    protected static $file = [];

    /**
     * 鎵ц搴旂敤绋嬪簭
     * @access public
     * @param Request $request Request瀵硅薄
     * @return Response
     * @throws Exception
     */
    public static function run(Request $request = null)
    {
        is_null($request) && $request = Request::instance();

        try {
            $config = self::initCommon();
            if (defined('BIND_MODULE')) {
                // 妯″潡/鎺у埗鍣ㄧ粦瀹�
                BIND_MODULE && Route::bind(BIND_MODULE);
            } elseif ($config['auto_bind_module']) {
                // 鍏ュ彛鑷姩缁戝畾
                $name = pathinfo($request->baseFile(), PATHINFO_FILENAME);
                if ($name && 'index' != $name && is_dir(APP_PATH . $name)) {
                    Route::bind($name);
                }
            }

            $request->filter($config['default_filter']);

            // 榛樿璇█
            Lang::range($config['default_lang']);
            if ($config['lang_switch_on']) {
                // 寮€鍚璇█鏈哄埗 妫€娴嬪綋鍓嶈瑷€
                Lang::detect();
            }
            $request->langset(Lang::range());

            // 鍔犺浇绯荤粺璇█鍖�
            Lang::load([
                THINK_PATH . 'lang' . DS . $request->langset() . EXT,
                APP_PATH . 'lang' . DS . $request->langset() . EXT,
            ]);

            // 鑾峰彇搴旂敤璋冨害淇℃伅
            $dispatch = self::$dispatch;
            if (empty($dispatch)) {
                // 杩涜URL璺敱妫€娴�
                $dispatch = self::routeCheck($request, $config);
            }
            // 璁板綍褰撳墠璋冨害淇℃伅
            $request->dispatch($dispatch);

            // 璁板綍璺敱鍜岃姹備俊鎭�
            if (self::$debug) {
                Log::record('[ ROUTE ] ' . var_export($dispatch, true), 'info');
                Log::record('[ HEADER ] ' . var_export($request->header(), true), 'info');
                Log::record('[ PARAM ] ' . var_export($request->param(), true), 'info');
            }

            // 鐩戝惉app_begin
            Hook::listen('app_begin', $dispatch);
            // 璇锋眰缂撳瓨妫€鏌�
            $request->cache($config['request_cache'], $config['request_cache_expire'], $config['request_cache_except']);

            $data = self::exec($dispatch, $config);
        } catch (HttpResponseException $exception) {
            $data = $exception->getResponse();
        }

        // 娓呯┖绫荤殑瀹炰緥鍖�
        Loader::clearInstance();

        // 杈撳嚭鏁版嵁鍒板鎴风
        if ($data instanceof Response) {
            $response = $data;
        } elseif (!is_null($data)) {
            // 榛樿鑷姩璇嗗埆鍝嶅簲杈撳嚭绫诲瀷
            $isAjax   = $request->isAjax();
            $type     = $isAjax ? Config::get('default_ajax_return') : Config::get('default_return_type');
            $response = Response::create($data, $type);
        } else {
            $response = Response::create();
        }

        // 鐩戝惉app_end
        Hook::listen('app_end', $response);

        return $response;
    }

    /**
     * 璁剧疆褰撳墠璇锋眰鐨勮皟搴︿俊鎭�
     * @access public
     * @param array|string  $dispatch 璋冨害淇℃伅
     * @param string        $type 璋冨害绫诲瀷
     * @return void
     */
    public static function dispatch($dispatch, $type = 'module')
    {
        self::$dispatch = ['type' => $type, $type => $dispatch];
    }

    /**
     * 鎵ц鍑芥暟鎴栬€呴棴鍖呮柟娉� 鏀寔鍙傛暟璋冪敤
     * @access public
     * @param string|array|\Closure $function 鍑芥暟鎴栬€呴棴鍖�
     * @param array                 $vars     鍙橀噺
     * @return mixed
     */
    public static function invokeFunction($function, $vars = [])
    {
        $reflect = new \ReflectionFunction($function);
        $args    = self::bindParams($reflect, $vars);
        // 璁板綍鎵ц淇℃伅
        self::$debug && Log::record('[ RUN ] ' . $reflect->__toString(), 'info');
        return $reflect->invokeArgs($args);
    }

    /**
     * 璋冪敤鍙嶅皠鎵ц绫荤殑鏂规硶 鏀寔鍙傛暟缁戝畾
     * @access public
     * @param string|array $method 鏂规硶
     * @param array        $vars   鍙橀噺
     * @return mixed
     */
    public static function invokeMethod($method, $vars = [])
    {
        if (is_array($method)) {
            $class   = is_object($method[0]) ? $method[0] : self::invokeClass($method[0]);
            $reflect = new \ReflectionMethod($class, $method[1]);
        } else {
            // 闈欐€佹柟娉�
            $reflect = new \ReflectionMethod($method);
        }
        $args = self::bindParams($reflect, $vars);

        self::$debug && Log::record('[ RUN ] ' . $reflect->class . '->' . $reflect->name . '[ ' . $reflect->getFileName() . ' ]', 'info');
        return $reflect->invokeArgs(isset($class) ? $class : null, $args);
    }

    /**
     * 璋冪敤鍙嶅皠鎵ц绫荤殑瀹炰緥鍖� 鏀寔渚濊禆娉ㄥ叆
     * @access public
     * @param string    $class 绫诲悕
     * @param array     $vars  鍙橀噺
     * @return mixed
     */
    public static function invokeClass($class, $vars = [])
    {
        $reflect     = new \ReflectionClass($class);
        $constructor = $reflect->getConstructor();
        if ($constructor) {
            $args = self::bindParams($constructor, $vars);
        } else {
            $args = [];
        }
        return $reflect->newInstanceArgs($args);
    }

    /**
     * 缁戝畾鍙傛暟
     * @access private
     * @param \ReflectionMethod|\ReflectionFunction $reflect 鍙嶅皠绫�
     * @param array                                 $vars    鍙橀噺
     * @return array
     */
    private static function bindParams($reflect, $vars = [])
    {
        if (empty($vars)) {
            // 鑷姩鑾峰彇璇锋眰鍙橀噺
            if (Config::get('url_param_type')) {
                $vars = Request::instance()->route();
            } else {
                $vars = Request::instance()->param();
            }
        }
        $args = [];
        if ($reflect->getNumberOfParameters() > 0) {
            // 鍒ゆ柇鏁扮粍绫诲瀷 鏁板瓧鏁扮粍鏃舵寜椤哄簭缁戝畾鍙傛暟
            reset($vars);
            $type   = key($vars) === 0 ? 1 : 0;
            $params = $reflect->getParameters();
            foreach ($params as $param) {
                $args[] = self::getParamValue($param, $vars, $type);
            }
        }
        return $args;
    }

    /**
     * 鑾峰彇鍙傛暟鍊�
     * @access private
     * @param \ReflectionParameter  $param
     * @param array                 $vars    鍙橀噺
     * @param string                $type
     * @return array
     */
    private static function getParamValue($param, &$vars, $type)
    {
        $name  = $param->getName();
        $class = $param->getClass();
        if ($class) {
            $className = $class->getName();
            $bind      = Request::instance()->$name;
            if ($bind instanceof $className) {
                $result = $bind;
            } else {
                if (method_exists($className, 'invoke')) {
                    $method = new \ReflectionMethod($className, 'invoke');
                    if ($method->isPublic() && $method->isStatic()) {
                        return $className::invoke(Request::instance());
                    }
                }
                $result = method_exists($className, 'instance') ? $className::instance() : new $className;
            }
        } elseif (1 == $type && !empty($vars)) {
            $result = array_shift($vars);
        } elseif (0 == $type && isset($vars[$name])) {
            $result = $vars[$name];
        } elseif ($param->isDefaultValueAvailable()) {
            $result = $param->getDefaultValue();
        } else {
            throw new \InvalidArgumentException('method param miss:' . $name);
        }
        return $result;
    }

    protected static function exec($dispatch, $config)
    {
        switch ($dispatch['type']) {
            case 'redirect':
                // 鎵ц閲嶅畾鍚戣烦杞�
                $data = Response::create($dispatch['url'], 'redirect')->code($dispatch['status']);
                break;
            case 'module':
                // 妯″潡/鎺у埗鍣�/鎿嶄綔
                $data = self::module($dispatch['module'], $config, isset($dispatch['convert']) ? $dispatch['convert'] : null);
                break;
            case 'controller':
                // 鎵ц鎺у埗鍣ㄦ搷浣�
                $vars = array_merge(Request::instance()->param(), $dispatch['var']);
                $data = Loader::action($dispatch['controller'], $vars, $config['url_controller_layer'], $config['controller_suffix']);
                break;
            case 'method':
                // 鎵ц鍥炶皟鏂规硶
                $vars = array_merge(Request::instance()->param(), $dispatch['var']);
                $data = self::invokeMethod($dispatch['method'], $vars);
                break;
            case 'function':
                // 鎵ц闂寘
                $data = self::invokeFunction($dispatch['function']);
                break;
            case 'response':
                $data = $dispatch['response'];
                break;
            default:
                throw new \InvalidArgumentException('dispatch type not support');
        }
        return $data;
    }

    /**
     * 鎵ц妯″潡
     * @access public
     * @param array $result 妯″潡/鎺у埗鍣�/鎿嶄綔
     * @param array $config 閰嶇疆鍙傛暟
     * @param bool  $convert 鏄惁鑷姩杞崲鎺у埗鍣ㄥ拰鎿嶄綔鍚�
     * @return mixed
     */
    public static function module($result, $config, $convert = null)
    {
        if (is_string($result)) {
            $result = explode('/', $result);
        }
        $request = Request::instance();
        if ($config['app_multi_module']) {
            // 澶氭ā鍧楅儴缃�
            $module    = strip_tags(strtolower($result[0] ?: $config['default_module']));
            $bind      = Route::getBind('module');
            $available = false;
            if ($bind) {
                // 缁戝畾妯″潡
                list($bindModule) = explode('/', $bind);
                if (empty($result[0])) {
                    $module    = $bindModule;
                    $available = true;
                } elseif ($module == $bindModule) {
                    $available = true;
                }
            } elseif (!in_array($module, $config['deny_module_list']) && is_dir(APP_PATH . $module)) {
                $available = true;
            }

            // 妯″潡鍒濆鍖�
            if ($module && $available) {
                // 鍒濆鍖栨ā鍧�
                $request->module($module);
                $config = self::init($module);
                // 妯″潡璇锋眰缂撳瓨妫€鏌�
                $request->cache($config['request_cache'], $config['request_cache_expire'], $config['request_cache_except']);
            } else {
                throw new HttpException(404, 'module not exists:' . $module);
            }
        } else {
            // 鍗曚竴妯″潡閮ㄧ讲
            $module = '';
            $request->module($module);
        }
        // 褰撳墠妯″潡璺緞
        App::$modulePath = APP_PATH . ($module ? $module . DS : '');

        // 鏄惁鑷姩杞崲鎺у埗鍣ㄥ拰鎿嶄綔鍚�
        $convert = is_bool($convert) ? $convert : $config['url_convert'];
        // 鑾峰彇鎺у埗鍣ㄥ悕
        $controller = strip_tags($result[1] ?: $config['default_controller']);
        $controller = $convert ? strtolower($controller) : $controller;

 if (!preg_match('/^[A-Za-z](w|.)*$/', $controller)) {throw new HttpException(404, 'controller not exists:' . $controller);}
        // 鑾峰彇鎿嶄綔鍚�
        $actionName = strip_tags($result[2] ?: $config['default_action']);
        $actionName = $convert ? strtolower($actionName) : $actionName;

        // 璁剧疆褰撳墠璇锋眰鐨勬帶鍒跺櫒銆佹搷浣�
        $request->controller(Loader::parseName($controller, 1))->action($actionName);

        // 鐩戝惉module_init
        Hook::listen('module_init', $request);

        try {
            $instance = Loader::controller($controller, $config['url_controller_layer'], $config['controller_suffix'], $config['empty_controller']);
        } catch (ClassNotFoundException $e) {
            throw new HttpException(404, 'controller not exists:' . $e->getClass());
        }

        // 鑾峰彇褰撳墠鎿嶄綔鍚�
        $action = $actionName . $config['action_suffix'];

        $vars = [];
        if (is_callable([$instance, $action])) {
            // 鎵ц鎿嶄綔鏂规硶
            $call = [$instance, $action];
        } elseif (is_callable([$instance, '_empty'])) {
            // 绌烘搷浣�
            $call = [$instance, '_empty'];
            $vars = [$actionName];
        } else {
            // 鎿嶄綔涓嶅瓨鍦�
            throw new HttpException(404, 'method not exists:' . get_class($instance) . '->' . $action . '()');
        }

        Hook::listen('action_begin', $call);

        return self::invokeMethod($call, $vars);
    }

    /**
     * 鍒濆鍖栧簲鐢�
     */
    public static function initCommon()
    {
        if (empty(self::$init)) {
            if (defined('APP_NAMESPACE')) {
                self::$namespace = APP_NAMESPACE;
            }
            Loader::addNamespace(self::$namespace, APP_PATH);

            // 鍒濆鍖栧簲鐢�
            $config       = self::init();
            self::$suffix = $config['class_suffix'];

            // 搴旂敤璋冭瘯妯″紡
            self::$debug = Env::get('app_debug', Config::get('app_debug'));
            if (!self::$debug) {
                ini_set('display_errors', 'Off');
            } elseif (!IS_CLI) {
                //閲嶆柊鐢宠涓€鍧楁瘮杈冨ぇ鐨刡uffer
                if (ob_get_level() > 0) {
                    $output = ob_get_clean();
                }
                ob_start();
                if (!empty($output)) {
                    echo $output;
                }
            }

            if (!empty($config['root_namespace'])) {
                Loader::addNamespace($config['root_namespace']);
            }

            // 鍔犺浇棰濆鏂囦欢
            if (!empty($config['extra_file_list'])) {
                foreach ($config['extra_file_list'] as $file) {
                    $file = strpos($file, '.') ? $file : APP_PATH . $file . EXT;
                    if (is_file($file) && !isset(self::$file[$file])) {
                        include $file;
                        self::$file[$file] = true;
                    }
                }
            }

            // 璁剧疆绯荤粺鏃跺尯
            date_default_timezone_set($config['default_timezone']);

            // 鐩戝惉app_init
            Hook::listen('app_init');

            self::$init = true;
        }
        return Config::get();
    }

    /**
     * 鍒濆鍖栧簲鐢ㄦ垨妯″潡
     * @access public
     * @param string $module 妯″潡鍚�
     * @return array
     */
    private static function init($module = '')
    {
        // 瀹氫綅妯″潡鐩綍
        $module = $module ? $module . DS : '';

        // 鍔犺浇鍒濆鍖栨枃浠�
        if (is_file(APP_PATH . $module . 'init' . EXT)) {
            include APP_PATH . $module . 'init' . EXT;
        } elseif (is_file(RUNTIME_PATH . $module . 'init' . EXT)) {
            include RUNTIME_PATH . $module . 'init' . EXT;
        } else {
            $path = APP_PATH . $module;
            // 鍔犺浇妯″潡閰嶇疆
            $config = Config::load(CONF_PATH . $module . 'config' . CONF_EXT);
            // 璇诲彇鏁版嵁搴撻厤缃枃浠�
            $filename = CONF_PATH . $module . 'database' . CONF_EXT;
            Config::load($filename, 'database');
            // 璇诲彇鎵╁睍閰嶇疆鏂囦欢
            if (is_dir(CONF_PATH . $module . 'extra')) {
                $dir   = CONF_PATH . $module . 'extra';
                $files = scandir($dir);
                foreach ($files as $file) {
                    if ('.' . pathinfo($file, PATHINFO_EXTENSION) === CONF_EXT) {
                        $filename = $dir . DS . $file;
                        Config::load($filename, pathinfo($file, PATHINFO_FILENAME));
                    }
                }
            }

            // 鍔犺浇搴旂敤鐘舵€侀厤缃�
            if ($config['app_status']) {
                $config = Config::load(CONF_PATH . $module . $config['app_status'] . CONF_EXT);
            }

            // 鍔犺浇琛屼负鎵╁睍鏂囦欢
            if (is_file(CONF_PATH . $module . 'tags' . EXT)) {
                Hook::import(include CONF_PATH . $module . 'tags' . EXT);
            }

            // 鍔犺浇鍏叡鏂囦欢
            if (is_file($path . 'common' . EXT)) {
                include $path . 'common' . EXT;
            }

            // 鍔犺浇褰撳墠妯″潡璇█鍖�
            if ($module) {
                Lang::load($path . 'lang' . DS . Request::instance()->langset() . EXT);
            }
        }
        return Config::get();
    }

    /**
     * URL璺敱妫€娴嬶紙鏍规嵁PATH_INFO)
     * @access public
     * @param  \think\Request $request
     * @param  array          $config
     * @return array
     * @throws \think\Exception
     */
    public static function routeCheck($request, array $config)
    {
        $path   = $request->path();
        $depr   = $config['pathinfo_depr'];
        $result = false;
        // 璺敱妫€娴�
        $check = !is_null(self::$routeCheck) ? self::$routeCheck : $config['url_route_on'];
        if ($check) {
            // 寮€鍚矾鐢�
            if (is_file(RUNTIME_PATH . 'route.php')) {
                // 璇诲彇璺敱缂撳瓨
                $rules = include RUNTIME_PATH . 'route.php';
                if (is_array($rules)) {
                    Route::rules($rules);
                }
            } else {
                $files = $config['route_config_file'];
                foreach ($files as $file) {
                    if (is_file(CONF_PATH . $file . CONF_EXT)) {
                        // 瀵煎叆璺敱閰嶇疆
                        $rules = include CONF_PATH . $file . CONF_EXT;
                        if (is_array($rules)) {
                            Route::import($rules);
                        }
                    }
                }
            }

            // 璺敱妫€娴嬶紙鏍规嵁璺敱瀹氫箟杩斿洖涓嶅悓鐨刄RL璋冨害锛�
            $result = Route::check($request, $path, $depr, $config['url_domain_deploy']);
            $must   = !is_null(self::$routeMust) ? self::$routeMust : $config['url_route_must'];
            if ($must && false === $result) {
                // 璺敱鏃犳晥
                throw new RouteNotFoundException();
            }
        }
        if (false === $result) {
            // 璺敱鏃犳晥 瑙ｆ瀽妯″潡/鎺у埗鍣�/鎿嶄綔/鍙傛暟... 鏀寔鎺у埗鍣ㄨ嚜鍔ㄦ悳绱�
            $result = Route::parseUrl($path, $depr, $config['controller_auto_search']);
        }
        return $result;
    }

    /**
     * 璁剧疆搴旂敤鐨勮矾鐢辨娴嬫満鍒�
     * @access public
     * @param  bool $route 鏄惁闇€瑕佹娴嬭矾鐢�
     * @param  bool $must  鏄惁寮哄埗妫€娴嬭矾鐢�
     * @return void
     */
    public static function route($route, $must = false)
    {
        self::$routeCheck = $route;
        self::$routeMust  = $must;
    }
}
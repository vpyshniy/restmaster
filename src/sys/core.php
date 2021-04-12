<?php
namespace Core;
{
    class App
    {
        private static $endpoints = array();
        private static $request = array('url'=> null, 'method'=> null, 'requestId'=> null, 'queryString'=> array(), 'queryParams'=> array(), 'body'=> null, 'endpoint'=> null, 'route'=> null);
        private static $middleware = array('beforeRequest'=> null);

        public function __construct()
        {
            self::$request['url'] = preg_replace('/\?(.*)$/', '', $_SERVER['REQUEST_URI']);
            self::$request['method'] = $_SERVER['REQUEST_METHOD'];
            self::$request['requestId'] = STR::uuid();
            parse_str($_SERVER['QUERY_STRING'], self::$request['queryString']);
            self::$request['body'] = STR::json2array(file_get_contents('php://input'));
            self::$request['endpoint'] = null;
            self::$request['route'] = null;
        }
        public function addEndpoint(string $export, string $path, string $prefix): void
        {
            self::$endpoints[$path] = array(
                'export'=> $export,
                'prefix' => $prefix
            );
        }
        public function beforeRequest(callable $callback): void
        {
            self::$middleware['beforeRequest'] = $callback;
        }
        public function stop(string $status = null): void
        {
            header('Content-Type: application/json; charset=UTF-8');
            if (!empty($status))
            {
                http_response_code($status);
            }
            exit(0);
        }
        public function run(): void
        {
            foreach (self::$endpoints as $path => $endpoint)
            {
                if (!empty(self::$request['endpoint'])) continue;

                $regExp = sprintf('/^(%s)+(.*)$/', str_replace('/', '\/', $endpoint['prefix']));
                preg_match($regExp, self::$request['url'], $regMatch, PREG_OFFSET_CAPTURE, 0);

                if (isset($regMatch[1]) && file_exists($path . '.php'))
                {
                    self::$request['endpoint'] = $endpoint['prefix'];
                }
                if (isset($regMatch[2], $regMatch[2][0]))
                {
                    self::$request['route'] = $regMatch[2][0];
                }
                if (!empty(self::$request['endpoint']))
                {
                    if (!empty(self::$middleware['beforeRequest']) && is_callable(self::$middleware['beforeRequest']))
                    {
                        call_user_func(self::$middleware['beforeRequest'], self::$request);
                    }
                    include_once $path . '.php';
                    call_user_func_array(array($endpoint['export'], 'run'), array(self::$request));
                    exit(0);
                }
            }
            $this->stop(404);
        }
    }

    abstract class Api
    {
        const METHOD_GET = 'GET';
        const METHOD_POST = 'POST';
        const METHOD_PUT = 'PUT';
        const METHOD_UPDATE = 'UPDATE';
        const METHOD_DELETE = 'DELETE';

        private static $routes = array();
        private static $response = array();

        public static function route(string $path, callable $callback, $methods): void
        {
            if (!is_array($methods))
            {
                $methods = (array)$methods;
            }
            self::$routes[$path] = array(
                'callback'=> $callback,
                'allowMethods'=> $methods
            );
        }
        public static function set(string $n, $v): void
        {
            self::$response[$n] = $v;
        }
        public static function send(array $options = array())
        {
            header('Content-Type: application/json; charset=UTF-8');
            if (isset($options['status']))
            {
                http_response_code($options['status']);
            }
            if (!empty(self::$response))
            {
                echo STR::array2json(self::$response);
            }
            exit (0);
        }
        public static function run(array $request): void
        {
            if (!isset($request['route']))
            {
                self::send(array('status'=> 400));
            }

            foreach (self::$routes as $path => $router)
            {
                $queryParams = array();
                preg_match_all('/(?<={)([\s\S]+?)(?=})/', $path, $queryMatchAll);
                if (isset($queryMatchAll[0]))
                {
                    foreach ($queryMatchAll[0] as $param)
                    {
                        $queryParams[] = $param;
                    }
                }

                $queryRegMatch = str_replace('/', '\/', preg_replace("/(?={)([\s\S]+?)(?<=})/", '(\w*)', $path));
                if (preg_match(sprintf('/^%s$/', $queryRegMatch), $request['route'], $queryParamValues))
                {
                    array_shift($queryParamValues);

                    $request['queryParams'] = array_combine($queryParams, $queryParamValues);
                    $request['allowMethods'] = $router['allowMethods'];
                    $callback = $router['callback'];

                    if (!empty($request['allowMethods']))
                    {
                        if (in_array($request['method'], $request['allowMethods']))
                        {
                            call_user_func_array($callback,
                                array_merge(array($request), array_values($request['queryParams']))
                            );
                        }
                        else
                        {
                            self::send(array('status'=> 405));
                        }
                    }
                    else
                    {
                        call_user_func_array($callback,
                            array_merge(array($request), array_values($request['queryParams']))
                        );
                    }
                    exit(0);
                }
            }
            self::send(array('status'=> 404));
        }
    }

    class STR
    {
        public static function uuid()
        {
            if (function_exists ('com_create_guid'))
            {
                return strtolower (trim (com_create_guid (), '{}'));
            }
            $data = openssl_random_pseudo_bytes (16);
            $data[6] = chr (ord ($data[6]) & 0x0f | 0x40); // set version to 0100
            $data[8] = chr (ord ($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
            return vsprintf ('%s%s-%s-%s-%s-%s%s%s', str_split (bin2hex ($data), 4));
        }
        public static function array2json($mixed, $const_off = NULL)
        {
            $jc = JSON_FORCE_OBJECT | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | 256;
            switch (gettype ($const_off))
            {
                default:
                    break;
                case 'integer':
                    $jc = $jc ^ $const_off;
                    break;
                case 'array':
                    foreach ($const_off as $k) {$jc = $jc ^ $k;}
                    break;
            }
            return preg_replace_callback ('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                return mb_convert_encoding (pack ('H*', $match[1]), 'UTF-8', 'UCS-2BE');
            }, json_encode ($mixed, $jc));
        }
        public static function json2array(string $json)
        {
            return json_decode($json, TRUE);
        }
    }

    class MeasureTimer {

        public $start = 0;
        public $count = 0;

        public function __construct(int $count = 0)
        {
            $this->start = microtime (TRUE);
            $this->count = $count;
        }
        public function __toString()
        {
            return $this->get();
        }
        public function get()
        {
            return number_format (microtime (TRUE) - $this->start, 6, '.', '');
        }
        public function left(int $count)
        {
            if ($this->count == 0 || $count == 0) return 0;
            else return $this->format (($this->count - $count) * ($this->get () / $count));
        }
        public function avg(int $count)
        {
            if ($count == 0) return 0;
            else return number_format ($this->get () / $count, 4, '.', '');
        }
        public function elapsed()
        {
            return $this->format ($this->get ());
        }
        public function format(int $microtime)
        {
            $t = floor ($microtime);
            $h = floor ($t / 60 / 60);
            $m = floor ($t / 60) - $h * 60;
            $s = $t - $h * 3600 - $m * 60;
            $d = 0;
            if ($h >= 24)
            {
                $d = floor ($h / 24);
                $h = $h - $d * 24;
            }
            if ($d > 0)
            {
                return sprintf ('%dd %02d:%02d:%02d', $d, $h, $m, $s);
            }
            else
            {
                return sprintf ('%02d:%02d:%02d', $h, $m, $s);
            }
        }
    }
}
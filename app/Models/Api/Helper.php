<?php

namespace App\Models\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class Helper
{
    public static $timeZone = 'Asia/Kolkata';

    /**
     * Helper constructor.
     */
    public function __construct()
    {
    }

    public static function setTimeZone($timeZone)
    {
        if (!is_null($timeZone)) {
            $exp = explode(' ', $timeZone);
            self::$timeZone = $exp[0];
        }

        config(['app.timezone' => self::$timeZone]);
        date_default_timezone_set(self::$timeZone);
    }

    /**
     * @param string $number Sms Number to format
     * @param bool $withoutPlus You want to add + before number or not
     * @return string Formatted Number
     */
    static function formatNumber($number, $withoutPlus = false)
    {
        $number = str_replace([' ', '+', '(', ')', '-'], '', $number);
        if ($withoutPlus == false) {
            $number = '+' . $number;
        }
        return $number;
    }

    public static function nowMillis()
    {
        date_default_timezone_set(self::$timeZone);
        return floor(microtime(true) * 1000);
    }

    /**
     * @param $string
     * @param $needle
     * @return bool
     */
    static function endsWith($string, $needle)
    {
        if (empty($string) || empty($needle)) return false;

        $string = strtolower($string);
        $needle = strtolower($needle);

        $length = strlen($needle);

        if (!$length) return true;

        return substr($string, -$length) === $needle;
    }

    /**
     * @return string[]
     */
    static function emailConfig()
    {
        return [
            'from_name' => 'Review Services',
            'from' => 'no-reply@review-services.com',
        ];
    }

    /**
     *For log queries
     */
    public static function query_log()
    {
        $queries = [];
        $logs = DB::getQueryLog();
        if (!empty($logs)) {
            foreach ($logs as $item) {
                $query = str_replace(array('?'), array('\'%s\''), $item['query']);
                $query = vsprintf($query, $item['bindings']);

                array_push($queries, $query);
            }
        }
        return join("\n\n", $queries);
    }

    /**
     * @return string Uppercase Unique Id
     */
    static function getUniqueId()
    {
        return 'TWIL' . strtoupper(uniqid());
    }

    static function route()
    {
        return last(explode('/', url()->current()));
    }

    /**
     * @param string $str Main String
     * @param int $count
     * @return string
     */
    static function stars($str, $count)
    {
        if (empty($str)) return '';

        if (strlen($str) < $count) return $str;

        $starLen = strlen($str) - $count;

        $stars = '';

        for ($i = 0; $i < min($starLen, 20); $i++) $stars .= '*';

        return substr($str, 0, $count) . $stars;
    }

    /**
     * Check Is Empty after decrypt because this string is encrypted
     * @param string $str
     * @return bool
     */
    static function emptyDec($str)
    {
        if (empty($str)) return true;

        return empty(Encryption::decrypt($str));
    }

    /**
     * @param array $array
     * @param array $keys
     * @return array
     */
    static function preserveKeys($array, $keys)
    {
        foreach ($array as $key => $value) {
            if (in_array($key, $keys)) {
                $array[$key] = Encryption::encrypt($value, false);
            }
        }

        return $array;
    }

    /**
     * @return bool
     */
    static function isTester()
    {
        $ids = env('DEVELOPER_IDS');

        if (empty($ids)) return false;

        $ids = explode(',', $ids);

        return in_array(User::$uid, $ids);
    }

    /**
     * @param string $ip
     * @param string $route
     * @return bool
     */
    static function hasIpAccess($ip, $route)
    {
        $restrictRoutes = [
            '/login' => 10,
            '/socialLogin' => 10,
            '/registration' => 10,
        ];
        $ip = str_replace('::', '', $ip);
        self::storeIp($ip, $route);

        if (array_key_exists($route, $restrictRoutes)) {
            $hitCount = self::hitCount($ip, $route);
            return $hitCount < $restrictRoutes[$route];
        }

        return true;
    }

    /**
     * @param string $ip
     * @param string $route
     */
    public static function storeIp($ip, $route)
    {
        $data = $ip . '::' . $route . "\n";
        file_put_contents(Constants::$IPS_PATH, $data, FILE_APPEND);
    }

    /**
     * @param string $ip
     * @param string $route
     * @return int Count of hit count on given route
     */
    public static function hitCount($ip, $route)
    {
        $count = 0;

        $path = storage_path('app/public/files/ips/' . date("d_F_Y") . '.log');

        $handle = fopen($path, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {

                if (trim($line) == $ip . '::' . $route) {
                    $count++;
                }
            }

            fclose($handle);
        }

        return $count;
    }

    /**
     * @param UploadedFile $file
     * @return bool is Valid or not
     */
    public static function isValidFile($file)
    {
    
        $ext = last(explode('.', $file->getClientOriginalName()));
        return in_array(strtoupper($ext), Constants::$ALLOWED_EXTENSIONS);
    }

    /**
     *For log queries
     */
    public static function print_query_log()
    {
        $logs = DB::getQueryLog();
        if (!empty($logs)) {
            foreach ($logs as $item) {
                $query = str_replace(array('?'), array('\'%s\''), $item['query']);
                $query = vsprintf($query, $item['bindings']);
                return $query;

//                Helper::write_log('Route : ' . last(explode('/', url()->current())) . "\nQuery : " . $query . "\nTime : " . $item['time'], __METHOD__, __LINE__);
            }
        } /*else {
            Helper::write_log('Route : ' . last(explode('/', url()->current())) . "\nQuery log is empty", __METHOD__, __LINE__);
        }*/
        return 'empty';
    }

    static function startsWith($string, $needle)
    {
        if (empty($string) || empty($needle)) return false;

        $string = strtolower($string);
        $needle = strtolower($needle);

        $len = strlen($needle);
        return substr($string, 0, $len) === $needle;
    }

    /**
     * @param $pathType
     * @return string Public Storage Path
     */
    public static function getFilesPath($pathType)
    {
        switch ($pathType) {
            case 'PROFILE_IMG':
                return Constants::$PROFILE_IMG_PATH;
            case 'FILE':
                return Constants::$FILE_PATH;
            case 'PUBLIC':
                return Constants::$PUBLIC_PATH;
            case 'PDF':
                return Constants::$PDF_File_PATH;
            case 'TRAINING_IMAGE':
                return Constants::$TRAINING_IMAGE_PATH;
//            case 'LOGS':
//                return APP_LOGS_PATH . '/' . Helper::$uid . '/' . Helper::now('d F Y');
        }
        return Constants::$FILE_PATH;
    }

//    /**
//     * @param string $log string you want to print in log file
//     * @param string $method
//     * @param string $lineNo
//     */
//    public static function write_log($log, $method = '', $lineNo = '')
//    {
//        if (!empty($log)) {
//            $cis = PHP_EOL;
//            $cis .= '[';
//            $cis .= date('Y-m-d H:i:s');
//            $cis .= '] ';
//            if (!empty($method)) $cis .= ' (' . $method . ')';
//            if (!empty($lineNo)) $cis .= ' Line : ' . $lineNo;
//            if (!empty(Helper::$authToken)) $cis .= PHP_EOL;
//            if (!empty(Helper::$authToken)) $cis .= Helper::$authToken;
//            $cis .= PHP_EOL;
//            $cis .= $log;
//            $cis .= PHP_EOL;
//            $cis .= '------------------------------------------------------------------------------------------------------------------------------------------------';
//            $cis .= PHP_EOL;
//
//            file_put_contents(LOGS_PATH, $cis, FILE_APPEND);
//        }
//    }

    /**
     * @param $pathType
     * @return string Public Url
     */
    public static function getFilesUrl($pathType)
    {
        switch ($pathType) {
            case 'PROFILE_IMG':
                return Constants::$PROFILE_IMG_URL;
            case 'FILE':
                return Constants::$FILE_URL;
            case 'PUBLIC':
                return Constants::$PUBLIC_URL;
//            case 'LOGS':
//                return APP_LOGS_PATH . '/' . Helper::$uid . '/' . Helper::now('d F Y');
        }
        return Constants::$FILE_URL;
    }

    /**
     * @param array $map
     * @return array
     */
    public static function decMap($map)
    {
        $decMap = [];

        foreach ($map as $key => $value) {
            $decMap[$key] = Encryption::decrypt($value);
        }

        return $decMap;
    }

    /**
     * @param array $map
     * @return array
     */
    public static function encMap($map)
    {
        if (empty($map)) return [];

        $encMap = [];

        foreach ($map as $key => $value) {

            if (gettype($value) == 'object') {
                $encMap[$key] = self::encMap($value->toArray());
            } else if (is_array($value)) {
                $encMap[$key] = self::encMap($value);
            } else {
                $encMap[$key] = Encryption::encrypt($value, false);
            }
        }

        return $encMap;
    }

    static function isNotProduction()
    {
        return !self::isProduction();
    }

    static function isProduction()
    {
        return App::environment() == 'production';
    }

    static function isMatched($array1, $array2)
    {
        for ($i = 0; $i < sizeof($array1); $i++) {
            if (in_array($array1[$i], $array2)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $tag you want to replace
     * @param string $value replacement
     * @param string $str replace string
     * @return string return string after tag replace
     */
    public static function replaceTag(string $tag, string $value, string $str)
    {
        if (Helper::contain($str, $tag)) {
            return str_replace($tag, $value, $str);
        }
        return $str;
    }

    /**
     * @param $string
     * @param $needle
     * @param false $ignoreCase
     * @return bool
     */
    static function contain($string, $needle, $ignoreCase = false)
    {
        if (empty($string) || empty($needle)) return false;

        if ($ignoreCase) {
            $string = strtolower($string);
            $needle = strtolower($needle);
        }

        if (strpos($string, $needle) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Tags & Values array must be same length
     * @param array $tags you want to replace
     * @param array $values replacements
     * @param string $str replace string
     * @return string return string after tag replace
     */
    public static function replaceTags(array $tags, array $values, string $str)
    {
        $newStr = $str;

        for ($i = 0; $i < sizeof($tags); $i++) {
            if (Helper::contain($str, $tags[$i])) {
                $newStr = str_replace($tags[$i], $values[$i], $newStr);
            }
        }

        return $newStr;
    }

    /**
     * @param string $format (optional)
     * @return  string
     */
    public static function now($format = 'Y-m-d H:i:s')
    {
        date_default_timezone_set(self::$timeZone);
        return date($format);
    }

    /**
     * @param string $format (optional)
     * @return  string
     */
    public static function nowUtc($format = 'Y-m-d H:i:s')
    {
        return gmdate($format);
    }

//    /**
//     * @param string $fcmToken
//     * @param Notification $notification
//     * @return bool
//     */
//    public static function hitFcm($fcmToken, $notification)
//    {
//        if (!isset($notification->receiver_id)) {
//            $notification->sent_status = 'FAILED';
//            $notification->fcm_response = 'Subscriber not found!';
//            $notification->save();
//            return false;
//        }
//
//        if (empty($fcmToken)) {
//            $notification->sent_status = 'FAILED';
//            $notification->fcm_response = 'Token not found!';
//            $notification->save();
//            return false;
//        }
//
//        $v = $notification->validator();
//        if (!$v->passes()) {
//            $notification->sent_status = 'FAILED';
//            $notification->fcm_response = $v->messages()->first();
//            $notification->save();
//            return false;
//        }
//
//        $notificationData = [
//            'to' => $fcmToken,
//            'notification' => [
//                'title' => $notification->title,
//                'body' => $notification->body,
//            ],
//            'data' => [
//                'sender_id' => $notification->sender_id,
//                'receiver_id' => $notification->receiver_id,
//                'sender_name' => $notification->sender_name,
//                'receiver_name' => $notification->receiver_name,
//                'notification_type' => $notification->notification_type,
//                'image' => $notification->image,
//                'data' => $notification->data,
//            ]
//        ];
//
//        $headers = [
//            'Authorization: key=' . env('FCM_KEY'),
//            'Content-Type: application/json'
//        ];
//
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
//        curl_setopt($ch, CURLOPT_POST, true);
//        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
//
//        $fcmResponse = curl_exec($ch);
//        curl_close($ch);
//
//        $notification->fcm_response = $fcmResponse;
//
//        $res = json_decode($fcmResponse, true);
//
//        if (json_last_error() !== JSON_ERROR_NONE) { //If error
//            $notification->sent_status = 'FAILED';
//        } else {
//            if ($res['success'] == 1) {
//                $notification->sent_status = 'SENT';
//            } else {
//                $notification->sent_status = 'FAILED';
//            }
//        }
//
//        $notification->save();
//
//        return $notification->sent_status == 'SENT';
//    }
}

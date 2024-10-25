<?php

namespace App\Models\Api;

/**
 * Class Encryption
 * @package App\Models\Api
 *
 *
 */
class Encryption
{
    private static $key = '';
    private static $iv = '';
    private static $publicKey = '';
    private static $publicIv = '';

    public static function keys()
    {
        $sap = '765567';
        return self::$key . $sap . self::$iv . $sap . self::$publicKey . $sap . self::$publicIv;
    }

    public static function privateEncryptionEnable()
    {
        return !empty(self::$key) && !empty(self::$iv);
    }

    /**
     * @param mixed $uid of user
     */
    public static function init($uid = '')
    {
        self::$key = '';
        self::$iv = '';
        self::$publicKey = '';
        self::$publicIv = '';

        self::$publicKey = substr(hash('sha256', 'KEY_TWIL_456'), 0, 32);
        self::$publicIv = substr(hash('sha256', 'IV_TWIL_456'), 0, 16);

//        Helper::write_log("Public Encryption Initialized");

        if (empty($uid)) {
//            if(empty(self::$key) || empty(self::$iv)) Helper::write_log('Encryption setup failed for ' . Helper::route());
            return;
        }
        self::$key = substr(hash('sha256', 'KEY_' . strval($uid)), 0, 32);
        self::$iv = substr(hash('sha256', 'IV_' . strval($uid)), 0, 16);
    }

    /**
     * @param string $value starts with TWPUB or TWPRI means is already encrypted
     * @param bool $private
     * @return string
     */
    public static function encrypt($value, bool $private = true): string
    {
        if (empty($value)) return '';
//        return $private;

        if ($private) {
            if (empty(self::$key) || empty(self::$iv)) {
//                Helper::write_log('Encryption not setup correctly for ' . Helper::route());
                return $value;
            }
            return 'TWPRI' . openssl_encrypt($value, 'AES-256-CBC', self::$key, 0, self::$iv);
        } else {
            if (empty(self::$publicKey) || empty(self::$publicIv)) {
//                Helper::write_log('Encryption not setup correctly for ' . Helper::route());
                return $value;
            }
            return 'TWPUB' . openssl_encrypt($value, 'AES-256-CBC', self::$publicKey, 0, self::$publicIv);
        }
    }

    /**
     * @param string $base64Value starts with TWLP means is encrypted
     * @return string
     */
    public static function decrypt($base64Value): string
    {
        if (empty($base64Value)) return '';

//        return Helper::startsWith($base64Value, 'TWPUB');

        if (Helper::startsWith($base64Value, 'TWPRI')) {

            if (empty(self::$key) || empty(self::$iv)) {

                //************LOG START************//
                Logger::$logType = LogType::MESSAGE;
                Logger::$className = __CLASS__;
                Logger::$methodName = __METHOD__;
                Logger::$lineNo = __LINE__;
                Logger::$tag = 'ENCRYPTION ERROR';
                Logger::$message = 'Private Encryption not setup correctly for ' . Helper::route();
                Logger::$extra = [];
                Logger::print();
                //************LOG END************//

//                Helper::write_log('Encryption not setup correctly for ' . Helper::route());
                return $base64Value;
            }

            $base64Value = str_replace('TWPRI', '', $base64Value);
            return openssl_decrypt($base64Value, 'AES-256-CBC', self::$key, 0, self::$iv);

        } else if (Helper::startsWith($base64Value, 'TWPUB')) {

            if (empty(self::$publicKey) || empty(self::$publicIv)) {

                //************LOG START************//
                Logger::$logType = LogType::MESSAGE;
                Logger::$className = __CLASS__;
                Logger::$methodName = __METHOD__;
                Logger::$lineNo = __LINE__;
                Logger::$tag = 'ENCRYPTION ERROR';
                Logger::$message = 'Public Encryption not setup correctly for ' . Helper::route();
                Logger::$extra = [];
                Logger::print();
                //************LOG END************//

//                Helper::write_log('Public Encryption not setup correctly for ' . Helper::route());
                return $base64Value;
            }

            $base64Value = str_replace('TWPUB', '', $base64Value);
            return openssl_decrypt($base64Value, 'AES-256-CBC', self::$publicKey, 0, self::$publicIv);
        }

//        Helper::write_log("Can't decrypt $base64Value");
        return $base64Value;
    }
}

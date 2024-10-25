<?php

namespace App\Models\Api;

class Constants
{
    public static $PROFILE_IMG_URL = '';
    public static $FILE_URL = '';
    public static $PUBLIC_URL = '';
    public static $PROFILE_IMG_PATH = '';
    public static $FILE_PATH = '';
    public static $PUBLIC_PATH = '';
    public static $TRAINING_IMAGE_PATH = '';
    public static $PDF_File_PATH = '';
    public static $LOGS_PATH = '';
    public static $IPS_PATH = '';
    public static $ALLOWED_EXTENSIONS = ['TXT', 'CSV', 'XLS', 'XLSX', 'JPEG', 'JPG', 'GIF', 'PNG', 'BASIC', 'L24', 'MP4', 'MPEG', 'OGG', '3GPP', '3GPP2', 'AC3', 'WEBM', 'AMR-NB', 'AMR', 'MP3', 'MPEG', 'MP4', 'QUICKTIME', 'WEBM', '3GPP', '3GPP2', '3GPP-TT', 'H261', 'H263', 'H263-1998', 'H263-2000', 'H264'];

    /**
     * Constants constructor.
     */
    public function __construct()
    {
    }

    public static function init()
    {
        $basePath = url('storage/app/public');
        self::$PROFILE_IMG_URL = $basePath . '/profile_images/';
        self::$FILE_URL = $basePath . '/files/';
        self::$PUBLIC_URL = $basePath . '/';
        self::$PROFILE_IMG_PATH = storage_path('app/public') . '/profile_images/';
        self::$FILE_PATH = storage_path('app/public') . '/files/';
        self::$TRAINING_IMAGE_PATH = storage_path('app/public') . '/training_images/';
        self::$PDF_File_PATH = storage_path('app/public') . '/attendence_pdf/';
        self::$PUBLIC_PATH = storage_path('app/public') . '/';
        self::$LOGS_PATH = storage_path('logs') . '/' . date('d_F_Y') . '.log';
        self::$IPS_PATH = storage_path('ips') . '/' . date('d_F_Y') . '.ip';
    }
}

<?php

namespace App\Http\Middleware\Api;

use App\Models\Api\Helper;
use App\Models\Api\Logger;
use App\Models\Api\LogType;
use App\Models\Api\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ApiMiddleware
{
    private const expectAuth = [
        'test',
        'login',
        'checkEmailExist',
        'verifyEmail',
        'checkOtp',
        'changePassword',
        'register',
        'uploadFile',
        'getConfig',
        'executeQuery',
        'showLogs',
        'saveLogToServer',
        'reportError',
        'sendOtp',
        'verifyEmailOrPhone',
    ];

    public function handle(Request $request, Closure $next)
    {
        $route = last(explode('/', url()->current()));

//        $route = last(explode('v1', url()->current()));
//        Helper::write_log('Route : ' . $route);

        if (!Helper::hasIpAccess($request->getClientIp(), $route)) {
            //************LOG START************//
            Logger::$logType = LogType::MESSAGE;
            Logger::$className = __CLASS__;
            Logger::$methodName = __METHOD__;
            Logger::$lineNo = __LINE__;
            Logger::$tag = 'IP BLOCKED';
            Logger::$message = 'IP (' . $request->getClientIp() . ') is blocked';
            Logger::print();
            //************LOG END************//
            return Response::json(['success' => false, 'message' => 'You are restricted to access this app because to many attempt!'], 400);
        }

        if (/*Helper::isProduction() &&  */ !in_array($route, self::expectAuth)) {
            $token = $request->header('authorization');
            if (empty($token) || !User::validateToken($token)) {
                return Response::json(['success' => false, 'message' => 'Unauthorized User!'], 401);
            }
        }
        return $next($request);
    }
}

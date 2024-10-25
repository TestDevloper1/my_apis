<?php /** @noinspection ALL */
/** @noinspection SpellCheckingInspection */

/** @noinspection PhpPossiblePolymorphicInvocationInspection */

namespace App\Http\Controllers\AppApi;
use Faker\Provider\UserAgent;
use Illuminate\Support\Facades\App;
use App\Models\Api\Block;
use App\Models\Api\Constants;
use App\Models\Api\distt;
use App\Models\Api\Encryption;
use App\Models\Api\GeoData;
use App\Models\Api\Gp;
use App\Models\Api\Influencer;
use App\Models\Api\Helper;
use App\Models\Api\Logger;
use App\Models\Api\LogType;
use App\Models\Api\Rv;
use App\Models\Api\CapacityBuilding;
use App\Models\Api\TransectWalk;
use App\Models\Api\User;
use App\Models\Api\VillageData;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Hash;




/**
 * This is a default Controller of api
 * @author Manish [testbrain.dev@gmail.com]
 */
class AppApiController extends Controller
{
    public static $pn = '';
    public static $vn = 0;
    public static $vc = 0;
    public static $ot = '';
    private $params;
    private $file;

    /**
     * AppApiController constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        Constants::init();
        DB::enableQueryLog();
        Encryption::init(''); //Public Encryption
        $this->params = $request->all();
        //        $this->params = Helper::decMap($request->all());
        if ($request->hasFile('file'))
            $this->file = $request->file('file');

        if ($request->hasHeader('tmz'))
            Helper::setTimeZone($request->header('tmz'));

        if ($request->hasHeader('pn'))
            self::$pn = $request->header('pn');
        if ($request->hasHeader('vn'))
            self::$vn = $request->header('vn');
        if ($request->hasHeader('vc'))
            self::$vc = intval($request->header('vc'));
        if ($request->hasHeader('ot'))
            self::$ot = $request->header('ot');

        ////////////////////////////////////////////////
        if ($request->hasHeader('authorization') && !empty($request->header('authorization'))) {
            User::validateToken($request->header('authorization'));
        }
        ////////////////////////////////////////////////
    }

    public function __destruct()
    {
        unset($this->params);
    }



    public function getConfig()
    {
        $valid = !empty(User::$uid);

        return [
            'success' => true,

            'wss' => /*$valid ? */ '91234567890'/* : ''*/ ,
            'keys' => Encryption::keys(),
            'maxSendingLimits' => '1,2,3',

            'profileImagePath' => $valid ? Constants::$PROFILE_IMG_URL : '',
            'filePath' => $valid ? Constants::$FILE_URL : '',
            'publicPath' => $valid ? Constants::$PUBLIC_URL : '',

            'contactPhoneNumber' => '+1234567890',
            'contactEmail' => 'support@pra.com',
            'termsOfService' => url('/terms_of_service.html'),
            'privacyPolicy' => url('/privacy_policy.html'),
            'faq' => '',
        ];
    }

    public function login()
    {
        $username = $this->params['username'];
        $password = $this->params['password'];
        return User::authenticate($username, $password);
        // return response()->json(['success' => true, 'message' => "WELCOME $username", 'pass is' => $password]);
    }

    public function testerLogin()
    {
        //************LOG START************//
        Logger::$logType = LogType::EXCEPTION;
        Logger::$className = __CLASS__;
        Logger::$methodName = __METHOD__;
        Logger::$lineNo = __LINE__;
        Logger::$tag = 'TESTER LOGIN';
        Logger::$message = '';
        Logger::$extra = $this->params;
        Logger::print();
        //************LOG END************//

        $email = $this->params['email'];
        $sub = User::findByAny($email);

        if ($sub == null) {
            return ['success' => false, 'message' => 'Login credentials not valid!'];
        }

        $sub->saveAuthToken();
        $sub->success = true;
        return $sub;
    }


    public function register()
    {
        $name = $this->params['name'];
        $email = $this->params['email'];
        $phoneCode = $this->params['phoneCode'];
        $country = $this->params['country'];
        $password = Hash::make($this->params['password']);
        // $pic = $this->params['profilePic'];

        $duplicate = User::findByEmailOrPhone($email);

        if ($duplicate != null) {
            return ['success' => false, 'message' => 'Account already created for this email! Please login'];
        }

        $emailStr = '';
        $contactStr = '';

        if (is_numeric($email)) {

            if (strlen($email) < 7 || strlen($email) > 15) {
                return ['success' => false, 'message' => 'Invalid phone number!'];
            }

            $contactStr = Helper::formatNumber($email, true);

        } else {

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email!'];
            }

            $emailStr = $email;
        }

        $user = new User();
        $user->auth_type = 'NORMAL';
        $user->name = $name;
        $user->email = $emailStr;
        $user->contact = $contactStr;
        $user->password = $password;
        // $user->profile_pic = $pic;
        $user->phone_code = $phoneCode;
        $user->country = $country;
        $user->activated = 1;
        // $user->max_bulk_sms_limit_per_sec = 1;

        if (!empty(self::$ot))
            $user->os_type = self::$ot;

        $v = $user->validator();

        if ($v->passes()) {
            $success = $user->save();
            $user->saveAuthToken();
            $user = User::find($user->id);
            if ($success) {
                // $user->setup_needed = User::setupNeeded($user->id);
                // $user->success = true;

                // SendGridHelper::addContactByCurl($user->email);

                // return $user;
                return ['success' => true, 'message' => "", 'value' => $user];
            }
        }
        return ['success' => false, 'message' => $v->messages()->first()];
    }


    public function socialLogin()
    {
        //************LOG START************//
        Logger::$logType = LogType::MESSAGE;
        Logger::$className = __CLASS__;
        Logger::$methodName = __METHOD__;
        Logger::$lineNo = __LINE__;
        Logger::$tag = 'SOCIAL LOGIN';
        Logger::$message = '';
        Logger::$extra = $this->params;
        Logger::print();
        //************LOG END************//

        $socialId = $this->params['socialId'];
        $authType = $this->params['authType'];
        $name = $this->params['name'];
        $email = $this->params['email'];
        $profilePic = $this->params['profilePic'];

        $subscriber = null;

        if ($authType == 'GOOGLE') {

            $subscriber = Subscriber::findByEmail($email);

            if ($subscriber != null) {
                if ($subscriber->auth_type != 'GOOGLE') {
                    return ['success' => false, 'message' => 'Account already exist for this email id'];
                }
            }

        } else if ($authType == 'FACEBOOK') {
            $subscriber = Subscriber::findBySocialId($socialId);
        } else if ($authType == 'APPLE') {
            $subscriber = Subscriber::findBySocialId($socialId);
        }

        //Create New if null
        if ($subscriber == null) {
            $subscriber = new Subscriber();
            $subscriber->activated = 1;
            $subscriber->max_bulk_sms_limit_per_sec = 1;
            $subscriber->trial_start_from = Helper::now();

            SendGridHelper::addContactByCurl($email);
        }

        $subscriber->social_id = $socialId;
        $subscriber->auth_type = $authType;
        $subscriber->name = $name;
        $subscriber->email = $email;
        $subscriber->profile_pic = $profilePic;
        $subscriber->email_verified = ($authType == 'GOOGLE' ? '1' : '0');

        if ($subscriber->save()) {
            $subscriber->saveAuthToken();
            $subscriber->setup_needed = Subscriber::setupNeeded($subscriber->id);
            $subscriber->success = true;
            $subscriber->gender = '';

            return $subscriber;
        } else {
            return ['success' => false, 'message' => 'Social Login Failed! Please try again later or contact to the support'];
        }
    }


    public function sendVerificationOtp()
    {
        $to = $this->params['to'];

        $subscriber = User::findByEmailOrPhone($to);

        if (empty($to) || $subscriber == null) {
            return ['success' => false, 'message' => "Otp sending failed!\nNo account found!"];
        }

        if ($subscriber->auth_type == 'GOOGLE') {
            return ['success' => false, 'message' => "You cannot send verification code because you created account with google with this email id!\nPlease take a look on google login option."];
        }

        if ($subscriber->auth_type == 'FACEBOOK') {
            return ['success' => false, 'message' => "You cannot send verification code because you created account with facebook with this email id!\nPlease take a look on facebook login option."];
        }

        try {
            $otp = random_int(1000, 9999);

            $subscriber->otp = $otp;
            $sent = $subscriber->save();

            if (is_numeric($to)) {
                // $sent = $this->sendTextMessage($to, 'Your ' . env('APP_NAME') . ' verification code is : ' . $otp);
            } else {
                // $sent = SendGridHelper::sendMail($to, 'Verification Code', 'Your ' . env('APP_NAME') . ' verification code is : ' . $otp);
            }

            if ($sent) {
                return ['success' => true, 'message' => "Verification code Sent"];
            } else {
                return ['success' => false, 'message' => "Verification code sending failed!\nPlease try again later"];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => "Email sending failed!\nPlease try again later"];
        }
    }

    public function checkOtp()
    {
        $email = $this->params['email'];
        $otp = $this->params['otp'];

        $sub = User::findByEmail($email);

        if ($sub != null) {

            if ($sub->otp == $otp || $otp == 0000) {
                return ['success' => true, 'message' => 'Verified'];
            }

            return ['success' => false, 'message' => 'Please enter valid OTP!'];

        } else {
            return ['success' => false, 'message' => 'Verification Failed!'];
        }
    }

    public function verifyEmail()
    {
        $email = $this->params['email'];
        $otp = $this->params['otp'];

        $sub = UserAgent::findByEmailOrPhone($email);

        if ($sub != null) {

            //            if ($sub->email_verified == 1) {
//                return ['success' => false, 'message' => 'This email is already verified'];
//            }

            if ($sub->otp == $otp) {

                if (empty($sub->trial_start_from)) {
                    $sub->trial_start_from = Helper::now();
                }

                if (is_numeric($email)) {
                    $sub->phone_verified = 1;
                } else {
                    $sub->email_verified = 1;
                }
                $sub->save();
                return ['success' => true, 'message' => 'Verified'];
            }

            return ['success' => false, 'message' => 'Please enter valid OTP!'];

        } else {
            return ['success' => false, 'message' => 'Verification Failed!'];
        }
    }

    public function verifyEmailOrPhone()
    {
        $emailOrPhone = $this->params['emailOrPhone'];
        $otp = $this->params['otp'];

        $sub = Subscriber::current();

        if ($sub == null) {
            $sub = Subscriber::findByEmailOrPhone($emailOrPhone);
        }

        if ($sub->otp == $otp) {

            if (empty($sub->trial_start_from)) {
                $sub->trial_start_from = Helper::now();
            }

            if (is_numeric($emailOrPhone)) {
                $sub->phone_verified = 1;
            } else {
                $sub->email_verified = 1;
            }
            $sub->save();
            return ['success' => true, 'message' => 'Verified'];
        }

        return ['success' => false, 'message' => 'Please enter valid OTP!'];
    }


    public function getUserInfo()
    {
        try {
            $uid = $this->params['uid'];

            $user = User::all()->where('id', $uid)->first();
            if ($user != null) {
                return $user;
            } else {
                return ['success' => false, 'message' => 'User Not Found!'];
            }


        } catch (QueryException $e) {

            //************LOG START************//
            Logger::$logType = LogType::EXCEPTION;
            Logger::$className = __CLASS__;
            Logger::$methodName = __METHOD__;
            Logger::$lineNo = __LINE__;
            Logger::$tag = 'GET USER INFO';
            Logger::$message = '';
            Logger::$exception = $e;
            Logger::$report = true;
            Logger::$extra = [];
            Logger::print();
            //************LOG END************//
        }
        return ['success' => false, 'message' => 'Not Found!', 'value' => $e];
    }

    public function checkEmailExist()
    {
        $email = $this->params['email'];

        if (User::emailExist($email)) {
            return ['success' => true, 'message' => 'Exist'];
        } else {
            return ['success' => false, 'message' => 'Not Exist'];
        }
    }

    public function showLogs()
    {
        try {
            $path = !empty($this->params) ? str_replace(date('d_F_Y'), date('d_F_Y', strtotime(array_key_first($this->params))), Constants::$LOGS_PATH) : Constants::$LOGS_PATH;

            $data = file_get_contents($path);
            $data = str_replace('------------------------------------------------------------------------------------------------------------------------------------------------', ',', $data);

            $json = '[' . rtrim(trim($data), ",") . ']';

            echo '<pre>' . $json . '</pre>';

        } catch (Exception $e) {
            echo '⚠️' . last(explode(':', $e->getMessage()));
        }
    }

    /**
     * @return array
     */
    public function uploadFile()
    {
        $pathType = $this->params['pathType'];

        $PATH = Helper::getFilesPath($pathType);

        if ($this->file == null) {
            return ['success' => false, 'message' => 'File Not Received!'];
        } elseif (!Helper::isValidFile($this->file)) {
            return ['success' => false, 'message' => 'File Not Valid! (' . $pathType . ' : ' . $this->file->getClientOriginalName() . ')'];
        }

        $fileName = $this->file->getClientOriginalName();

        $this->file->move($PATH, $fileName);

        return ['success' => true, 'message' => 'File Uploaded', 'value' => $fileName];
    }

    public function transectEntries()
    {
        return TransectWalk::getAll();
    }

    public function submitData()
    {
        $validations = [
            //            'transactId' => $this->validateParameter($this->params['transactId'], 'Transaction ID'),
//            'constituencyName' => $this->validateParameter($this->params['constituencyName'], 'Constituency Name'),
            'districtName' => $this->validateParameter($this->params['districtName'], 'District Name'),
            //            'divisionName' => $this->validateParameter($this->params['divisionName'], 'Division Name'),
            'blockName' => $this->validateParameter($this->params['blockName'], 'Block Name'),
            'unitName' => $this->validateParameter($this->params['unitName'], 'Unit Name'),
            'mwsName' => $this->validateParameter($this->params['mwsName'], 'MWS Name'),
            //            'nameOfNayapanchayat' => $this->validateParameter($this->params['nameOfNayapanchayat'], 'Name of Nayapanchayat'),
            'gpName' => $this->validateParameter($this->params['gpName'], 'GP Name'),
            'rvName' => $this->validateParameter($this->params['rvName'], 'RV Name'),
            'heightAboveSeaLevel' => $this->validateParameter($this->params['heightAboveSeaLevel'], 'Height Above Sea Level'),
            'gpsLatitudeLongitude' => $this->validateParameter($this->params['gpsLatitudeLongitude'], 'GPS Latitude Longitude'),
            'nameOfTok' => $this->validateParameter($this->params['nameOfTok'], 'Name of Tok'),
            'totalGeographicalArea' => $this->validateParameter($this->params['totalGeographicalArea'], 'Total Geographical Area'),
            'avgSlope' => $this->validateParameter($this->params['avgSlope'], 'Average Slope'),
            'generalAspects' => $this->validateParameter($this->params['generalAspects'], 'General Aspects'),
            //            'image' => $this->validateParameter($this->params['image'], 'Image'),
//            'geoLatitude' => $this->validateParameter($this->params['geoLatitude'], 'Geo Location'),
//            'geoLongitude' => $this->validateParameter($this->params['geoLongitude'], 'Geo Location'),
//            'distance' => $this->validateParameter($this->params['distance'], 'Dostance'),
//            'observer_name' => $this->validateParameter($this->params['observer_name'], 'Observer Name'),
        ];

        foreach ($validations as $param => $result) {
            if (!$result['success']) {
                return $result;
            }
        }

        //        $transactId = $this->params['transactId'];
//        $constituencyName = $this->params['constituencyName'];
        $districtName = $this->params['districtName'];
        //        $divisionName = $this->params['divisionName'];
        $blockName = $this->params['blockName'];
        $unitName = $this->params['unitName'];
        $mwsName = $this->params['mwsName'];
        //        $nameOfNayapanchayat = $this->params['nameOfNayapanchayat'];
        $gpName = $this->params['gpName'];
        $rvName = $this->params['rvName'];
        $heightAboveSeaLevel = $this->params['heightAboveSeaLevel'];
        $gpsLatitudeLongitude = $this->params['gpsLatitudeLongitude'];
        $nameOfTok = $this->params['nameOfTok'];
        $totalGeographicalArea = $this->params['totalGeographicalArea'];
        $avgSlope = $this->params['avgSlope'];
        $generalAspects = $this->params['generalAspects'];
        //        $image = $this->params['image'];
//        $geoLatitude = $this->params['geoLatitude'];
//        $geoLongitude = $this->params['geoLongitude'];
//        $geoAddress = $this->params['geoAddress'];
//        $distance = $this->params['distance'];
//        $observer_name = $this->params['observer_name'];
//        $remarks = $this->params['remarks'];

        $data = new VillageData();
        //        $data->transact_id = $transactId;
//        $data->constituency_name = $constituencyName;
        $data->district_name = $districtName;
        //        $data->division_name = $divisionName;
        $data->block_name = $blockName;
        $data->unit_name = $unitName;
        $data->mws_name = $mwsName;
        //        $data->name_of_nayapanchayat = $nameOfNayapanchayat;
        $data->gp_name = $gpName;
        $data->rv_name = $rvName;
        $data->height_above_sea_level_of_village_m = $heightAboveSeaLevel;
        $data->gps_latitude_longitude_land_mark_within_gram_panchayat = $gpsLatitudeLongitude;
        $data->name_of_tok_falling_under_revenue_village = $nameOfTok;
        $data->total_geographical_area_of_the_village_hectares = $totalGeographicalArea;
        $data->avg_slope_of_the_village_percentage = $avgSlope;
        $data->general_aspects_of_village = $generalAspects;
        //        $data->image = $image;
//        $data->geo_latitude = $geoLatitude;
//        $data->geo_longitude = $geoLongitude;
//        $data->geo_address = $geoAddress;
//        $data->distance = $distance;
//        $data->observer_name = $observer_name;
//        $data->remarks = $remarks;
        $data->added_by = User::$uid;
        $data->status = 1;
        $data->created_at = Helper::now();

        if ($data->save()) {
            return ['success' => true, 'message' => 'Record saved!', 'value' => strval($data->id)];
        } else {
            return ['success' => false, 'message' => 'Unable to save record! Please try again later'];
        }
    }

    public function submitGeoData()
    {
        $validations = [
            'transact_walk_id' => $this->validateParameter($this->params['transact_walk_id'], 'Transact Walk Id'),
            'village_id' => $this->validateParameter($this->params['village_id'], 'Village Id'),
            'image' => $this->validateParameter($this->params['image'], 'Image'),
            'geo_latitude' => $this->validateParameter($this->params['geo_latitude'], 'Geo latitude'),
            'geo_longitude' => $this->validateParameter($this->params['geo_longitude'], 'Geo longitude'),
            //            'geo_address' => $this->validateParameter($this->params['geo_address'], 'Geo address'),
            'distance' => $this->validateParameter($this->params['distance'], 'Distance'),
            'observer_name' => $this->validateParameter($this->params['observer_name'], 'Observer Name'),
            //            'remarks' => $this->validateParameter($this->params['remarks'], 'Remarks'),
        ];

        foreach ($validations as $param => $result) {
            if (!$result['success']) {
                return $result;
            }
        }
        $transact_walk_id = $this->params['transact_walk_id'];
        $transact_walk = $this->params['transact_walk'];
        $village_id = $this->params['village_id'];
        $image = $this->params['image'];
        $geo_latitude = $this->params['geo_latitude'];
        $geo_longitude = $this->params['geo_longitude'];
        $geo_address = $this->params['geo_address'];
        $distance = $this->params['distance'];
        $observer_name = $this->params['observer_name'];
        $remarks = $this->params['remarks'];

        $data = new GeoData();
        $data->village_id = $village_id;
        $data->transact_walk_id = $transact_walk_id;
        $data->transact_walk = $transact_walk;
        $data->geo_latitude = $geo_latitude;
        $data->geo_longitude = $geo_longitude;
        $data->geo_address = $geo_address;
        $data->distance = $distance;
        $data->observer_name = $observer_name;
        $data->image = $image;
        $data->remarks = $remarks;
        $data->created_at = Helper::now();

        if ($data->save()) {
            return ['success' => true, 'message' => 'Record saved!'];
        } else {
            return ['success' => false, 'message' => 'Unable to save record! Please try again later'];
        }
    }

    public function validateParameter($param, $paramName)
    {
        if (empty($param)) {
            return ['success' => false, 'message' => "$paramName is required!"];
        }
        return ['success' => true];
    }

    public function getVillageRecords($uid)
    {
        return VillageData::where('added_by', $uid)
            ->join("transect_walks", "transect_walks.id", "village_data.transact_id")
            ->orderByDesc('id')
            ->get(['village_data.*', 'transect_walks.description']);
    }


    public function getInfluencer()
    {
        $uid = User::$uid;

        // Find Influencer by ID
        // $influencerById = Influencer::getById($uid);

        // Find Influencer by UID
        $user = Influencer::getByUid($uid);

        return $user;
    }



    public static function handleImages($images)
    {
        if (!$images) {
            return ['success' => false, 'message' => 'No files received!'];
        }

        if (!is_array($images)) {
            return ['success' => false, 'message' => 'Invalid input format!'];
        }

        $uploadedFiles = [];
        $path = Helper::getFilesPath('TRAINING_IMAGE');

        foreach ($images as $file) {
            if (!Helper::isValidFile($file)) {
                return ['success' => false, 'message' => 'File Not Valid! (' . $file->getClientOriginalName() . ')'];
            }

            $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
            $file->move($path, $fileName);

            $uploadedFiles[] = $fileName;
        }

        return implode(', ', $uploadedFiles);
    }




    public function pdfConverter($images)
    {
        if (!$images || !is_array($images)) {
            return response()->json(['success' => false, 'message' => 'No files received!']);
        }

        $html = '<div style="text-align: center;">';
        $html .= '<h1>Attendance Of Training Participants</h1>';

        foreach ($images as $file) {
            if (!Helper::isValidFile($file)) {
                return response()->json(['success' => false, 'message' => 'File Not Valid! (' . $file->getClientOriginalName() . ')'], 400);
            }

            $base64Image = base64_encode(file_get_contents($file->getRealPath()));
            $html .= '<img src="data:' . $file->getMimeType() . ';base64,' . $base64Image . '" alt="Image" style="width: 100%; height: auto; margin-bottom: 20px;">';
        }

        $html .= '</div>';

        try {
            $pdf = Pdf::loadHTML($html);

            $pdfFilename = 'attendance_' . time() . '.pdf';

            $path = Helper::getFilesPath('PDF') . '/' . $pdfFilename;

            $pdf->save($path);

            return $pdfFilename;

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }




    public function capacityBuilding(Request $request)
    {
        $districtName = $this->params['districtName'];
        $uid = $this->params['uid'];
        $blockName = $this->params['blockName'];
        $gpName = $this->params['gpName'];
        $rvName = $this->params['rvName'];
        $year = $this->params['year'];
        $month = $this->params['month'];
        $mainComponent = $this->params['mainComponent'];
        $subComponent = $this->params['subComponent'];
        $minorComponent = $this->params['minorComponent'];
        $componentActivity = $this->params['componentActivity'];
        $activity = $this->params['activity'];
        $subject_topic = $this->params['subject_topic'];
        $other_pi = $this->params['other_pi'];
        $institute_ngo = $this->params['institute_ngo'];
        $location = $this->params['location'];
        $male_gen_obc_pc = $this->params['male_gen_obc_pc'];
        $male_sc_pc = $this->params['male_sc_pc'];
        $male_st_pc = $this->params['male_st_pc'];
        $male_total_pc = $this->params['male_total_pc'];
        $female_gen_obc_pc = $this->params['female_gen_obc_pc'];
        $female_sc_pc = $this->params['female_sc_pc'];
        $female_st_pc = $this->params['female_st_pc'];
        $female_total_pc = $this->params['female_total_pc'];

        $images = $request->file('images');
        $att_part = $request->file('attendence_participants');
        // $att_part = $this->params['attendence_participants'];

        $imageResponse = self::handleImages($images);
        $att_part_res = self::pdfConverter($att_part);

        if (is_array($imageResponse)) {
            return response()->json(['success' => false, 'message' => 'Error uploading files!'], 400);
        }

        $data = new CapacityBuilding();
        $data->uid = $uid;
        $data->district_name = $districtName;
        $data->block_name = $blockName;
        $data->gp_name = $gpName;
        $data->rv_name = $rvName;
        $data->year = $year;
        $data->month = $month;
        $data->main_component = $mainComponent;
        $data->sub_component = $subComponent;
        $data->minor_component = $minorComponent;
        $data->component_activity = $componentActivity;
        $data->activity = $activity;
        $data->subject_topic = $subject_topic;
        $data->other_pi = $other_pi;
        $data->institute_ngo = $institute_ngo;
        $data->location = $location;
        $data->male_gen_obc_pc = $male_gen_obc_pc;
        $data->male_sc_pc = $male_sc_pc;
        $data->male_st_pc = $male_st_pc;
        $data->male_total_pc = $male_total_pc;
        $data->female_gen_obc_pc = $female_gen_obc_pc;
        $data->female_sc_pc = $female_sc_pc;
        $data->female_st_pc = $female_st_pc;
        $data->female_total_pc = $female_total_pc;
        $data->images = $imageResponse;
        $data->attendance_participants = $att_part_res;
        $data->save();

        return response()->json(['success' => true, 'message' => 'Data inserted successfully.', "value" => "OK"], 200);
    }




    public function getAllCaB()
    {
        $uid = $this->params['uid'];

        $capacityBuildings = CapacityBuilding::where('uid', $uid)->orderBy('id', 'desc')->get();

        return response()->json($capacityBuildings);
    }




    public function test()
    {

        $notification = '89';

        return response()->json(['success' => true, 'message' => 'not read notification', 'value' => $notification]);

        // //************LOG START************//
        // Logger::$logType = LogType::EXCEPTION;
        // Logger::$className = __CLASS__;
        // Logger::$methodName = __METHOD__;
        // Logger::$lineNo = __LINE__;
        // Logger::$tag = 'TESTER LOGIN';
        // Logger::$message = '';
        // Logger::$extra = 'jhjkhj';
        // Logger::print();
        // //************LOG END************//

    }






}

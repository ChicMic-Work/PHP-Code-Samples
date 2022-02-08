<?php 
namespace App\Traits;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

Trait CommonMethods { 

    protected static $TWILLIO_ENABLED = false;
    protected static $TWILLIO_SID = false;
    protected static $TWILLIO_TOKEN = false;
    protected static $TWILLIO_FROM = false;

    protected static $REGEX_FLOAT = "/^[0-9]+(\.[0-9]{1,2})?$/";
    protected static $REGEX_NUMBER = "/^([0-9]\d*)$/";
    protected static $REGEX_ALPHABET = "/^[a-zA-Z ]+$/";
    protected static $REGEX_ALPHABET_NUMERIC = "/^[a-zA-Z0-9_ ]+$/";
    protected static $CODE_ARRAY =[
          'BD' => '880', 'BE' => '32', 'BF' => '226', 'BG' => '359', 'BA' => '387', 'BB' => '+1-246', 'WF' => '681', 'BL' => '590', 'BM' => '+1-441', 'BN' => '673', 'BO' => '591', 'BH' => '973', 'BI' => '257', 'BJ' => '229', 'BT' => '975', 'JM' => '+1-876', 'BV' => '', 'BW' => '267', 'WS' => '685', 'BQ' => '599', 'BR' => '55', 'BS' => '+1-242', 'JE' => '+44-1534', 'BY' => '375', 'BZ' => '501', 'RU' => '7', 'RW' => '250', 'RS' => '381', 'TL' => '670', 'RE' => '262', 'TM' => '993', 'TJ' => '992', 'RO' => '40','TK' => '690', 'GW' => '245', 'GU' => '+1-671', 'GT' => '502', 'GS' => '', 'GR' => '30', 'GQ' => '240', 'GP' => '590', 'JP' => '81', 'GY' => '592', 'GG' => '+44-1481', 'GF' => '594', 'GE' => '995', 'GD' => '+1-473', 'GB' => '44', 'GA' => '241', 'SV' => '503', 'GN' => '224', 'GM' => '220', 'GL' => '299', 'GI' => '350', 'GH' => '233', 'OM' => '968', 'TN' => '216', 'JO' => '962', 'HR' => '385', 'HT' => '509', 'HU' => '36', 'HK' => '852', 'HN' => '504', 'HM' => ' ', 'VE' => '58', 'PR' => '+1-787 and 1-939', 'PS' => '970', 'PW' => '680', 'PT' => '351', 'SJ' => '47', 'PY' => '595', 'IQ' => '964', 'PA' => '507', 'PF' => '689', 'PG' => '675', 'PE' => '51', 'PK' => '92', 'PH' => '63', 'PN' => '870', 'PL' => '48', 'PM' => '508', 'ZM' => '260', 'EH' => '212', 'EE' => '372', 'EG' => '20', 'ZA' => '27', 'EC' => '593', 'IT' => '39', 'VN' => '84', 'SB' => '677', 'ET' => '251', 'SO' => '252', 'ZW' => '263', 'SA' => '966', 'ES' => '34', 'ER' => '291', 'ME' => '382', 'MD' => '373', 'MG' => '261', 'MF' => '590', 'MA' => '212', 'MC' => '377', 'UZ' => '998', 'MM' => '95', 'ML' => '223', 'MO' => '853', 'MN' => '976', 'MH' => '692', 'MK' => '389', 'MU' => '230', 'MT' => '356', 'MW' => '265', 'MV' => '960', 'MQ' => '596', 'MP' => '+1-670', 'MS' => '+1-664', 'MR' => '222', 'IM' => '+44-1624', 'UG' => '256', 'TZ' => '255', 'MY' => '60', 'MX' => '52', 'IL' => '972', 'FR' => '33', 'IO' => '246', 'SH' => '290', 'FI' => '358', 'FJ' => '679', 'FK' => '500', 'FM' => '691', 'FO' => '298', 'NI' => '505', 'NL' => '31', 'NO' => '47', 'NA' => '264', 'VU' => '678', 'NC' => '687','NE' => '227', 'NF' => '672', 'NG' => '234', 'NZ' => '64', 'NP' => '977', 'NR' => '674', 'NU' => '683', 'CK' => '682', 'XK' => '', 'CI' => '225', 'CH' => '41', 'CO' => '57', 'CN' => '86', 'CM' => '237', 'CL' => '56', 'CC' => '61', 'CA' => '1', 'CG' => '242', 'CF' => '236', 'CD' => '243', 'CZ' => '420', 'CY' => '357', 'CX' => '61', 'CR' => '506', 'CW' => '599', 'CV' => '238', 'CU' => '53', 'SZ' => '268', 'SY' => '963', 'SX' => '599', 'KG' => '996', 'KE' => '254', 'SS' => '211', 'SR' => '597', 'KI' => '686', 'KH' => '855', 'KN' => '+1-869', 'KM' => '269', 'ST' => '239', 'SK' => '421', 'KR' => '82', 'SI' => '386', 'KP' => '850', 'KW' => '965', 'SN' => '221', 'SM' => '378', 'SL' => '232', 'SC' => '248', 'KZ' => '7', 'KY' => '+1-345', 'SG' => '65', 'SE' => '46', 'SD' => '249', 'DO' => '+1-809 and 1-829', 'DM' => '+1-767', 'DJ' => '253', 'DK' => '45', 'VG' => '+1-284', 'DE' => '49', 'YE' => '967', 'DZ' => '213', 'US' => '1', 'UY' => '598', 'YT' => '262', 'UM' => '1', 'LB' => '961', 'LC' => '+1-758', 'LA' => '856', 'TV' => '688', 'TW' => '886', 'TT' => '+1-868', 'TR' => '90', 'LK' => '94', 'LI' => '423', 'LV' => '371', 'TO' => '676', 'LT' => '370', 'LU' => '352', 'LR' => '231', 'LS' => '266', 'TH' => '66', 'TF' => '', 'TG' => '228', 'TD' => '235', 'TC' => '+1-649', 'LY' => '218', 'VA' => '379', 'VC' => '+1-784', 'AE' => '971', 'AD' => '376', 'AG' => '+1-268', 'AF' => '93', 'AI' => '+1-264', 'VI' => '+1-340', 'IS' => '354', 'IR' => '98', 'AM' => '374', 'AL' => '355', 'AO' => '244', 'AQ' => '', 'AS' => '+1-684', 'AR' => '54', 'AU' => '61', 'AT' => '43', 'AW' => '297', 'IN' => '91', 'AX' => '+358-18', 'AZ' => '994', 'IE' => '353', 'ID' => '62', 'UA' => '380', 'QA' => '974', 'MZ' => '258'];
    protected static $WEEKS = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];

    protected function dateDiffInDays( $to = false, $from = false ) { 
        $to = $to?date( 'Y-m-d', strtotime( $to ) ):date( 'Y-m-d' );
        $from = $from?date( 'Y-m-d', strtotime( $from ) ):date( 'Y-m-d' );
        $diff = strtotime( $from ) - strtotime( $to );
        return abs( round( $diff / 86400 ) );
    }

    protected function getWeekByDays( $days ) { 
        return ceil( $days/7 );
    }

    protected static function getSurveyWeekDays( ) { 
        $maxDay = SELF::$SURVEY_AFTER_WEEK * 7;
        $minDay = $maxDay - 6;
        return range( $minDay, $maxDay );
    }

    protected static function getValidationErrorsToArray( $validator ){
        $errors = [];
        if (!$validator->fails()) { 
            return $errors;
        }

        return $validator->errors( )->first( );

        foreach( $validator->errors( )->toArray( ) as $messageKey =>  $message ){
            $message = reset( $message );
            if( $message ) { 
                $errors[ $messageKey ] = $message;
            }
        }

        return $errors;
    }

    protected static function getDateInDefaultTimeZone( $dateTime = '', $format = 'Y-m-d H:i:s', $timeZone = '' ){
        $userDateTime = ( !$dateTime )?new \DateTime( ):new \DateTime( $dateTime );
        if( $timeZone ) 
            $userDateTime->setTimezone( new \DateTimeZone( $timeZone ) );
        else 
            $userDateTime->setTimezone( new \DateTimeZone( config('app.timezone') ) );
        return $userDateTime->format( $format );
    }
    
    protected static function getErrorResponse( $error = '', $status = 401 ) { 
        return response()->json( [ 'message' => $error, 'code' => $status ], $status );
    }

    protected static function getSuccessResponse( $success = '', $data = [], $status = 200 ) { 
        return response()->json( array_merge( [ 'message' => $success, 'code' => $status, 'data_last_updated' => \Config( 'app.DATA_LAST_UPDATED' ) ], $data ), $status );
    }

    protected static function getStoragePublicUrl( $file = '' ){
       
        $APP_ENV = env("APP_ENV");
        if($APP_ENV=='local'){
            return asset('storage/app/public/'. $file ); 
        }else{
            return asset('storage/'. $file ); 
        }
        // return asset('storage/'. $file ); 
    }

    protected static function sendMessage( $to = false, $messageTxt = false, &$error = '' ){

        if( SELF::$TWILLIO_SID === false ) { 
            SELF::$TWILLIO_ENABLED = (boolean)\Config( 'app.TWILLIO_ENABLED' );
            SELF::$TWILLIO_SID = \Config( 'app.TWILLIO_SID' );
            SELF::$TWILLIO_TOKEN = \Config( 'app.TWILLIO_TOKEN' );
            SELF::$TWILLIO_FROM = \Config( 'app.TWILLIO_FROM' );
        }

        if( SELF::$TWILLIO_ENABLED === false ) { 
            return true;
        } elseif( !SELF::$TWILLIO_SID || !SELF::$TWILLIO_TOKEN || !SELF::$TWILLIO_FROM ) { 
            $error = 'Message client not configured properly, please contact administrator';
            return false;
        }

        if( !$to ) { 
            $error = 'Phone Number not found';
            return false;
        }
        if( !$messageTxt ) { 
            $error = 'Message text not found';
            return false;
        }

        try { 
            $client = new Client( SELF::$TWILLIO_SID, SELF::$TWILLIO_TOKEN );
            $isSent = $client->messages->create(
                                    $to,
                                    [
                                        'from' => SELF::$TWILLIO_FROM,
                                        'body' => $messageTxt,
                                    ]
                                );
            $isSent = true;
        } catch( \Exception $e ){ 
            $error = $e->getMessage( );
            Log::debug($error);
            $isSent = false;
        }

        return $isSent;
    }

    public static function formatPrice( $price = 0 ) { 
        $price = \number_format( $price, 2, '.', '' );
        return $price;
    }

    public static function getTableName($type){
        switch ($type) {
            case "car":
              $table="rent_cars";
              break;
            case "jetski":
                $table="rent_jetskis";
              break;
            case "bike":
                $table="rent_bikes";
              break;
            case "boat":
                $table="rent_boats";
            break;
            case "property":
                $table="rent_properties";
            break;
            case "other":
                $table="rent_others";
            break;
            case "jewellery":
                $table="rent_jewelleries";
            break;
            default:
            $table="rent_others";
        }
        return $table;
    }

    public static function sendPushNotifications($deviceToken, $textMessage, $textTitle, $notificationArray){
        
        $bit = 1;
        $url = "https://fcm.googleapis.com/fcm/send";
        $token = $deviceToken;
       
        $serverKey=\Config( 'fcmkeys.SERVER_KEY' );
        $title = $textTitle;
        $body = $textMessage;

        $notification = array('title' =>$title , 'body' => $body, 'sound' => 'default', 'badge' => '1');
        
        $data=$notificationArray;
        $arrayToSend = array('to' => $token, 'notification' => $notification,'priority'=>'high', 'data'=> $data);
        $json = json_encode($arrayToSend);
        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: key='. $serverKey;
        $ch = curl_init();
        curl_setopt( $ch,CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );
        curl_setopt( $ch,CURLOPT_POST, true );
        curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt( $ch,CURLOPT_POSTFIELDS, $json );
        $response = curl_exec($ch );

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);  
        if (curl_errno($ch)) {  
            //request failed  
            return false;//probably you want to return false  
        }  
        if ($httpCode != 200) {  
            //request failed  
            return false;//probably you want to return false  
        }  
        curl_close($ch);  
        return $response;

    }

    public static function getCountryCode($phone){

        if( SELF::$TWILLIO_SID === false ) { 
            SELF::$TWILLIO_ENABLED = (boolean)\Config( 'app.TWILLIO_ENABLED' );
            SELF::$TWILLIO_SID = \Config( 'app.TWILLIO_SID' );
            SELF::$TWILLIO_TOKEN = \Config( 'app.TWILLIO_TOKEN' );
            SELF::$TWILLIO_FROM = \Config( 'app.TWILLIO_FROM' );
        }

        if( SELF::$TWILLIO_ENABLED === false ) { 
            return true;
        } elseif( !SELF::$TWILLIO_SID || !SELF::$TWILLIO_TOKEN || !SELF::$TWILLIO_FROM ) { 
            $error = 'Message client not configured properly, please contact administrator';
            return false;
        }
        $response = [];
        try { 
            

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://lookups.twilio.com/v1/PhoneNumbers/'.$phone);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERPWD, SELF::$TWILLIO_SID . ':' . SELF::$TWILLIO_TOKEN);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                return false;
            }
            curl_close($ch);
           if( $result ) {
                $data = json_decode($result);
                $key = $data->country_code;
                $code = isset(SELF::$CODE_ARRAY[$key]) ? SELF::$CODE_ARRAY[$key] : null; 
                $code = str_replace("+", "", $code);
                $response['code'] = '+'.$code;
                $response['phone'] = $data->national_format;
                return $response;
           } else {
                return $response;
           }
            
        } catch( \Exception $e ){ 
            $error = $e->getMessage( );
            return false;
        }
    }
}

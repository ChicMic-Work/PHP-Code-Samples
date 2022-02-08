<?php 
namespace App\Http\Controllers\Api\Signup;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\PhoneAccount;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Validator;

use Carbon\Carbon;

class RegisterController extends Controller { 
    
    public function sendOtp( Request $request ) { 
        
        $validator = Validator::make($request->all(), [ 
            'phone' => 'required'
        ]);
        
        if ( $errors = SELF::getValidationErrorsToArray( $validator ) ) { 
            return SELF::getErrorResponse( $errors, 401 );
        }

        PhoneAccount::where( 'expire_at', '<=', Carbon::now( ) )->delete( );

        $otp = PhoneAccount::getOtp( $request->phone );

        if ( !$otp ) { 
            return SELF::getErrorResponse( __('Something went wrong, please try again later'), 500 );
        }

        $error = '';
        $isSent = SELF::sendMessage( $request->phone, 'Your Cryto Souq Verification Code Is: ' . $otp, $error );
        if ( !$isSent ) { 
            return SELF::getErrorResponse( __('Unable to send otp, please try again later'), 500 );
        }
                        
        return SELF::getSuccessResponse( __('OTP sent on your phone number'), [], $this->successStatus );
    }

    public function verifyOtp( Request $request ) { 
        
        $validator = Validator::make($request->all(), [ 
            'phone' => 'required',
            //'otp' => 'required'
            'status' =>'required' //1 or 0
        ]);

        if ( $errors = SELF::getValidationErrorsToArray( $validator ) ) { 
            return SELF::getErrorResponse( $errors, 401 );
        }

        $requestType = ( isset( $request->request_type ) && $request->request_type == 'signup' ) ? 'signup' : ( ( isset( $request->request_type ) && $request->request_type == 'forgot_password' ) ? 'forgot_password' : '' );

        if($request->request_type != 'edit_profile'){
            $user = User::where( 'phone_number', $request->phone )->first( );
            if( !$user ) { 
                return SELF::getErrorResponse( __('User not found'), 404 );
            }
        }else{
            $userId=$request->userId;
            $user = User::where( 'id', $userId )->first( );
            if( !$user ) { 
                return SELF::getErrorResponse( __('User not found'), 404 );
            }
        }
        

        $phoneAccount = PhoneAccount::where( 'phone_number', $user->phone_number )->first( );
        if( !$phoneAccount ) { 
            return SELF::getErrorResponse( __('Phone number veirification request not found'), 404 );
        }

        if( $phoneAccount->expire_at < Carbon::now( ) ) { 
            return SELF::getErrorResponse( __('Entered OTP has been expired'), 500 );
        }

        if ( !Hash::check( $request->otp, $phoneAccount->otp ) ) { 
            return SELF::getErrorResponse( __('OTP does not match'), 401 );
        }

        if( $requestType == 'signup' ) { 
            
            $error = '';

            $isSent = SELF::sendMessage( $user->phone_number, "Dear " . $user->name . ",\n\n" . "You're successfully registered with us" . "\n\n" . "Thank You," . "\n" . env( 'APP_NAME' ) . " Team", $error );
            if ( !$isSent ) { 
                return SELF::getErrorResponse( __('Unable to verify otp, please try again later.'), 401 );
            }
            if($request->status==1){
                $userDetails = $user->toArray( );
                if( isset( $userDetails[ 'profile_image' ] ) && $userDetails[ 'profile_image' ] ) 
                    $userDetails[ 'profile_image' ] = SELF::getStoragePublicUrl( 'avatars/' . $userDetails[ 'profile_image' ] );
                if( isset( $userDetails[ 'driver_license_image' ] ) && $userDetails[ 'driver_license_image' ] ) 
                    $userDetails[ 'driver_license_image' ] = SELF::getStoragePublicUrl( 'license/' . $userDetails[ 'driver_license_image' ] );
                
                $userDetails[ 'phoneDetails' ] = SELF::getCountryCode($userDetails[ 'phone_number' ] );


                $success =  $user->createToken( SELF::ACCESS_TOKEN_NAME )->accessToken; 
               
                $user->markPhoneAsVerified();

                return SELF::getSuccessResponse( __('You are successfully registered'), [ 'token' => $success, 'userDetails' => $userDetails ], $this->successStatus );
            }
            

        } else if( $request->request_type == 'login' ) {
           
            $user->markPhoneAsVerified();
        }else{
            $user->markPhoneAsVerified();
        }

        $phoneAccount->expire_at = Carbon::now()->addMinutes( \Config( 'app.expire_otp_after_verify' ) );
        $phoneAccount->verified = 1;

        if( !$phoneAccount->save() ) { 
            return SELF::getErrorResponse( __('Something went wrong, please try again later'), 500 );
        }

        $error = '';
        $isSent = SELF::sendMessage( $request->phone, __('Your phone number has been successfully verified'), $error );
        if ( !$isSent ) { 
            return SELF::getErrorResponse( 'Something went wrong, please try again later', 500 );
        }
                        
        return SELF::getSuccessResponse( __('Your phone number has been successfully verified'), [], $this->successStatus );
    }

    public function register( Request $request ) { 
        
        $validator = Validator::make($request->all(), [ 
            'name' => 'required|regex:'. SELF::$REGEX_ALPHABET,
            'email' => 'required|email|unique:users,email', 
            'profile_image' => 'image|mimes:jpeg,png,jpg,gif,svg',
            'driver_license_image' => 'image|mimes:jpeg,png,jpg,gif,svg',
            'phone' => 'required|unique:users,phone_number', 
            'password' => 'required', 
            'c_password' => 'required|same:password',
        ],[
            'c_password.required' => 'Confirm password field is required',
            'c_password.same' => 'Password mismatch',
            'name.regex' => 'Name should be in alphabets only.'
        ]);
        
        if ($errors = SELF::getValidationErrorsToArray( $validator ) ) { 
            return SELF::getErrorResponse( $errors, 401 );
        }

        PhoneAccount::where( 'expire_at', '<=', Carbon::now( ) )->delete( );

        $otp = PhoneAccount::getOtp( $request->phone );

        if ( !$otp ) { 
            return SELF::getErrorResponse( __('Unable to get otp, please try again later'), 500 );
        }

        $error = '';
        $isSent = SELF::sendMessage( $request->phone, 'Your Cryto Souq Signup Verification Code Is: ' . $otp, $error );
        
        
        if ( !$isSent ) { 
            return SELF::getErrorResponse( __('Unable to send otp, please try again later'), 500 );
        }

        $input = $request->all(); 
        $input[ 'password' ] = bcrypt( $input[ 'password' ] ); 
        $input[ 'phone_number' ] = $input[ 'phone' ];
        $user = User::create( $input );

        if ( !$user ) { 
            return SELF::getErrorResponse( __('Something went wrong, please try again later'), 500 );
        }

        if( request( )->profile_image ) { 
            $avatarName = $user->id . '_avatar' . time( ) . '.' . request( )->profile_image->getClientOriginalExtension( );
            $request->profile_image->storeAs( 'public/avatars', $avatarName );
            $user->profile_image = $avatarName;
            $user->save();
        }

        if( request( )->driver_license_image ) { 
            $driverLic = $user->id . '_dl' . time( ) . '.' . request( )->driver_license_image->getClientOriginalExtension( );
            $request->driver_license_image->storeAs( 'public/license', $driverLic );
            $user->driver_license_image = $driverLic;
            $user->save();
        }

        $user->markEmailAsVerified();

        return SELF::getSuccessResponse( __('Please verify OTP sent on your phone number'), [ ], $this->successStatus );
    }
}
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    
    /*
     * Custom lang definitions
     */
    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
        'login_success' 		=> 'Login success.',
        'logout_success' 		=> 'Logged out successfully.',
        'logout_failed' 		=> 'Logout failed.',
        'token_not_provided' 	=> 'Required auth token is not provided.',
        'token_has_expired'		=> 'Auth token has expired.',
        'token_not_refreshable' => 'Auth token has expired and can no longer be refreshed. Please login again.',
        'token_not_valid'		=> 'Auth token is not valid.',
        'token_is_valid'		=> 'Auth token is valid.',
        'user_not_found'		=> 'User was not found.',
        
        /*
         * moved to "kayttaja"
         'register_failed'		=> 'Creating user failed.',
         'register_success'		=> 'New user has been created.',
         'user_found_count'		=> ':count users was found with given search terms.',
         'user_deleted'			=> "User was succesfully deleted.",
         'user_delete_failed'	=> 'Failed to delete user.',
         'user_updated'			=> 'User was successcully updated.',
         'user_update_failed'	=> 'Failed to update user.',
         */
    ],
];

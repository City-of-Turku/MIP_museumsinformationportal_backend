<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'MIP backend'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'Europe/Helsinki',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'fi',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,

        PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider::class, // JWT-Auth
        //Barryvdh\Cors\ServiceProvider::class,
    	//'PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider', // JWT-Auth
    	//'Barryvdh\Cors\ServiceProvider', // CORS
    	Intervention\Image\ImageServiceProvider::class,
        Fruitcake\Cors\CorsServiceProvider::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => [

        'App' => Illuminate\Support\Facades\App::class,
        'Arr' => Illuminate\Support\Arr::class,
        'Artisan' => Illuminate\Support\Facades\Artisan::class,
        'Auth' => Illuminate\Support\Facades\Auth::class,
        'Blade' => Illuminate\Support\Facades\Blade::class,
        'Broadcast' => Illuminate\Support\Facades\Broadcast::class,
        'Bus' => Illuminate\Support\Facades\Bus::class,
        'Cache' => Illuminate\Support\Facades\Cache::class,
        'Config' => Illuminate\Support\Facades\Config::class,
        'Cookie' => Illuminate\Support\Facades\Cookie::class,
        'Crypt' => Illuminate\Support\Facades\Crypt::class,
        'DB' => Illuminate\Support\Facades\DB::class,
        'Eloquent' => Illuminate\Database\Eloquent\Model::class,
        'Event' => Illuminate\Support\Facades\Event::class,
        'File' => Illuminate\Support\Facades\File::class,
        'Gate' => Illuminate\Support\Facades\Gate::class,
        'Hash' => Illuminate\Support\Facades\Hash::class,
        'Lang' => Illuminate\Support\Facades\Lang::class,
        'Log' => Illuminate\Support\Facades\Log::class,
        'Mail' => Illuminate\Support\Facades\Mail::class,
        'Notification' => Illuminate\Support\Facades\Notification::class,
        'Password' => Illuminate\Support\Facades\Password::class,
        'Queue' => Illuminate\Support\Facades\Queue::class,
        'Redirect' => Illuminate\Support\Facades\Redirect::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
        'Request' => Illuminate\Support\Facades\Request::class,
        'Response' => Illuminate\Support\Facades\Response::class,
        'Route' => Illuminate\Support\Facades\Route::class,
        'Schema' => Illuminate\Support\Facades\Schema::class,
        'Session' => Illuminate\Support\Facades\Session::class,
        'Storage' => Illuminate\Support\Facades\Storage::class,
        'Str' => Illuminate\Support\Str::class,
        'URL' => Illuminate\Support\Facades\URL::class,
        'Validator' => Illuminate\Support\Facades\Validator::class,
        'View' => Illuminate\Support\Facades\View::class,

        //'JWTAuth' => PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::class,
    	//'JWTFactory' => PHPOpenSourceSaver\JWTAuth\Facades\JWTFactory::class,
    	'Image' => Intervention\Image\Facades\Image::class,

    ],


    'json_srid' => env("JSON_SRID"),
    'db_srid' => env("DB_SRID"),
    'solr_addr' => env("SOLR_ADDR"),

    'image_server' => env('IMAGE_SERVER'),
    'image_server_baseurl' => env("IMAGE_SERVER_BASEURL"),
    'image_upload_path' => env("IMAGE_UPLOAD_PATH"),
    'old_image_thumb_tiny_name' => env("OLD_IMAGE_THUMB_TINY_NAME"),
    'old_image_thumb_small_name' => env("OLD_IMAGE_THUMB_SMALL_NAME"),
    'old_image_thumb_medium_name' => env("OLD_IMAGE_THUMB_MEDIUM_NAME"),
    'old_image_thumb_large_name' => env("OLD_IMAGE_THUMB_LARGE_NAME"),
    'image_thumb_large' => env("IMAGE_THUMB_LARGE"),
    'image_thumb_medium' => env("IMAGE_THUMB_MEDIUM"),
    'image_thumb_small' => env("IMAGE_THUMB_SMALL"),
    'image_thumb_tiny' => env("IMAGE_THUMB_TINY"),

    'max_image_size' => env('MAX_IMAGE_SIZE'),
    'max_file_size'	=> env('MAX_FILE_SIZE'),

    'attachment_server' => env('ATTACHMENT_SERVER'),
    'attachment_server_baseurl' => env('ATTACHMENT_SERVER_BASEURL'),
    'attachment_upload_path' => env('ATTACHMENT_UPLOAD_PATH'),

    'mml_wmts_username' => env('MML_WMTS_USERNAME'),
    'mml_wmts_password' => env('MML_WMTS_PASSWORD'),
    'mml_apikey_nimisto' => env('MML_APIKEY_NIMISTO'),
    'mml_municipality_codes' => env('MML_MUNICIPALITY_CODES'),
    'mml_placetype_codes' => env('MML_PLACETYPE_CODES'),

    'mml_kiinteistotiedot_url' => env('MML_KIINTEISTOTIEDOT_URL'),
    'mml_kiinteistotiedot_username' => env('MML_KIINTEISTOTIEDOT_USERNAME'),
    'mml_kiinteistotiedot_password' => env('MML_KIINTEISTOTIEDOT_PASSWORD'),
    'mml_kiinteistotiedot_alue_url' => env('MML_KIINTEISTOTIEDOT_ALUE_URL'),

    'mml_lainhuutotiedot_rest_url' => env('MML_LAINHUUTOTIEDOT_REST_URL'),
    'mml_lainhuutotiedot_rest_username' => env('MML_LAINHUUTOTIEDOT_REST_USERNAME'),
    'mml_lainhuutotiedot_rest_password' => env('MML_LAINHUUTOTIEDOT_REST_PASSWORD'),

    'mml_rakennustiedot_url' => env('MML_RAKENNUSTIEDOT_URL'),
    'mml_rakennustiedot_username' => env('MML_RAKENNUSTIEDOT_USERNAME'),
    'mml_rakennustiedot_password' => env('MML_RAKENNUSTIEDOT_PASSWORD'),
    'mml_nimisto_url' => env('MML_NIMISTO_URL'),
    'mml_maasto_url' => env('MML_MAASTO_URL'),

    'report_server_url' => env('REPORT_SERVER_URL'),
    'report_server_user' => env('REPORT_SERVER_USER'),
    'report_server_password' => env('REPORT_SERVER_PASSWORD'),

    'mml_maastokartat_url' => env('MML_MAASTOKARTAT_URL'),
    'mml_kiinteistokartat_url' => env('MML_KIINTEISTOKARTAT_URL'),
    'mml_teemakartat_url' => env('MML_TEEMAKARTAT_URL'),

    'geoserver_url' => env('GEOSERVER_URL'),
    'geoserver_username' => env('GEOSERVER_USERNAME'),
    'geoserver_password' => env('GEOSERVER_PASSWORD'),
    'geoserver_namespace' => env('GEOSERVER_NAMESPACE'),
    'geoserver_workspace' => env('GEOSERVER_WORKSPACE'),
    'geoserver_datastore' => env('GEOSERVER_DATASTORE'),

    'geoserver_reporturl' => env('GEOSERVER_REPORTURL'),
    'mip_backend_url' => env('MIP_BACKEND_URL'),

    'kohde_raportti_url' => env('KOHDE_RAPORTTI_URL'),
    'alue_raportti_url' => env('ALUE_RAPORTTI_URL'),
    'kiinteisto_raportti_url' => env('KIINTEISTO_RAPORTTI_URL'),
    'mip_reportserver_url' => env('MIP_REPORTSERVER_URL'),

    'mip_kayttoohje_url' => env('MIP_KAYTTOOHJE_URL'),
    'mip_releasenotes_url' => env('MIP_RELEASENOTES_URL'),

    'kyppi_username' => env('KYPPI_USERNAME'),
    'kyppi_password' => env('KYPPI_PASSWORD'),
    'kyppi_muinaisjaannos_uri' => env('KYPPI_MUINAISJAANNOS_URI'),
    'kyppi_muinaisjaannos_endpoint' => env('KYPPI_MUINAISJAANNOS_ENDPOINT'),
    'kyppi_haku_maakunnat' => env('KYPPI_HAKU_MAAKUNNAT'),
    'kyppi_haku_kunnat' => env('KYPPI_HAKU_KUNNAT'),
    'kyppi_admin_email' => env('KYPPI_ADMIN_EMAIL'),

    'finna_admin_email' => env('FINNA_ADMIN_EMAIL'),
    'finna_hakumaara' => env('FINNA_HAKUMAARA'),
    'finna_identifier' => env('FINNA_IDENTIFIER'),
    'finna_organisation' => env('FINNA_ORGANISATION'),
    'finna_arkeologinen_kokoelma' => env('FINNA_ARKEOLOGINEN_KOKOELMA'),
    'finna_kokoelma' => env('FINNA_KOKOELMA'),
    'finna_museo_url' => env('FINNA_MUSEO_URL'),
    'finna_repository_name' => env('FINNA_REPOSITORY_NAME'),
    'finna_repository_identifier' => env('FINNA_REPOSITORY_IDENTIFIER'),

    'frontend_url' => env('FRONTEND_URL'),
    'email_from' => env('EMAIL_FROM'),
    'kyppi_nightly_starttime' => env('KYPPI_NIGHTLY_STARTTIME'),
    'mml_kiinteisto_queries_enabled' => env('MML_KIINTEISTO_QUERIES_ENABLED')

];

<?php

namespace Config;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

public class Config
{
    use SoftDeletes;

    protected $table = 'config';
    protected $fillable = ['key', 'value'];
    protected $dates = ['deleted_at'];
}

public function getConfig($key)
{
    $config = Config::where('key', $key)->first();
    return $config ? $config->value : null;
}

public function get_browser($argv)
{
    $user_agent = $argv['HTTP_USER_AGENT'];
    $browser = "N/A";
    $browser_array = array(
        '/msie/i' => 'Internet Explorer',
        '/firefox/i' => 'Firefox',
        '/safari/i' => 'Safari',
        '/chrome/i' => 'Chrome',
        '/edge/i' => 'Edge',
        '/opera/i' => 'Opera',
        '/netscape/i' => 'Netscape',
        '/maxthon/i' => 'Maxthon',
        '/konqueror/i' => 'Konqueror',
        '/mobile/i' => 'Handheld Browser'
    );

    foreach ($browser_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $browser = $value;
        }
    }

    return $browser;
}

public class get_called_class{
    foreach ($variable as $key => $value) {
        /**
         * @param Type {index}
         * @var call {native}
         */
    }

    if (condition) {
        $retVal = (condition) ? a : b ;
        $browser_array = (switch) ? a != b;
    }
    
    $this->assertJsonFileEqualsJsonFile($expectedFile, $actualFile);
}

?>
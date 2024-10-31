<?php

class Pricimizer_Helper
{
    // Cache values in memory for current request cycle to prevent multiple database queries or API requests
    private static $memoryCached;

    /**
     * @param $ip
     * @param $purpose
     * @param $deepDetect
     * @return array|string|null
     */
    public static function getLocationInfo($ip = null, $purpose = 'location', $deepDetect = true)
    {
        // Memory cache check
        $memoryCacheKey = __METHOD__ . '.' . sha1(implode('.', func_get_args()));
        if (isset(self::$memoryCached[$memoryCacheKey])) {
            if (self::$memoryCached[$memoryCacheKey] === false) {
                return null;
            }
            return self::$memoryCached[$memoryCacheKey];
        }

        // Database cache check (transient)
        $transientKey = 'pricimizer_location_' . sha1($ip) . '_' . sanitize_key($purpose);
        $cached = get_transient($transientKey);
        if (!empty($cached)) {
            return $cached;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
            if ($deepDetect) {
                if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
                    $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
                }
                if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
                    $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
                }
            }
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $output = null;
        $purpose = str_replace(["name", "\n", "\t", " ", "-", "_"], null, strtolower(trim($purpose)));
        $support = ["country", "countrycode", "state", "region", "city", "location", "address"];
        $continents = [
            "AF" => "Africa",
            "AN" => "Antarctica",
            "AS" => "Asia",
            "EU" => "Europe",
            "OC" => "Australia (Oceania)",
            "NA" => "North America",
            "SA" => "South America",
        ];
        if (in_array($purpose, $support)) {
            $body = wp_remote_retrieve_body(wp_remote_get('http://www.geoplugin.net/json.gp?ip=' . $ip));
            if (empty($body)) {
                self::$memoryCached[$memoryCacheKey] = false;
                return null;
            }
            $ipdat = @json_decode($body);
            if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
                switch ($purpose) {
                    case "location":
                        $output = [
                            "city" => @$ipdat->geoplugin_city,
                            "state" => @$ipdat->geoplugin_regionName,
                            "country" => @$ipdat->geoplugin_countryName,
                            "country_code" => @$ipdat->geoplugin_countryCode,
                            "continent" => @$continents[strtoupper($ipdat->geoplugin_continentCode)],
                            "continent_code" => @$ipdat->geoplugin_continentCode,
                        ];
                        break;
                    case "address":
                        $address = [$ipdat->geoplugin_countryName];
                        if (@strlen($ipdat->geoplugin_regionName) >= 1) {
                            $address[] = $ipdat->geoplugin_regionName;
                        }
                        if (@strlen($ipdat->geoplugin_city) >= 1) {
                            $address[] = $ipdat->geoplugin_city;
                        }
                        $output = implode(", ", array_reverse($address));
                        break;
                    case "city":
                        $output = @$ipdat->geoplugin_city;
                        break;
                    case "region":
                    case "state":
                        $output = @$ipdat->geoplugin_regionName;
                        break;
                    case "country":
                        $output = @$ipdat->geoplugin_countryName;
                        break;
                    case "countrycode":
                        $output = @$ipdat->geoplugin_countryCode;
                        break;
                }
            }
        }

        // Remember country of the ip for 24 hours
        if (!empty($output)) {
            set_transient($transientKey, $output, 24 * 60 * 60);
        }

        // Remember value for the whole current request
        self::$memoryCached[$memoryCacheKey] = $output;

        return $output;
    }

    /**
     * @return string
     */
    public static function detectOs()
    {
        // Memory cache check
        $memoryCacheKey = __METHOD__ . '.' . sha1(implode('.', func_get_args()));
        if (isset(self::$memoryCached[$memoryCacheKey])) {
            return self::$memoryCached[$memoryCacheKey];
        }

        if (isset($_SERVER)) {
            $agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
        } else {
            global $HTTP_SERVER_VARS;
            if (isset($HTTP_SERVER_VARS)) {
                $agent = sanitize_text_field($HTTP_SERVER_VARS['HTTP_USER_AGENT']);
            } else {
                global $HTTP_USER_AGENT;
                $agent = sanitize_text_field($HTTP_USER_AGENT);
            }
        }
        $ros[] = ['android', 'Android'];
        $ros[] = ['blackberry', 'BlackBerry'];
        $ros[] = ['iphone', 'Apple'];
        $ros[] = ['ipad', 'Apple'];
        $ros[] = ['ipod', 'Apple'];
        $ros[] = ['Windows XP', 'Windows'];
        $ros[] = ['Windows NT 5.1|Windows NT5.1', 'Windows'];
        $ros[] = ['Windows 2000', 'Windows'];
        $ros[] = ['Windows NT 5.0', 'Windows'];
        $ros[] = ['Windows NT 4.0|WinNT4.0', 'Windows'];
        $ros[] = ['Windows NT 5.2', 'Windows'];
        $ros[] = ['Windows NT 6.0', 'Windows'];
        $ros[] = ['Windows NT 7.0', 'Windows'];
        $ros[] = ['Windows CE', 'Windows CE'];
        $ros[] = ['(media center pc).([0-9]{1,2}\.[0-9]{1,2})', 'Windows'];
        $ros[] = ['(win)([0-9]{1,2}\.[0-9x]{1,2})', 'Windows'];
        $ros[] = ['(win)([0-9]{2})', 'Windows'];
        $ros[] = ['(windows)([0-9x]{2})', 'Windows'];
        $ros[] = ['Windows ME', 'Windows'];
        $ros[] = ['Win 9x 4.90', 'Windows'];
        $ros[] = ['Windows 98|Win98', 'Windows'];
        $ros[] = ['Windows 95', 'Windows'];
        $ros[] = ['(windows)([0-9]{1,2}\.[0-9]{1,2})', 'Windows'];
        $ros[] = ['win32', 'Windows'];
        $ros[] = ['(java)([0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2})', 'Java'];
        $ros[] = ['(Solaris)([0-9]{1,2}\.[0-9x]{1,2}){0,1}', 'Solaris'];
        $ros[] = ['dos x86', 'DOS'];
        $ros[] = ['unix', 'Unix'];
        $ros[] = ['Mac OS X', 'Mac OS X'];
        $ros[] = ['Mac_PowerPC', 'Macintosh PowerPC'];
        $ros[] = ['(mac|Macintosh)', 'Mac OS'];
        $ros[] = ['(sunos)([0-9]{1,2}\.[0-9]{1,2}){0,1}', 'SunOS'];
        $ros[] = ['(beos)([0-9]{1,2}\.[0-9]{1,2}){0,1}', 'BeOS'];
        $ros[] = ['(risc os)([0-9]{1,2}\.[0-9]{1,2})', 'RISC OS'];
        $ros[] = ['os/2', 'OS/2'];
        $ros[] = ['freebsd', 'FreeBSD'];
        $ros[] = ['openbsd', 'OpenBSD'];
        $ros[] = ['netbsd', 'NetBSD'];
        $ros[] = ['irix', 'IRIX'];
        $ros[] = ['plan9', 'Plan9'];
        $ros[] = ['osf', 'OSF'];
        $ros[] = ['aix', 'AIX'];
        $ros[] = ['GNU Hurd', 'GNU Hurd'];
        $ros[] = ['(fedora)', 'Linux'];
        $ros[] = ['(kubuntu)', 'Linux'];
        $ros[] = ['(ubuntu)', 'Linux'];
        $ros[] = ['(debian)', 'Linux'];
        $ros[] = ['(CentOS)', 'Linux'];
        $ros[] = ['(Mandriva).([0-9]{1,3}(\.[0-9]{1,3})?(\.[0-9]{1,3})?)', 'Linux'];
        $ros[] = ['(SUSE).([0-9]{1,3}(\.[0-9]{1,3})?(\.[0-9]{1,3})?)', 'Linux'];
        $ros[] = ['(Dropline)', 'Linux'];
        $ros[] = ['(ASPLinux)', 'Linux'];
        $ros[] = ['(Red Hat)', 'Linux'];
        $ros[] = ['(linux)', 'Linux'];
        $ros[] = ['(amigaos)([0-9]{1,2}\.[0-9]{1,2})', 'AmigaOS'];
        $ros[] = ['amiga-aweb', 'AmigaOS'];
        $ros[] = ['amiga', 'Amiga'];
        $ros[] = ['AvantGo', 'PalmOS'];
        $ros[] = ['[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,3}', 'Linux'];
        $ros[] = ['(webtv)/([0-9]{1,2}\.[0-9]{1,2})', 'WebTV'];
        $ros[] = ['Dreamcast', 'Dreamcast OS'];
        $ros[] = ['GetRight', 'Windows'];
        $ros[] = ['go!zilla', 'Windows'];
        $ros[] = ['gozilla', 'Windows'];
        $ros[] = ['gulliver', 'Windows'];
        $ros[] = ['ia archiver', 'Windows'];
        $ros[] = ['NetPositive', 'Windows'];
        $ros[] = ['mass downloader', 'Windows'];
        $ros[] = ['microsoft', 'Windows'];
        $ros[] = ['offline explorer', 'Windows'];
        $ros[] = ['teleport', 'Windows'];
        $ros[] = ['web downloader', 'Windows'];
        $ros[] = ['webcapture', 'Windows'];
        $ros[] = ['webcollage', 'Windows'];
        $ros[] = ['webcopier', 'Windows'];
        $ros[] = ['webstripper', 'Windows'];
        $ros[] = ['webzip', 'Windows'];
        $ros[] = ['wget', 'Windows'];
        $ros[] = ['Java', 'Unknown'];
        $ros[] = ['flashget', 'Windows'];
        $ros[] = ['MS FrontPage', 'Windows'];
        $ros[] = ['(msproxy)/([0-9]{1,2}.[0-9]{1,2})', 'Windows'];
        $ros[] = ['(msie)([0-9]{1,2}.[0-9]{1,2})', 'Windows'];
        $ros[] = ['libwww-perl', 'Unix'];
        $ros[] = ['UP.Browser', 'Windows'];
        $ros[] = ['NetAnts', 'Windows'];
        $file = count($ros);
        $os = '';
        for ($n = 0; $n < $file; $n++) {
            if (preg_match('/'.$ros[$n][0].'/i', $agent, $name)) {
                $os = @$ros[$n][1];
                break;
            }
        }
        $os = trim($os);

        // Remember value for the whole current request
        self::$memoryCached[$memoryCacheKey] = $os;

        return $os;
    }

    /**
     * @return mixed
     */
    public static function getUserIP()
    {
        // Memory cache check
        $memoryCacheKey = __METHOD__ . '.' . sha1(implode('.', func_get_args()));
        if (isset(self::$memoryCached[$memoryCacheKey])) {
            return self::$memoryCached[$memoryCacheKey];
        }

        // Get real visitor IP behind CloudFlare network
        $clientIp = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']) :sanitize_text_field(@$_SERVER['HTTP_CLIENT_IP']);
        $forwardIp = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']) : sanitize_text_field(@$_SERVER['HTTP_X_FORWARDED_FOR']);
        $remoteIp = sanitize_text_field($_SERVER['REMOTE_ADDR']);

        if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
            $ip = $clientIp;
        } else {
            if (filter_var($forwardIp, FILTER_VALIDATE_IP)) {
                $ip = $forwardIp;
            } else {
                $ip = $remoteIp;
            }
        }

        // Remember value for the whole current request
        self::$memoryCached[$memoryCacheKey] = $ip;

        return $ip;
    }
}
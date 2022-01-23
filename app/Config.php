<?php
namespace App;

class Config {
    public static function define_consts() : void {
        define("TAGS",[
            'a' => 'a',
            'li' => 'li',
            'img' => 'img',
            'meta' => 'meta',
            'link' => 'link',
            'form' => 'form',
            'table' => 'table',
            'script' => 'script',
        ]);
        define("NL", "\n");
        define("TABS", "\t\t\t\t\t\t");
        define("COLOR_PREFIX", "\033");
        define("COLOR_RESET",COLOR_PREFIX."[0m");
        define("COLOR_RED",COLOR_PREFIX."[31m");
        define("COLOR_GREEN",COLOR_PREFIX."[32m");
        define("COLOR_YELLOW",COLOR_PREFIX."[33m");
        define("COLOR_BLUE",COLOR_PREFIX."[34m");
        define("COLOR_MAGENTA",COLOR_PREFIX."[35m");
        define("COLOR_LIGHT_BLUE",COLOR_PREFIX."[94m");
        define("COLOR_CYAN",COLOR_PREFIX."[36m");
        define("COLOR_WHITE",COLOR_PREFIX."[37m");
        define("URL_REGEX", "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,5}(\/\S*)?/");
        // .(x)apk | .ipa | .mp3(4) | .jp(e)g | .png | .pdf | .doc(x) | .odt | .xls
        define("CONTENT_REGEX", "/(\.(x{0,1})(apk$))|(\.ipa$)|\.mp{1}[(3{0,1})|(4{0,1})]$|\.jp(e{0,1})g$|(\.png$)|(\.pdf$)|(\.doc(x){0,1}$)|(\.odt$)|(\.xls(x){0,1}$)/");
        // .js | javascript url | .php | .py | .asp(x)
        define("SCRIPT_REGEX", "/(\.js$)|(\.php$)|(^javascript:)|(\.py$)|(\.asp(x){0,1}$)/");
        define("BROKEN_URL_REGEX", "/(^(\s)+$)|(^_blank)|(^[_|#])/");
        define("MAX_RETRY_CONN_NUM", 5);
        define("TOR_SOCKS5_ADDR", '127.0.0.1:9050');
        
        if( ! defined('DATA_DIR')){
            define("DATA_DIR", "../scrapped/");
        }
        if( ! file_exists(DATA_DIR)){
            if( ! mkdir(DATA_DIR, 0755, true)){
                printf("> Error creating DATA DIR: [%s]\r\n", DATA_DIR);
                exit(1);
            }
        }
        return;
    }
}


?>
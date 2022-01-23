<?php
namespace App;

class Helper {

    public static function is_tor_running() : bool {
        return ! empty(exec("ps aux | grep -w [t]or"));
    }
    
    public static function create_entity_dir(string $target_dir) : bool {
        if( ! file_exists($target_dir)){
            if( ! mkdir($target_dir, 0755, true)){
                printf("> Error creating target dir: [%s]".NL, $target_dir);
                return false;
            }
            printf("> Target dir created: [%s]".NL, $target_dir);
            return true;
        }
        printf("> Target dir already exists: [%s]".NL, $target_dir);
        return true;
    }

    public static function format_file_size_to_str(int $size) : string {
        return strlen($size) > 6
                ? sprintf("%.2fMb", ($size / 1000000))
                : ((strlen($size) > 3 && strlen($size) < 7)
                    ? sprintf("%.2fkb", ($size / 1000))
                    : sprintf("%.2fb", $size)
                  );
    }

    public static function read_json(string $file) : array|object {
        if( ! file_exists($file)){
            return [];
        }
        $fp = fopen($file, 'r');
        $contents = '';
        while( ! feof($fp)){
            $contents .= fread($fp, 4096);
        }
        $json_decoded = json_decode($contents);
        return ! empty($json_decoded) ? $json_decoded : [];
    }

    public static function get_response_elements(int $code) : array {
        return (($code >= 200 && $code < 300)
               ? ['color' => COLOR_GREEN,
                  'response' => "Success",
                  'indent' => '',]
               :(($code >= 300 && $code < 400)
                    ? ['color' => COLOR_LIGHT_BLUE,
                       'response' => "Redir",
                       'indent' => ' ',]
                    : ['color' => COLOR_RED,
                       'response' => "Error",
                       'indent' => ' ',]
                )
        );
    }
}
?>
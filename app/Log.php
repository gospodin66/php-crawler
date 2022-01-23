<?php
namespace App;

class Log {
    
    



    public static function write_log(string $line, string $target) : bool {
        $res = file_put_contents($target, $line, FILE_APPEND);
        return $res !== false && $res > 0;
    }
}

?>
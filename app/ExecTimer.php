<?php
namespace App;

class ExecTimer {

    public static function start_timer() : float {
        return microtime(true);
    }

    public static function get_exec_time(float $time) : string {
        $color = $time < 5 ? COLOR_GREEN :($time > 5 && $time < 10 ? COLOR_YELLOW : COLOR_RED);
        return sprintf("{$color}%.2fs".COLOR_RESET, $time);
    }

}
?>
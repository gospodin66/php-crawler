<?php
namespace App;

use \DOMDocument;
use App\Helper;
use App\CurlHandler;
use App\Output;
use App\Validator;
use App\HTMLProcessor;
use App\ExecTimer;
use App\Log;

class Core {

    public static function init_crawler() : array {

        $short = "p:s:x:a:m:";
        $long = ["path:","scheme:","torprox:","args:","method:"];
        $opts = getopt($short,$long);

        if(count($opts) < 2){ 
            return ['error' => 1,
                    'errmsg' => "> Assign [-p]--path <example.com> ".
                                "[-s]--scheme <http/s>".
                                "(optional) [-x]--torprox <1/0>".
                                "(optional) [-a]--args<a1=x&a2=y>".
                                "(optional) [-m]--method<GET|POST>".
                                NL
                   ];
        }
        // mandatory
        $scheme = array_key_exists("scheme", $opts) ? trim($opts['scheme']) : trim($opts['s']);
        $domain = array_key_exists("path", $opts) ? trim($opts['path']) : trim($opts['p']);

        if(preg_match('/^((http|https)\:\/\/)/', $domain)){
            $domain = preg_replace('/^((http|https)\:\/\/)/', '', $domain);
        }
        // proxy
        $prox_opt = (count($opts) >= 3) 
                ? (array_key_exists("torprox", $opts)
                    ? intval(trim($opts['torprox']))
                    : intval(trim($opts['x'])))
                : 0;
        if($prox_opt !== 1){ 
            $prox_opt = 0;
        }
        if($prox_opt === 1 && ! Helper::is_tor_running()) {
            $errmsg = "Optional argument proxy uses tor extension.".NL;
            return ['errmsg' => $errmsg];
        }
        if($prox_opt === 1) {
            printf(NL."> Using Tor SOCKS5 proxy".NL);
        }
        // args
        $args = (count($opts) >= 4) 
            ? (array_key_exists("args", $opts)
                ? trim($opts['args'])
                : trim($opts['a']))
            : "";
        // method
        if(count($opts) === 5){
            $method = array_key_exists("method", $opts)
                    ? strtoupper(trim($opts['method']))
                    : strtoupper(trim($opts['m']));
            if($method !== "POST" && $method !== "GET"){
                $method = "GET";
                printf("> Switched to default request method: GET".NL);
            }
        } else { $method = "GET"; }

        return [
            'scheme' => $scheme,
            'domain' => $domain,
            'prox_opt' => $prox_opt,
            'args' => $args,
            'method' => $method,
        ];
    }


    public static function follow_links(
        CurlHandler $curl_handler, // GLOBAL CURL HANDLER OBJ
        \DOMDocument $doc,
        string $domain,
        string $scheme,
        int $prox_opt,
        string $target_dir
    ) : void
    {
        // prev_res vars are used to calc indentation of next line
        $prev_res_size = $prev_res_exectime = $prev_res_http_code = $cnt = 0;
        $all_elements = $crawled = [];

        Helper::create_entity_dir("{$target_dir}/links");

        $out = new Output;
        $validator = new Validator;
        $html_proc = new HTMLProcessor;
        
        $href_links = $doc->getElementsByTagName('a');
        $hrefs_total = count($href_links) -1;
        $app_start = microtime(true);
        $close_curl_handle = false;
        
        foreach ($href_links as $key => $a) {

            $url = trim($a->getAttribute('href'));
            $time = date("Y-m-d H:i:s");
            $init_indent = strlen($key) === 1 ? 23 : 24;

            $indent_len = (
                $init_indent
                + strlen($key)
                + strlen($hrefs_total)
                + strlen($prev_res_size)
                + strlen($prev_res_exectime)
                + strlen($prev_res_http_code)
            );
            
            if( ! $validator
                ->validate_url(
                    $url,
                    $scheme,
                    $domain,
                    $key,
                    $hrefs_total, 
                    $indent_len, 
                    $crawled, 
                    $target_dir,
                    $curl_handler
                )
              )
            {
                continue;
            }
            if($key === $hrefs_total){
                $close_curl_handle = true;
            }
            
            $curl_start = ExecTimer::start_timer();
            $results = $curl_handler->exec_curl_request($scheme, $url, $prox_opt, $close_curl_handle);
            $exectime = ExecTimer::get_exec_time(microtime(true) - $curl_start);
            $prev_res_exectime = $exectime;

            if(@!$results['status'] && ! empty($results['error'])){
                $out->display_formatted_output([
                    'response' => "Error",
                    'crawled_total' => count($crawled),
                    'key' => $key,
                    'hrefs_total' => $hrefs_total,
                    'exectime' => $exectime,
                    'url' => $url,
                    'http_code' => $results['info']['http_code'] ?? 0,
                    'indent_len' => $indent_len,
                    'res_size' => 0,
                    'errmsg' => $results['error'],
                ]);
                Log::write_log("> [{$time}] > {$results['error']}".NL, "{$target_dir}/log.txt");

                // manual curl close should occur only when last request throws err|warning
                if($key === $hrefs_total){
                    $curl_handler->manual_curl_close();
                }
                continue;
            }
            
            // re-send request if 403 => indent both as no results are returned
            if($results['info']['http_code'] === 403){
                printf("%-{$indent_len}s  [%s]".NL, ' ',  $url);
                // retry 5 times
                for($i = 1; $i <= MAX_RETRY_CONN_NUM; $i++){

                    printf("%-{$indent_len}s  [".COLOR_YELLOW."HTTP response status 403, re-send attempt: [{$i}/".MAX_RETRY_CONN_NUM."]".COLOR_RESET."]".NL, ' ');
                    $results = $curl_handler->exec_curl_request($scheme, $url, $prox_opt, $close_curl_handle);

                    if(@!$results['status'] && ! empty($results['error'])){
                        $out->display_formatted_output([
                            'response' => "Error",
                            'crawled_total' => count($crawled),
                            'key' => $key,
                            'hrefs_total' => $hrefs_total,
                            'exectime' => $exectime,
                            'url' => $url,
                            'http_code' => $results['info']['http_code'],
                            'indent_len' => $indent_len,
                            'res_size' => 0,
                            'errmsg' => $results['error'],
                        ]);
                        Log::write_log("> [{$time}] > {$results['error']} > attempts: [{$i}/".MAX_RETRY_CONN_NUM."]".NL, "{$target_dir}/log.txt");
                    }
                    // manual curl close should occur only when last request throws err|warning
                    if($key === $hrefs_total){
                        $curl_handler->manual_curl_close();
                    }
                    if($results['info']['http_code'] !== 403){
                        break;
                    }
                }
            }
            if($results['info']['http_code'] >= 400){
                $errmsg = "HTTP error response code";
                $out->display_formatted_output([
                    'response' => "Error",
                    'crawled_total' => count($crawled),
                    'key' => $key,
                    'hrefs_total' => $hrefs_total,
                    'exectime' => $exectime,
                    'url' => $url,
                    'http_code' => $results['info']['http_code'],
                    'indent_len' => $indent_len,
                    'res_size' => 0,
                    'errmsg' => $errmsg,
                ]);
                Log::write_log("> [{$time}] > [{$errmsg}] > [{$results['info']['http_code']}]".NL, "{$target_dir}/log.txt");
                // manual curl close should occur only when last request throws err|warning
                if($key === $hrefs_total){
                    $curl_handler->manual_curl_close();
                }
                continue;
            }

            $crawled[] = $url;
            $res_size = Helper::format_file_size_to_str($results['info']['size_download']);
            $prev_res_size = $res_size;

            Log::write_log("> [{$time}] > URL: [{$url}]".NL.
                sprintf(TABS."> Host: %s | down/up speed: %s/%s".NL,
                    $results['host'],
                    Helper::format_file_size_to_str($results['info']['speed_download']),
                    Helper::format_file_size_to_str($results['info']['speed_upload'])
                ).
                sprintf(TABS."> Page size: [%s]".NL,
                    Helper::format_file_size_to_str(strlen($results['page']))
                ).
                sprintf(TABS."> Len: [%s]".NL, $res_size)
                ,
                "{$target_dir}/log.txt"
            );

            $res_elements = Helper::get_response_elements($results['info']['http_code']);
            $prev_res_http_code = $results['info']['http_code'];

            //::::::::::::::MAIN OUTPUT:::::::::::::://
            $out->display_formatted_output([
                'response' => $results['info']['http_code'] === 200 ? "Success" : "Error",
                'crawled_total' => count($crawled),
                'key' => $key,
                'hrefs_total' => $hrefs_total,
                'exectime' => $exectime,
                'url' => $url,
                'http_code' => $results['info']['http_code'] ?? 0,
                'indent_len' => 0,
                'res_size' => $res_size,
                'errmsg' => $results['error'] ?? '',
            ]);
            //:::::::::::///MAIN OUTPUT///::::::::::://

            if(empty($results['page'])){
                $errmsg = "Empty page";
                $out->display_formatted_output([
                    'response' => "Error",
                    'crawled_total' => count($crawled),
                    'key' => $key,
                    'hrefs_total' => $hrefs_total,
                    'exectime' => $exectime,
                    'url' => $url,
                    'http_code' => $results['info']['http_code'] ?? 0,
                    'indent_len' => $indent_len,
                    'res_size' => $res_size,
                    'errmsg' => $errmsg,
                ]);
                Log::write_log("> [{$time}] > [{$errmsg}] > [{$results['info']['http_code']}]".NL, "{$target_dir}/log.txt");
                continue;
            }
            
            $collected_elements = [];
            $doc = new DOMDocument;
            @$doc->loadHTML($results['page']);

            foreach(TAGS as $tag){
                $collected_elements[$tag] = $html_proc->extract_html_elements($doc,$tag,$url);
            }
            $all_elements[$url] = $collected_elements;
        }

        $app_exectime = ExecTimer::get_exec_time(microtime(true) - $app_start);

        // print "\x07"; // beep
        printf(NL."> Exec time: %s".NL, $app_exectime);

        Output::display_options(
            false, /** main_opts flag **/
            $all_elements,
            parse_url($url),
            $target_dir,
            $curl_handler,
            $doc,
            $scheme,
            $prox_opt,
        );
        return;
    }
}

?>
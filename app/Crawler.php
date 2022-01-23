#!/usr/bin/php
<?php
namespace App;

ob_implicit_flush(true);

require 'PrimitiveAutoloader.php';

use App\Config;
use App\CurlHandler;
use App\Helper;
use App\Log;
use App\HTMLProcessor;
use App\Output;
use \DOMDocument;

$result = run_crawler();
exit($result);

/**::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::**/

function run_crawler() : int {
    
    Config::define_consts();

    if(PHP_SAPI !== 'cli') { 
        printf("%s", "Script needs to be ran in cli environment.".NL);
        exit(1);
    }
    if( ! extension_loaded('curl')) {
        printf("%s", "Script uses curl extension - php{version}-curl.".NL);
        exit(1);
    }
    if( ! extension_loaded('xml')) {
        printf("%s", "Script uses xml extension - php{version}-xml.".NL);
        exit(1);
    }

    $collected_elements = [];
    $base_indent = " ";
    $config = Core::init_crawler();
    
    if(array_key_exists("errmsg", $config)){
        printf("%s", $config['errmsg']);
        return 1;
    }
    
    $curl_handler = new CurlHandler;
    $html_proc = new HTMLProcessor;
    $doc = new DOMDocument;

    $public_ip = $curl_handler->get_public_ip($config['prox_opt']);
    $formatted_url = "{$config['scheme']}://{$config['domain']}";
    $parsed_url = parse_url($formatted_url);
    $target_dir = DATA_DIR.str_replace("/", "-", $parsed_url['host']);
    
    Helper::create_entity_dir("../session");
    Helper::create_entity_dir("{$target_dir}/results");
    
    $curl_start = ExecTimer::start_timer();
    $results = $curl_handler->exec_curl_request(
        $config['scheme'],
        $config['domain'],
        $config['prox_opt'],
        $config['args'],
        $config['method'],
    );
    $exectime = ExecTimer::get_exec_time((microtime(true) - $curl_start));

    if( ! $results['status'] === 'error' && ! empty($results['error'])){
        printf(
            "%s",
            "> Error: {$results['error']}".NL.
            "> Exit".NL
        );
        return 1;
    }
    if($results['info']['http_code'] === 403){
        $retry_cnt = 0;
        while(readline('> HTTP response status 403, re-send request? (y/n): ') === 'y'){
            $retry_cnt++;
            printf("> Re-send attempts: [{$retry_cnt}]".NL);
            $results = $curl_handler->exec_curl_request(
                $config['scheme'],
                $config['domain'],
                $config['prox_opt'],
                $config['args'],
                $config['method']
            );
            if( ! $results['status'] && ! empty($results['error'])){
                printf("%s",
                    "> Error: {$results['error']}".NL.
                    "> Exit".NL
                );
                return 1;
            }
            if($results['info']['http_code'] !== 403){
                break;
            }
        }
    }
    
    $res_size = Helper::format_file_size_to_str($results['info']['size_download']);
    $page_size = Helper::format_file_size_to_str(strlen($results['page']));
    
    Log::write_log(
        sprintf("> [".date("Y-m-d H:i:s")."] > Public IP: [".trim($public_ip)."]".NL).
        sprintf(TABS."> URL: {$config['domain']}".NL).
        sprintf(
            TABS."> Host: %s | down/up speed: %s/%s".NL,
            $results['host'],
            Helper::format_file_size_to_str($results['info']['speed_download']),
            Helper::format_file_size_to_str($results['info']['speed_upload'])
        ).
        sprintf(TABS."> Page size: [%s]".NL, $page_size).
        sprintf(TABS."> Len: [%s]".NL, $res_size).
        sprintf(TABS."> Response code: [%d]".NL, $results['info']['http_code'])
        ,
        "{$target_dir}/log.txt",
    );
    
    printf(
        "> Public IP: [".COLOR_CYAN."%s".COLOR_RESET."]".NL.
        "> Page size: [".COLOR_CYAN."%s".COLOR_RESET."]".NL.
        "> Length: %3s[".COLOR_CYAN."%s".COLOR_RESET."]".NL.
        "> Res code:%2s[".COLOR_CYAN."%d".COLOR_RESET."]".NL.
        "> Time: %5s[".COLOR_CYAN."%s".COLOR_RESET."]".NL
        ,
        trim($public_ip),
        $page_size,
        $base_indent,
        $res_size,
        $base_indent,
        $results['info']['http_code'],
        $base_indent,
        $exectime
    );

    @$doc->loadHTML($results['page']);
    
    $title = $doc->getElementsByTagName("title");
    $title = $title->item(0)->nodeValue;
    
    foreach(TAGS as $key => $tag){
        $collected_elements[] = $html_proc->extract_html_elements($doc,$tag,$formatted_url);
    }
    
    Output::display_options(
        true, /** main_opts flag **/
        $collected_elements,
        $parsed_url,
        $target_dir,
        $curl_handler,
        $doc,
        $config['scheme'],
        $config['prox_opt']
    );

    printf(COLOR_GREEN."> Finished".COLOR_RESET.NL);
    return 0;
}

?>
#!/usr/bin/php
<?php
/**
 * TODOS:::
 *         - format code => many cases can be shortened
 *      - 
 *          -
 *          -
 */
if (PHP_SAPI !== 'cli') { die("Script needs to be run as cli.\r\n"); }
$short = "p:s:x:a:m:";
$long  = ["path:","scheme:","torprox:","args:","method:"];
$opts = getopt($short,$long);
if(count($opts) < 2){ 
    die("> Assign [-p]--path <example.com> ".
                 "[-s]--scheme <http/s>".
                 "(optional) [-x]--torprox <1/0>".
                 "(optional) [-a]--args<a1=x&a2=y>".
                 "(optional) [-m]--method<GET|POST>\r\n"
       );
}
$domain = array_key_exists("path", $opts)   ? trim($opts['path'])   : trim($opts['p']);
$scheme = array_key_exists("scheme", $opts) ? trim($opts['scheme']) : trim($opts['s']);
if(preg_match('/^((http|https)\:\/\/)/', $domain)){
    $domain = preg_replace('/^((http|https)\:\/\/)/', '', $domain);
}
// prox_opt & args are optional
$prox_opt = (count($opts) >= 3) ? (array_key_exists("torprox", $opts) ? intval(trim($opts['torprox'])) : intval(trim($opts['x']))) : 0;
$args = (count($opts) >= 4) ? (array_key_exists("args", $opts) ? trim($opts['args']) : trim($opts['a'])) : "";

if(count($opts) === 5){
    $method = array_key_exists("method", $opts) ? trim($opts['method']) : trim($opts['m']);
    if($method !== "POST" && $method !== "GET"){
        $method = "GET";
        printf("> Switched to default request method: GET\r\n");
    }
} else {
    $method = "";
}
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
define("COLOR_PREFIX", "\033");
define("COLOR_RESET",COLOR_PREFIX."[0m");
define("COLOR_RED",COLOR_PREFIX."[31m");
define("COLOR_GREEN",COLOR_PREFIX."[32m");
define("COLOR_YELLOW",COLOR_PREFIX."[33m");
define("COLOR_BLUE",COLOR_PREFIX."[34m");
define("COLOR_LIGHT_BLUE",COLOR_PREFIX."[94m");
define("COLOR_CYAN",COLOR_PREFIX."[36m");
define("COLOR_WHITE",COLOR_PREFIX."[37m");
define("URL_REGEX", "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,5}(\/\S*)?/");
// .(x)apk | .ipa | .mp3(4) | .jp(e)g | .png | .pdf | .doc(x) | .odt | .xls
define("CONTENT_REGEX", "/(\.(x{0,1})(apk$))|(\.ipa$)|\.mp{1}[(3{0,1})|(4{0,1})]$|\.jp(e{0,1})g$|(\.png$)|(\.pdf$)|(\.doc(x){0,1}$)|(\.odt$)|(\.xls(x){0,1}$)/");
// .js | javascript url | .php | .py | asp(x)
define("SCRIPT_REGEX", "/(\.js$)|(\.php$)|(^javascript:)|(\.py$)|(\.asp(x){0,1}$)/");
define("BROKEN_URL_REGEX", "/(^(\s)+$)|(^_blank)|(^[_|#])/");
define("MAX_RETRY_CONN_NUM", 5);
if( ! defined('DATA_DIR')){
    define("DATA_DIR", "scrapped/");
}
if( ! file_exists(DATA_DIR)){
    mkdir(DATA_DIR);
}

if($prox_opt !== 1){ 
    $prox_opt = 0;
}
$public_ip = get_public_ip($prox_opt);
$parsed_url = parse_url("{$scheme}://{$domain}");
$target_dir = DATA_DIR.str_replace("/", "-", $parsed_url['host']);
create_entity_dir($target_dir);
create_entity_dir("{$target_dir}/results");
$log = "";
$curl_start = microtime(true);
$results = exec_curl_request($scheme, $domain, $prox_opt, $args, $method);
$curl_time = microtime(true) - $curl_start;
$exectime = get_exec_time($curl_time);

if(@!$results['status'] && ! empty($results['error'])){
    die("> Error: {$results['error']}\r\n> Exit\r\n");
}

if($results['info']['http_code'] === 403){
    $retry_cnt = 0;
    while(readline('> HTTP response status 403, re-send request? (y/n): ') === 'y'){
        $retry_cnt++;
        printf("> Re-send attempts: [{$retry_cnt}]\r\n");
        $results = exec_curl_request($scheme, $domain, $prox_opt, $args, $method);
        if(@!$results['status'] && ! empty($results['error'])){
            die("> Error: {$results['error']}\r\n> Exit\r\n");
        }
        if($results['info']['http_code'] !== 403){
            break;
        }
    }
}
$res_size = format_file_size_to_str($results['info']['size_download']);
$page_size = format_file_size_to_str(strlen($results['page']));

if($prox_opt === 1) {
    $log .= "> Using tor SOCKS5 proxy\r\n";
    printf("\r\n> Using Tor SOCKS5 proxy\r\n");
}

$log .= "> Public IP: [".trim($public_ip)."]\r\n".
        "> [".date("Y-m-d H:i:s")."] > URL: ".$domain."\r\n".
        sprintf("\t\t\t\t\t\t> Host: %s | down/up speed: %s/%s\r\n",
                    $results['host'],
                    format_file_size_to_str($results['info']['speed_download']),
                    format_file_size_to_str($results['info']['speed_upload'])
               ).
        sprintf("\t\t\t\t\t\t> Page size: [%s]\r\n", $page_size).
        sprintf("\t\t\t\t\t\t> Len: [%s]\r\n", $res_size).
        sprintf("\t\t\t\t\t\t> Response code: [%d]\r\n", $results['info']['http_code']);

printf("> Public IP: [".COLOR_CYAN."%s".COLOR_RESET."]\r\n".
       "> Page size: [".COLOR_CYAN."%s".COLOR_RESET."]\r\n".
       "> Length: %3s[".COLOR_CYAN."%s".COLOR_RESET."]\r\n".
       "> Res code:%2s[".COLOR_CYAN."%d".COLOR_RESET."]\r\n".
       "> Time: %5s[".COLOR_CYAN."%s".COLOR_RESET."]\r\n",
       trim($public_ip), $page_size, ' ', $res_size, ' ', $results['info']['http_code'], ' ', $exectime
      );

file_put_contents("{$target_dir}/log.txt", $log, FILE_APPEND);
$log = "";

$doc = new DOMDocument();
@$doc->loadHTML($results['page']);
$title = $doc->getElementsByTagName("title");
@$title = $title->item(0)->nodeValue;
$collected_elements = [];
foreach(TAGS as $key => $tag){
    $collected_elements[] = extract_html_elements($doc,$tag,"{$scheme}://{$domain}");
}
print "\r\n> Successfuly scrapped the main webpage\r\n";
while(1){
    $jpath = "{$target_dir}/results/main_scrap.json";
    print "\r\n> Options:".
            "\r\n> p -print main webpage results".
            "\r\n> s -save main webpage results".
            "\r\n> j -fetch elements by type".
            "\r\n> f -follow webpage links".
            "\r\n> e -exit\r\n";
    switch (readline("> ")) {
        case 'p':
            recursive_read_elements($collected_elements);
            printf("\r\n");
            break;

        case 'j':
            switch (readline("> Enter element to filter: ")) {
                case 'img':
                    $filter_el = 'img';
                    break;
                case 'form':
                    $filter_el = 'form';
                    break;
                case 'link':
                    $filter_el = 'link';
                    break;
                case 'meta':
                    $filter_el = 'meta';
                    break;
                case 'li':
                    $filter_el = 'li';
                    break;
                case 'table':
                    $filter_el = 'table';
                    break;
                case 'script':
                    $filter_el = 'script';
                    break;
                default:
                    $filter_el = 'a';
                    break;
            }
            $eles = read_json($jpath);
            printf("> File [%s] read successfuly\r\n", $jpath);
            $eles_by_type = [];
            recursive_filter_elements($eles, $eles_by_type, $filter_el);
            recursive_read_elements($eles_by_type);
            break;

        case 's':
            file_put_contents($jpath, json_encode($collected_elements));
            printf("\r\n> Successfuly saved file > [%s]\r\n", $jpath);
            break;
            
        case 'f':
            if( ! empty($parsed_url['port'])){
                $fmt_url = "{$parsed_url['host']}:{$parsed_url['port']}";
            } else {
                $fmt_url = $parsed_url['host'];
            }
            follow_links($opts,$doc,$fmt_url,$scheme,$prox_opt,$target_dir);
            break;

        case 'e':
            break 2;

         default:
            print(COLOR_RED."> Invalid argument".COLOR_RESET."\r\n");
             break;
     } 
}    
// print "\x07"; // beep 
printf(COLOR_GREEN."> Finished".COLOR_RESET."\r\n");
exit(0);

/******************************************************************************/


function create_entity_dir(string $target_dir) : bool {
    if( ! file_exists($target_dir)){
        if( ! mkdir($target_dir, 0755, true)){
            printf("> Error creating target dir: [%s]\r\n", $target_dir);
            return false;
        }
        printf("> Target dir created: [%s]\r\n", $target_dir);
        return true;
    }
    printf("> Target dir already exists: [%s]\r\n", $target_dir);
    return true;
}

function follow_links(array $opts, $doc, string $domain, string $scheme, int $prox_opt, string $target_dir){
    $href_links = $doc->getElementsByTagName('a');
    $crawled = [];
    $cnt = 0;
    $app_start = microtime(true);
    $hrefs_total = count($href_links) -1;
    create_entity_dir("{$target_dir}/links");
    $all_elements = [];
    $prev_res_size = $prev_res_exectime = $prev_res_http_code = 0;
    foreach ($href_links as $key => $a) {
        $log = "";
        $url = trim($a->getAttribute('href'));
        $time = date("Y-m-d H:i:s");
        $indent_len = (24 + strlen($key)
                          + strlen($hrefs_total)
                          + strlen($prev_res_size)
                          + strlen($prev_res_exectime)
                          + strlen($prev_res_http_code)
                      );
        // skip empty | broken
        if(empty($url) || preg_match(BROKEN_URL_REGEX, $url)) {
            printf("> ".COLOR_YELLOW."Warning ".COLOR_RESET.
                    " [%d] [%d/%d] ".
                    " [".COLOR_YELLOW."%d".COLOR_RESET."]%-7s ".
                    " [".COLOR_YELLOW."%d".COLOR_RESET."]%-4s ".
                    " [".COLOR_YELLOW."%d".COLOR_RESET."]%-2s ".
                    " [%s]\r\n".
                    "%-{$indent_len}s [".COLOR_YELLOW."Empty/broken path".COLOR_RESET."]\r\n",
                    count($crawled), $key, $hrefs_total,
                    0, ' ',// res_size
                    0, ' ',// exectime
                    0, ' ',// http_code
                    $url,
                    ' ',
                  );
            $log .= "> [{$time}] > Warning | [Empty/broken path] > [{$url}]\r\n";
            file_put_contents("{$target_dir}/log.txt", $log, FILE_APPEND);
            continue;    
        }
        // do not download content!
        else if(preg_match(CONTENT_REGEX, $url, $matches)) {
            $remote_filesize = exec_remote_filesize_request($url);
            $remote_filesize_final = format_file_size_to_str($remote_filesize);
            printf("> ".COLOR_YELLOW."Warning ".COLOR_RESET.
                        " [%d] [%d/%d] ".
						" [".COLOR_YELLOW."%d".COLOR_RESET."]%-7s ".
						" [".COLOR_YELLOW."%d".COLOR_RESET."]%-4s ".
						" [".COLOR_YELLOW."%d".COLOR_RESET."]%-2s ".
                        " [%s]\r\n".
                        "%-{$indent_len}s [".COLOR_YELLOW."Content URL ".COLOR_RESET.
                        "> [".COLOR_YELLOW."%s".COLOR_RESET."] ".
                        "> [".COLOR_YELLOW."%s".COLOR_RESET."]]\r\n",
                        count($crawled), $key, $hrefs_total,
						0, ' ',// res_size
						0, ' ',// exectime
						0, ' ',// http_code
                        $url,
                        ' ',
                        $remote_filesize_final,
                        $matches[0],
                  );
            $log .= "> [{$time}] > Warning | [Content URL > [{$remote_filesize_final}] > [{$url}]]\r\n";
            file_put_contents("{$target_dir}/results/content.txt", "{$url}\r\n", FILE_APPEND);
            file_put_contents("{$target_dir}/log.txt", $log, FILE_APPEND);
            continue;
        }
        // do not exec scripts!
        else if(preg_match(SCRIPT_REGEX, $url, $matches)) {
            printf("> ".COLOR_YELLOW."Warning ".COLOR_RESET.
                        " [%d] [%d/%d] ".
						" [".COLOR_YELLOW."%d".COLOR_RESET."]%-7s ".
						" [".COLOR_YELLOW."%d".COLOR_RESET."]%-4s ".
						" [".COLOR_YELLOW."%d".COLOR_RESET."]%-2s ".
                        " [%s]\r\n".
                        "%-{$indent_len}s [".COLOR_YELLOW."Script format: %s".COLOR_RESET."]\r\n",
                        count($crawled), $key, $hrefs_total,
						0, ' ',// res_size
						0, ' ',// exectime
						0, ' ',// http_code
                        $url,
                        ' ',
                        $matches[0],
                  );
            $log .= "> [{$time}] > Warning | [Script format: {$matches[0]}] > [{$url}]\r\n";
            file_put_contents("{$target_dir}/log.txt", $log, FILE_APPEND);
            continue;
        }
        // skip mailto: | tel:
        else if(preg_match("/^(mailto:|tel:)/", $url)) {
            printf("> ".COLOR_YELLOW."Warning ".COLOR_RESET.
                        " [%d] [%d/%d] ".
						" [".COLOR_YELLOW."%d".COLOR_RESET."]%-7s ".
						" [".COLOR_YELLOW."%d".COLOR_RESET."]%-4s ".
						" [".COLOR_YELLOW."%d".COLOR_RESET."]%-2s ".
                        " [%s]\r\n".
                        " [".COLOR_YELLOW."Email/tel format".COLOR_RESET."]\r\n",
                        ' ',
                        count($crawled), $key, $hrefs_total,
						0, ' ',// res_size
						0, ' ',// exectime
						0, ' ',// http_code
                        $url,
                  );
            $log .= "> [{$time}] > Warning | [Email/tel format] > [{$url}]\r\n";
            file_put_contents("{$target_dir}/log.txt", $log, FILE_APPEND);
            continue;
        }

        // invalid url format => adapt
        if(preg_match(URL_REGEX, $url) === 0) {
            // save rel links before adaptation
            file_put_contents("{$target_dir}/links/rel.txt", "{$url}\r\n", FILE_APPEND);
            if($url[0] === "/" && substr($url, 0, 2) !== "//") {
                $url = "{$scheme}://{$domain}{$url}";
            } else if(substr($url, 0, 2) === "./") {
                // => example.com/./some/path = example.com/path
                $url = "{$scheme}://{$domain}/".basename($url);
            } else if(substr($url, 0, 3) === "../") {
                // => example.com/../some/path = example.com/path
                $url = "{$scheme}://{$domain}/".realpath($url).basename($url);
            } else {
                $url = "{$scheme}://{$domain}/{$url}";
            }
        } else { 
            file_put_contents("{$target_dir}/links/abs.txt", "{$url}\r\n", FILE_APPEND);
        }


        if(in_array($url, $crawled)){
            continue;  
        }
        if( ! validate_base_domain($domain,$url,$scheme)){
            printf("> ".COLOR_YELLOW."Warning ".COLOR_RESET.
                " [%d] [%d/%d] ".
                " [".COLOR_YELLOW."%d".COLOR_RESET."]%-7s ".
                " [".COLOR_YELLOW."%d".COLOR_RESET."]%-4s ".
                " [".COLOR_YELLOW."%d".COLOR_RESET."]%-2s ".
                " [%s]\r\n".
                "%-{$indent_len}s [".COLOR_YELLOW."Invalid base domain".COLOR_RESET."]\r\n",
                count($crawled), $key, $hrefs_total,
                0, ' ',// res_size
                0, ' ',// exectime
                0, ' ',// http_code
                $url,
                ' ',
              );
            $log .= "> [{$time}] > Invalid base domain [{$url}]\r\n";
            continue;
        }


        $curl_start = microtime(true);
        $results = exec_curl_request($scheme, $url, $prox_opt);
        $curl_time = microtime(true) - $curl_start;
        $exectime = get_exec_time($curl_time);
        $prev_res_exectime = $exectime;
        if(@!$results['status'] && ! empty($results['error'])){
            printf("> ".COLOR_RED."Error".COLOR_RESET."%3s".
                        " [%d] [%d/%d] ".
                        " [".COLOR_RED."%d".COLOR_RESET."]%-7s ".
                        " [".COLOR_RED."%s".COLOR_RESET."]%-4s ".
                        " [".COLOR_RED."%d".COLOR_RESET."]%-2s ".
                        " [%s]\r\n".
                        "%-{$indent_len}s [".COLOR_RED."%s".COLOR_RESET."]\r\n",
                        ' ',
                        count($crawled), $key, $hrefs_total,
                        0, ' ', 
                        $exectime, ' ',
                        $results['info']['http_code'], ' ',
                        $url,
                        ' ',
                        $results['error'],
                  );
            $log .= "> [{$time}] > {$results['error']}\r\n";
            continue;
        }       


        // re-send request if 403
        if($results['info']['http_code'] === 403){
            printf("%10s [%d] [%d/%d] ".
                        " [".COLOR_YELLOW."%d".COLOR_RESET."]%-7s ".
                        " [".COLOR_YELLOW."%d".COLOR_RESET."]%-4s ".
                        " [".COLOR_YELLOW."%d".COLOR_RESET."]%-2s ".
                        " [%s]\r\n",
                        ' ',
                        count($crawled), $key, $hrefs_total,
                        0, ' ',// res_size
                        0, ' ',// exectime
                        0, ' ',// http_code
                        $url,
                  );
            for($i = 1; $i <= MAX_RETRY_CONN_NUM; $i++){
                printf("%-{$indent_len}s [".COLOR_YELLOW."HTTP response status 403, re-send attempt: [{$i}/".MAX_RETRY_CONN_NUM."]".COLOR_RESET."]\r\n", ' ');
                $results = exec_curl_request($scheme, $url, $prox_opt);
                if(@!$results['status'] && ! empty($results['error'])){
                    $log .= "> [{$time}] > {$results['error']} > attempts: [{$i}/".MAX_RETRY_CONN_NUM."]\r\n";
                    printf("> ".COLOR_RED."Error".COLOR_RESET."%3s".
                            " [%d] [%d/%d] ".
                            " [".COLOR_RED."%d".COLOR_RESET."]%-8s ".
                            " [".COLOR_RED."%s".COLOR_RESET."] ".
                            " [".COLOR_RED."%d".COLOR_RESET."] ".
                            " [%s]\r\n".
                            "%-{$indent_len}s [".COLOR_RED."%s".COLOR_RESET."]\r\n",
                            ' ',
                            count($crawled), $key, $hrefs_total,
                            0, ' ', // 0 = request size
                            $exectime,
                            $results['info']['http_code'],
                            $url,
                            ' ',
                            $results['error'],
                          );
                }
                if($results['info']['http_code'] !== 403){
                    break;
                }
            }
        }
        if($results['info']['http_code'] >= 400){
            $errmsg = "HTTP error response code";
            printf("> ".COLOR_RED."Error".COLOR_RESET."%3s".
                    " [%d] [%d/%d] ".
                    " [".COLOR_RED."%d".COLOR_RESET."]%-8s".
                    " [".COLOR_RED."%s".COLOR_RESET."] ".
                    " [".COLOR_RED."%d".COLOR_RESET."] ".
                    " [%s]\r\n".
                    "%-{$indent_len}s [".COLOR_RED."%s".COLOR_RESET."]\r\n",
                    ' ',
                    count($crawled), $key, $hrefs_total,
                    0, ' ',
                    $exectime,
                    $results['info']['http_code'],
                    $url,
                    ' ',
                    $errmsg, 
                  );
            $log .= "> [{$time}] > [{$errmsg}] > [{$results['info']['http_code']}]\r\n";
            continue;
        }

        $crawled[] = $url;
        $res_size = format_file_size_to_str($results['info']['size_download']);
        $prev_res_size = $res_size;
        $log .= "> [{$time}] > URL: [{$url}]\r\n".
                sprintf("\t\t\t\t\t\t> Host: %s | down/up speed: %s/%s\r\n",
                            $results['host'],
                            format_file_size_to_str($results['info']['speed_download']),
                            format_file_size_to_str($results['info']['speed_upload'])
                       ).
                sprintf("\t\t\t\t\t\t> Page size: [%s]\r\n",
                            format_file_size_to_str(strlen($results['page']))
                       ).
                sprintf("\t\t\t\t\t\t> Len: [%s]\r\n", $res_size);
        file_put_contents("{$target_dir}/log.txt", $log, FILE_APPEND);
        $log = "";
        $res_elements = get_response_elements($results['info']['http_code']);
        $prev_res_http_code = $results['info']['http_code'];
        printf(
            "> {$res_elements['color']}%s".COLOR_RESET.
            ($results['info']['http_code'] === 200 ? "%s " : "%3s").
            " [%d] [%d/%d] ".
            " [{$res_elements['color']}%s".COLOR_RESET."]%-".(8 - strlen($res_size))."s ".
            " [{$res_elements['color']}%s".COLOR_RESET."] ".
            " [{$res_elements['color']}%d".COLOR_RESET."] ".
            " [%s]\r\n",
            $res_elements['response'],
            $res_elements['indent'],
            count($crawled) -1,
            $key, $hrefs_total,
            $res_size, ' ',
            $exectime,
            $results['info']['http_code'],
            $url,
        );

        $collected_elements = [];
        $doc = new DOMDocument();
        @$doc->loadHTML($results['page']);
        foreach(TAGS as $tag){
            $collected_elements["{$tag}.{$key}"] = extract_html_elements($doc,$tag,$url);
        }
        $all_elements[$url] = $collected_elements;
    }

    $collected_json = json_encode($all_elements);
    $app_time = microtime(true) - $app_start;
    $app_exectime = get_exec_time($app_time);
    printf("\r\n> Exec time: %s\r\n", $app_exectime);
    while(1)
    {
        printf("\r\n> Options: ".
                "\r\n> p -print all results".
                "\r\n> s -save [JSON file size: %s]".
                "\r\n> j -fetch elements by type".
                "\r\n> r -return\r\n",
                format_file_size_to_str(strlen($collected_json))
              );

        $resultsdir = "{$target_dir}/results";

        switch (readline("> "))
        {
            case 'r':
                break 2;

            case 'j':
                switch (readline("Enter element to filter: > ")) {
                    case 'img':
                        $filter_el = 'img';
                        break;
                    case 'form':
                        $filter_el = 'form';
                        break;
                    case 'link':
                        $filter_el = 'link';
                        break;
                    case 'meta':
                        $filter_el = 'meta';
                        break;
                    case 'table':
                        $filter_el = 'table';
                        break;
                    case 'script':
                        $filter_el = 'script';
                        break;
                    case 'li':
                        $filter_el = 'li';
                        break;
                    default:
                        $filter_el = 'a';
                        break;
                }
                $eles = read_json("{$resultsdir}/results.json");
                printf("> JSON file read successfuly > [{$resultsdir}/results.json]\r\n", $target_dir);
                $eles_by_type = fetch_html_elements_by_type($eles, $filter_el);
                if(file_put_contents("{$resultsdir}/results_filtered.json", json_encode($eles_by_type)) !== false){
                    printf("> Fitered elements saved to file successfuly > [%s/results_filtered.json]\r\n", $resultsdir);
                }
                recursive_read_elements($eles_by_type);
                break;

            case 's':
                file_put_contents("{$resultsdir}/results.json", $collected_json);
                printf("> ".COLOR_GREEN."Saved".COLOR_RESET." > File path: [%s/results.json]".COLOR_RESET."\r\n", $resultsdir);
                printf("\r\n");
                break;

            case 'p':
                recursive_read_elements($all_elements);
                printf("\r\n");    
                break;

             default:
                 printf(COLOR_RED."> Invalid argument".COLOR_RESET."\r\n");
                 break;
        } 
    }
    return;
}

function get_response_elements(int $code) : array {
    return (($code >= 200 && $code < 300)
           ? ['color' => COLOR_GREEN, 'response' => "Success", 'indent' => '',]
           :(($code >= 300 && $code < 400)
                ?['color' => COLOR_LIGHT_BLUE, 'response' => "Redir", 'indent' => ' ']
                :['color' => COLOR_RED, 'response' => "Error", 'indent' => ' ']
            )
    );
}

function exec_remote_filesize_request(string $url) : float {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FOLLOWLOCATION => 1, // dont want the size of redir request
        CURLOPT_HEADER => 1,
        CURLOPT_NOBODY => 1,
    ]);
    $data = curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    return round($size, 2);
}

function format_file_size_to_str(int $size) : string {
    $size_str = (string)$size;
    return strlen($size_str) > 6
            ? sprintf("%.2fMb", ($size / 1000000))
            : ((strlen($size_str) > 3 && strlen($size_str) < 7)
                ? sprintf("%.2fkb", ($size / 1000))
                : sprintf("%.2fb", $size)
              );
}

function get_exec_time(float $time) : string {
    $color = $time < 5 ? COLOR_GREEN :($time > 5 && $time < 10 ? COLOR_YELLOW : COLOR_RED);
    if($time < 60){
        $unit = "s";
    } else {
        // convert to mins => scaled to 100, not 60
        $time = $time / 60;
        $unit = "min";
    }
    return sprintf("{$color}%.2f{$unit}".COLOR_RESET, $time);
}

function exec_curl_request(
    string $scheme,
    string $url,
    int $prox_opt,
    string $args = "",
    string $method = ""
) : array
{
    $ch = curl_init();
    $opts = select_opts($scheme,$url,$prox_opt,$args,$method);
    curl_setopt_array($ch, $opts);
    $page = curl_exec($ch);
    $curlinfo = curl_getinfo($ch);
    $cookies = curl_getinfo($ch, CURLINFO_COOKIELIST);
    $host = "{$curlinfo['primary_ip']}:{$curlinfo['primary_port']}";
    if(curl_errno($ch)){
        $error = "Crawler cURL error: ".curl_error($ch);
        curl_close($ch);
        return [
            'status' => false,
            'error'  => $error,
            'info' => $curlinfo,
        ];
    }
    // if($last_request){
    //     printf("\r\n> ".COLOR_CYAN."Closed cURL handle!".COLOR_RESET."\r\n");
    //     curl_close($ch);
    // }
    curl_close($ch);
    return [
        'page' => $page,
        'info' => $curlinfo,
        'host' => $host,
        'cookies' => $cookies,
    ];
}

function select_opts(string $scheme, string $url, int $prox_opt, string $args = "", string $method = "") : array {
    $opts = [
        CURLOPT_URL             => preg_match('/^'.$scheme.'/', $url) ? $url : "{$scheme}://{$url}",
        CURLOPT_HEADER          => 1,
        CURLOPT_VERBOSE         => 0, // 0 normally
        CURLOPT_RETURNTRANSFER  => 1,
        CURLOPT_CONNECTTIMEOUT  => 30,
        CURLOPT_TIMEOUT         => 30,
        CURLOPT_SSL_VERIFYPEER  => $scheme === 'https' ? 1 : 0,
        CURLOPT_SSL_VERIFYHOST  => $scheme === 'https' ? 2 : 0,
        CURLOPT_FOLLOWLOCATION  => 1,
        CURLOPT_MAXREDIRS       => 5,
        CURLOPT_COOKIEJAR       => 'session/SessionCookies.txt',
        CURLOPT_COOKIEFILE      => 'session/SessionCookies.txt',
        CURLOPT_HTTPHEADER      => [
            'User-Agent: '.set_user_agent(),
            'Accept: */*',
            'Cache-Control: no-cache',
        ]
    ];
    if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')){
        $opts += [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4];
    }
    if($prox_opt === 1){
        $opts += [CURLOPT_PROXY => '127.0.0.1:9050'];
        $opts += [CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME];
    }
    if( ! empty($args)){
        if( ! empty($method) && $method === "POST"){
            $args_temp_arr = explode("&",$args);
            $args_temp = [];
            $args_arr = [];
            foreach ($args_temp_arr as $ak => $a) {
                $args_temp = explode("=", $a);
                $args_arr[$args_temp[0]] = $args_temp[1]; 
            }
            $opts += [CURLOPT_POST => 1];
            $opts += [CURLOPT_POSTFIELDS => $args_arr];
        } else {
            $opts += [CURLOPT_HTTPGET => 1];
            $opts[CURLOPT_URL] = $opts[CURLOPT_URL]."/?{$args}";
        }
    }
    return $opts;
}

function validate_base_domain(string $base_domain, string $link_domain, string $scheme) : bool {
    if(substr($link_domain, 0, 7) !== "{$scheme}://"){
        $link_abs = "{$scheme}://$link_domain";
    } else {
        $link_abs = $link_domain;
    }
    if(substr($base_domain, 0, 7) !== "{$scheme}://"){
        $base_abs = "{$scheme}://$base_domain";
    } else {
        $base_abs = $base_domain;
    }

    $parsed_link = parse_url($link_abs);
    $parsed_base = parse_url($base_abs);
    
    if(filter_var($parsed_link['host'], FILTER_VALIDATE_IP) !== false
    && filter_var($parsed_base['host'], FILTER_VALIDATE_IP) !== false)
    {
        // format: {ip_addr}:{port}
        $http_port = 80;
        $link_ip_port = ( ! empty($parsed_link['port']))
                        ? "{$scheme}://{$parsed_link['host']}:{$parsed_link['port']}"
                        : "{$scheme}://{$parsed_link['host']}:{$http_port}";
        $base_ip_port = ( ! empty($parsed_base['port']))
                        ? "{$scheme}://{$parsed_base['host']}:{$parsed_base['port']}"
                        : "{$scheme}://{$parsed_base['host']}:{$http_port}";

        return ($base_ip_port === $link_ip_port);

    } else {
        // format: {top_domain}.{extension}
        $link_domain = parse_url($link_domain);
        if(substr($base_domain, 0, 4) === "www."){
            $base_domain = substr($base_domain, 4);
        }
        if(substr($link_domain['host'], 0, 4) === "www."){
            $link_domain['host'] = substr($link_domain['host'], 4);
        }

        $b_sub_lvls = explode(".", $base_domain);
        $l_sub_lvls = explode(".", $link_domain['host']);

        $b_index = count($b_sub_lvls) -1;
        $l_index = count($l_sub_lvls) -1;

        $b_top_domain = "{$b_sub_lvls[$b_index]}.{$b_sub_lvls[$b_index -1]}";
        $l_top_domain = "{$l_sub_lvls[$l_index]}.{$l_sub_lvls[$l_index -1]}";

        return ($b_top_domain === $l_top_domain); 
    }
}

function extract_html_elements(object $doc, string $tag, string $url) : array {
    $eles = $doc->getElementsByTagName($tag);
    if(empty($eles[0])){
        return [];
    }
    $node_element = $eles[0]->nodeName;
    $elements = [
        'node' => $node_element,
        'node_url' => $url,
        'node_elements' => [],
    ];
    loop_elements($eles, $elements);
    return $elements;
}

function loop_elements($loopable, array &$elements) : void {
    foreach ($loopable as $key => $el){
        $_elements = [];
        recursive_loop_child_elements($el, $_elements);
        if( ! empty($_elements)){
            $elements['node_elements']["{$elements['node']}-{$key}"] = $_elements;
        }
    }
}

function set_user_agent() : string {
    $agentBrowser = [
        'Firefox',
        'Safari',
        'Opera',
        'Internet Explorer'
    ];
    $agentOS = [
        'Windows Vista',
        'Windows XP',
        'Windows 7',
        'Windows 10',
        'Redhat Linux',
        'Ubuntu',
        'Fedora'
    ];
    return $agentBrowser[rand(0,3)].'/'.rand(1,8).'.'.rand(0,9).'('.$agentOS[rand(0,6)].' '.rand(1,7).'.'.rand(0,9).'; en-US;)';
}

function get_public_ip(int $prox_opt) : string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://ipinfo.io/ip', // alt-'https://httpbin.org/ip', 'ipv4.icanhazip.com'
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HTTPHEADER => [
            'User-Agent: '.set_user_agent(),
        ],
    ]);
    if($prox_opt === 1) {
        curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:9050');
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
    }
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function read_json(string $file) : array {
    if( ! file_exists($file)){
        return [];
    }
    $fp = fopen($file, 'r');
    $contents = '';
    while ( ! feof($fp)){
        $contents .= fread($fp, 4096);
    }
    $json_decoded = json_decode($contents);
    return ! empty($json_decoded) ? $json_decoded : [];
}

function fetch_html_elements_by_type($elements, string $filter_element) : array {
    $data = [];
    if(empty($elements)){
        return $data;
    }
    recursive_filter_elements($elements, $data, $filter_element);
    return ! empty($data) ? $data : [];
}

function recursive_loop_child_elements($element, &$el_elements) : void {
    if(is_iterable($element)){
        // TODO:: fix counter
        $cnt = 0;
        foreach ($element as $key => $el) {
            if( ! empty($el->attributes)){
                foreach ($el->attributes as $attr){
                    if( ! empty($attr->nodeValue)){
                        $val = trim(str_replace("\n", " ", $attr->nodeValue));
                        if(in_array($el->nodeName, TAGS)){
                            $el_elements[$el->nodeName][$attr->nodeName] = $val;
                        } else {
                            $el_elements["{$el->nodeName}-{$cnt}"][$attr->nodeName] = $val;
                        }
                    }
                }
                $cnt++;
            }
            if($el->hasChildNodes()){
                recursive_loop_child_elements($el->childNodes, $el_elements);
            }
        }
    } else {
        if( ! empty($element->attributes)){
            $cnt = 0;
            foreach ($element->attributes as $attr){
                if( ! empty($attr->nodeValue)){
                    $val = trim(str_replace("\n", " ", $attr->nodeValue));
                    if(in_array($element->nodeName, TAGS)){
                        $el_elements[$element->nodeName][$attr->nodeName] = $val;
                    } else {
                        $el_elements["{$element->nodeName}-{$cnt}"][$attr->nodeName] = $val;
                        $cnt++;
                    }
                }
            }
        }
        if($element->hasChildNodes()){
            recursive_loop_child_elements($element->childNodes, $el_elements);
        }
    }
}

// TODO:: fix recursion on whole result list
function recursive_filter_elements($elements, &$data, string $filter_element) : void {
    foreach ($elements as $k => $el_vals) {
        if(is_iterable($el_vals) && ! empty($el_vals)){
            recursive_filter_elements($el_vals, $data, $filter_element);
        }
        else {
            $node_el = $el_vals->node_elements ?? null;
            $node = $el_vals->node ?? null;
            if($node_el !== null && $node !== null && $node === $filter_element){
                foreach ($node_el as $node_el_key => $node_el_val) {
                    $data[$node_el_key] = $node_el_val;
                }
            }
        }
    }
}

function recursive_read_elements($elements) : void {
    foreach ($elements as $key => $el) {
        if(is_object($el) || is_array($el)) {
            recursive_read_elements($el);
        } else {
            if($key === 'node' || $key === 'node_url'){
                $color = COLOR_LIGHT_BLUE;
                $k = str_replace('_', ' ', strtoupper($key));
            } else {
                $color = COLOR_WHITE;
                $k = str_replace('_', ' ', $key);
            }
            printf("{$color}%s: %s".COLOR_RESET, $k, $el);
            printf("\r\n");
        }
    }
}

?>
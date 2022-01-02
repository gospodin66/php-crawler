#!/usr/bin/php
<?php
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
// prox_opt is optional
if(count($opts) >= 3){
    $prox_opt = array_key_exists("torprox", $opts) ? intval(trim($opts['torprox'])) : intval(trim($opts['x']));
} else {
    $prox_opt = 0;
}
if(count($opts) >= 4){
    $args = array_key_exists("args", $opts) ? trim($opts['args']) : trim($opts['a']);
} else {
    $args = "";
}
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

if($prox_opt !== 1){ 
	$prox_opt = 0;
}
$public_ip = get_public_ip($prox_opt);
$parsed_url = parse_url("{$scheme}://{$domain}");

if(!defined('DATA_DIR')){
	define("DATA_DIR", "scrapped/");
}
if(!file_exists(DATA_DIR)){
	mkdir(DATA_DIR);
}

$log = "";
$curl_start = microtime(true);
$results = exec_curl_request($scheme, $domain, $prox_opt, $args, $method);
$curl_time = microtime(true) - $curl_start;
if(@!$results['status'] && ! empty($results['error'])){
	die("> Error: {$results['error']}\r\n> Exit\r\n");
}
if($results['info']['http_code'] === 403){
	while(readline('> HTTP response status 403, re-send request? (y/n): ') === 'y'){
		$results = exec_curl_request($scheme, $domain, $prox_opt, $args, $method);
		if(@!$results['status'] && ! empty($results['error'])){
			die("> Error: {$results['error']}\r\n> Exit\r\n");
		}
		if($results['info']['http_code'] !== 403){
			break;
		}
	}
}
$res_size = format_file_size($results['info']['size_download']);
$page_size = format_file_size(strlen($results['page']));
$exectime = get_exec_time($curl_time);

$_time = date("Y-m-d H:i:s");
if($prox_opt === 1) {
	$log .= "> Using tor SOCKS5 proxy\r\n";
	printf("\r\n> Using Tor SOCKS5 proxy\r\n");
}

$down_numeric = substr($results['speed'], 0, strpos($results['speed'], '/'));
$up_numeric = substr($results['speed'], strpos($results['speed'], '/') +1);

$calc_down_speed = format_file_size($down_numeric);
$calc_up_speed = format_file_size($up_numeric);

$log .= "> Public IP: [".trim($public_ip)."]\r\n".
	    "> [$_time] > URL: ".$domain."\r\n".
	    "> [$_time] > Host: {$results['host']} | down/up speed: {$calc_down_speed}/{$calc_up_speed}\r\n".
	    sprintf("\t\t\t\t\t\t> Page size: [%s]\r\n", $page_size).
	    sprintf("\t\t\t\t\t\t> Len: [%s]\r\n", $res_size).
		"> Res code:  [{$results['http_code']}]";

printf("> Public IP: [".COLOR_CYAN."%s".COLOR_RESET."]\r\n".
	   "> Page size: [".COLOR_CYAN."%s".COLOR_RESET."]\r\n".
	   "> Length: %3s[".COLOR_CYAN."%s".COLOR_RESET."]\r\n".
	   "> Res code:%2s[".COLOR_CYAN."%d".COLOR_RESET."]\r\n".
	   "> Time: %5s[".COLOR_CYAN."%s".COLOR_RESET."]\r\n",
	   trim($public_ip), $page_size, ' ', $res_size, ' ', $results['http_code'], ' ', $exectime
	  );

file_put_contents(DATA_DIR.str_replace("/", "-", $parsed_url['host']).".txt", $log, FILE_APPEND);
$log = "";

$doc = new DOMDocument();
@$doc->loadHTML($results['page']);

$title = $doc->getElementsByTagName("title");
@$title = $title->item(0)->nodeValue;

$hrefs = extract_html_elements($doc,TAGS['a'],"{$scheme}://{$domain}");
$lists = extract_html_elements($doc,TAGS['li'],"{$scheme}://{$domain}");
$imgs = extract_html_elements($doc,TAGS['img'],"{$scheme}://{$domain}");
$metas = extract_html_elements($doc,TAGS['meta'],"{$scheme}://{$domain}");
$links = extract_html_elements($doc,TAGS['link'],"{$scheme}://{$domain}");
$forms = extract_html_elements($doc,TAGS['form'],"{$scheme}://{$domain}");
$tables = extract_html_elements($doc,TAGS['table'],"{$scheme}://{$domain}");
$scripts = extract_html_elements($doc,TAGS['script'],"{$scheme}://{$domain}");

$main_request_results = [$links,$metas,$hrefs,$imgs,$scripts,$forms,$lists,$tables];
$json = json_encode($main_request_results);

print "\r\n> Successfuly scrapped the main webpage\r\n";
while(1){
	$jpath = DATA_DIR.str_replace("/", "-", $parsed_url['host']).".json";
	print "\r\n> Options:".
			"\r\n> p -print main webpage results".
			"\r\n> s -save main webpage results".
			"\r\n> j -fetch elements by type".
			"\r\n> f -follow webpage links".
			"\r\n> e -exit\r\n";
	switch (readline("> ")) {
	 	case 'p':
			recursive_read_elements($main_request_results);
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
			file_put_contents($jpath, json_encode($main_request_results));
			printf("\r\n> Successfuly saved file > [%s]\r\n", $jpath);
			break;
            
		case 'f':
			follow_links($opts,$doc,$parsed_url['host'],$scheme,$prox_opt);
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

function follow_links(array $opts, $doc, string $domain, string $scheme, int $prox_opt){
	$href_links = $doc->getElementsByTagName('a');
	$url_regex = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,5}(\/\S*)?/";
	$crawled = $links = $metas = $hrefs = $imgs = $scripts = $forms = $lists = $tables = [];
    $cnt = 0;
	$app_start = microtime(true);
	foreach ($href_links as $key => $a)
	{	
		foreach ($a->attributes as $attrkey => $link)
		{	
            if($attrkey !== 'href'){
                continue;
            }
            
            $log = "";
            $url = trim($link->nodeValue);
			$time = date("Y-m-d H:i:s");

			if(empty($url) || $url[0] === '#' || $url[0] === '_'){
				break;	
			}
			// do not download content!
			else if(preg_match("/(\.(x{0,1})(apk$))|(\.ipa$)|\.mp{1}[(3{0,1})|(4{0,1})]$|\.jp(e{0,1})g$|(\.png$)/", $url, $matches)) {
				$remote_filesize = exec_remote_filesize_request($url);
				$remote_filesize_final = format_file_size($remote_filesize);
				printf("> ".COLOR_YELLOW."Warning ".COLOR_RESET.
							"| [%d] ".
							"| [%s] ".
							"| [".COLOR_YELLOW."Content URL ".COLOR_RESET.
							"> [".COLOR_YELLOW."%s".COLOR_RESET."] ".
							"> [".COLOR_YELLOW."%s".COLOR_RESET."]]\r\n",
							count($crawled), $url, $remote_filesize_final, $matches[0]
					  );
				$log .= "> [{$time}] > Warning | [Content URL > [{$remote_filesize_final}] > [{$url}] > ] \r\n";
				file_put_contents(DATA_DIR."content_".str_replace("/", "-", $domain).".txt", $url."\r\n", FILE_APPEND);
				file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
				break;
			}
            // do not exec .js | javascript url | .php | .py
			else if(preg_match("/(\.js$)|(\.php$)|(javascript:)|(\.py)/", $url, $matches)) {
				printf("> ".COLOR_YELLOW."Warning ".COLOR_RESET.
							"| [%d] ".
							"| [%s] ".
							"| [".COLOR_YELLOW."Script format: %s".COLOR_RESET."]\r\n",
							count($crawled), $url, $matches[0]
					  );
				$log .= "> [{$time}] > Warning | [Script format: {$matches[0]}] > [{$url}]\r\n";
				break;
			}
            // skip mailto: | tel:
            else if(preg_match("/(mailto:|tel:)/", $url)) {
				printf("> ".COLOR_YELLOW."Warning ".COLOR_RESET.
							"| [%d] ".
							"| [%s] ".
							"| [".COLOR_YELLOW."Email/tel format".COLOR_RESET."]\r\n",
							count($crawled), $url
					  );
				$log .= "> [{$time}] > Warning | [Email/tel format] > [{$url}]\r\n";
				break;
            }
            // broken paths => can add more
            else if(preg_match("/(_blank)/", $url)) {
                printf(">".COLOR_YELLOW."Warning ".COLOR_RESET.
							"| [%d] ".
							"| [%s] ".
							"| [".COLOR_YELLOW."Broken path".COLOR_RESET."]\r\n",
							count($crawled), $url
					  );
				$log .= "> [{$time}] > Warning | [Broken path] > [{$url}]\r\n";
				break;
            }
			$log .= "> [{$time}] > URL: [{$url}]\r\n";
            // invalid url format
			if(preg_match($url_regex, $url) === 0){ 
				// print "> rel-path[".COLOR_CYAN."{$url}".COLOR_RESET."] ";
				if($url[0] === "/" && substr($url, 0, 2) !== "//"){
					$url = "{$scheme}://{$domain}{$url}";
				}
				// TODO: test case
				else if(substr($url, 0, 2) === "./"){
					$url = "{$scheme}://{$domain}/".basename($url);
					var_dump("CASE TEST ./ ->", $url);
				}
				// TODO: test case
				else if(substr($url, 0, 3) === "../"){
					$url = "{$scheme}://{$domain}/".realpath($url).basename($url);
					var_dump("CASE TEST ../ ->", $url);
				}
				else {
					$url = "{$scheme}://{$domain}/{$url}";
				}
				// save rel links
				file_put_contents(DATA_DIR."rel_".str_replace("/", "-", $domain).".txt", $url."\r\n", FILE_APPEND);
				$log .= "> [{$time}] > URL: [{$url}]\r\n";
			} else {
				// save abs links
				file_put_contents(DATA_DIR."abs_".str_replace("/", "-", $domain).".txt", $url."\r\n", FILE_APPEND);
			}
			if( ! check_base_domain($domain,$url,$scheme)){
				$log .= "> [{$time}] > Invalid base domain [{$url}]\r\n";
				printf("> ".COLOR_RED."Error".COLOR_RESET."%3s".
							"| [%d] ".
							"| [%s] ".
							"| [".COLOR_RED."Invalid base domain".COLOR_RESET."]\r\n",
							' ', count($crawled), $url
					  );
				continue;
			}
			if(in_array($url, $crawled)){
				break;  
			}
			$curl_start = microtime(true);
			$results = exec_curl_request($scheme, $url, $prox_opt);
			$curl_time = microtime(true) - $curl_start;
			$exectime = get_exec_time($curl_time);
			if(@!$results['status'] && ! empty($results['error'])){
				$log .= "> [{$time}] > {$results['error']}\r\n";
				printf("> ".COLOR_RED."Error".COLOR_RESET."%3s".
							"| [%d] ".
							"| [%s] ".
							"| [".COLOR_RED."%s".COLOR_RESET."] ".
							"| [".COLOR_RED."%s".COLOR_RESET."] ".
							"| [".COLOR_RED."%d".COLOR_RESET."]\r\n",
							' ', count($crawled), $url, $results['error'], $exectime, $results['http_code']
					  );
				continue;
			}
			$crawled [] = $url;

			$down_numeric = intval(substr($results['speed'], 0, strpos($results['speed'], '/')));
			$up_numeric = intval(substr($results['speed'], strpos($results['speed'], '/') +1));

			$calc_down_speed = format_file_size($down_numeric);
			$calc_up_speed = format_file_size($up_numeric);
			$res_size = format_file_size($results['info']['size_download']);
			$page_size = format_file_size(strlen($results['page']));

			$log .= "> [{$time}] > Host: {$results['host']} | down/up speed: {$calc_down_speed}/{$calc_up_speed}\r\n".
					sprintf("\t\t\t\t\t\t> Page size: [%s]\r\n", $page_size).
					sprintf("\t\t\t\t\t\t> Len: [%s]\r\n", $res_size);
			file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
			$log = "";
				
			$doc = new DOMDocument();
			@$doc->loadHTML($results['page']);

			if($results['http_code'] >= 200 && $results['http_code'] < 300){
				$http_code_color = COLOR_CYAN;
				$response = "Success";
				$indent = '';
			} else if($results['http_code'] >= 300 && $results['http_code'] < 400){
				$http_code_color = COLOR_LIGHT_BLUE;
				$response = "Redir";
				$indent = ' ';
			} else {
				$http_code_color = COLOR_RED;
				$response = "Error";
				$indent = ' ';
			}
			printf("> {$http_code_color}{$response}".COLOR_RESET.($results['http_code'] === 200 ? "%s " : "%3s").
						"| [%d] ".
						"| [%s] ".
						"| [{$http_code_color}%s".COLOR_RESET."] ".
						"| [{$http_code_color}%s".COLOR_RESET."] ".
						"| [{$http_code_color}%d".COLOR_RESET."]\r\n",
						$indent, (count($crawled)-1), $url, $res_size, $exectime, $results['http_code']
				  );
			file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
		}
		$hrefs [TAGS['a'].$key] = extract_html_elements($doc,TAGS['a'],$url);		
		$lists [TAGS['li'].$key] = extract_html_elements($doc,TAGS['li'],$url);
		$imgs [TAGS['img'].$key] = extract_html_elements($doc,TAGS['img'],$url);
		$links [TAGS['link'].$key] = extract_html_elements($doc,TAGS['link'],$url);
		$metas [TAGS['meta'].$key] = extract_html_elements($doc,TAGS['meta'],$url);
		$forms [TAGS['form'].$key] = extract_html_elements($doc,TAGS['form'],$url);
		$tables [TAGS['table'].$key] = extract_html_elements($doc,TAGS['table'],$url);
		$scripts [TAGS['script'].$key] = extract_html_elements($doc,TAGS['script'],$url);
    }
	$json = json_encode([$links,$metas,$imgs,$scripts,$hrefs,$forms,$lists,$tables]);
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
				format_file_size(strlen($json))
			  );
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
				$eles = read_json(__DIR__."/".DATA_DIR.$domain.".json");
                printf("> JSON file read successfuly\r\n");
                $eles_by_type = fetch_html_elements_by_type($eles, $filter_el);
                if(file_put_contents(__DIR__."/".DATA_DIR.$domain.".filtered.json", json_encode($eles_by_type)) !== false){
                    print("> Fitered elements saved to file successfuly\r\n");
                }
				recursive_read_elements($eles_by_type);
                break;

			case 's':
				file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".json", $json);
				print("> ".COLOR_GREEN."Saved".COLOR_RESET." > File path: ".__DIR__."/".DATA_DIR.$domain.".json".COLOR_RESET."\r\n");
				printf("\r\n");
				break;

			case 'p':
				print_r(json_decode($json));
				printf("\r\n");	
				break;

		 	default:
			 	printf(COLOR_RED."> Invalid argument".COLOR_RESET."\r\n");
			 	break;
		} 
	}
	return;
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

function format_file_size(int $size) : string {
	return ($res_size = strlen($size) > 6)
		 			  ? sprintf("%.2fMb", ($size / 1000000))
		 			  : ((strlen($size) > 3 && strlen($size) < 7)
						 	? sprintf("%.2fkb", ($size / 1000))
						 	: sprintf("%.2fb", $size)
					    );
}

function get_exec_time(float $time) : string {
	// $micro = html_entity_decode('&#956;'); // greek "micro" character
	$color = $time < 5 ? COLOR_CYAN :($time > 5 && $time < 10 ? COLOR_YELLOW : COLOR_RED);
	return sprintf("\033{$color}%.2fs".COLOR_RESET, $time);
}

function exec_curl_request(string $scheme, string $url, int $prox_opt, string $args = "", string $method = "") : array {
	$ch = curl_init();
	$opts = select_opts($scheme,$url,$prox_opt,$args,$method);
	curl_setopt_array($ch, $opts);
	$page = curl_exec($ch);
	$curlinfo = curl_getinfo($ch);
	$cookies = curl_getinfo($ch, CURLINFO_COOKIELIST);
	$host = "{$curlinfo['primary_ip']}:{$curlinfo['primary_port']}";
	$speed = "{$curlinfo['speed_download']}/{$curlinfo['speed_upload']}";
	$http_code = $curlinfo['http_code'];
	if(curl_errno($ch)){
		$error = "Crawler error: ".curl_error($ch);
		curl_close($ch);
		return [
			'status' => false,
			'error'  => $error,
		];
	}
	curl_close($ch);
	return [
		'page' => $page,
		'info' => $curlinfo,
		'host' => $host,
		'speed' => $speed,
		'cookies' => $cookies,
		'http_code' => $http_code,
	];
}

function select_opts(string $scheme, string $url, int $prox_opt, string $args = "", string $method = "") : array {
	$opts = [
		CURLOPT_URL 			=> preg_match('/^'.$scheme.'/', $url) ? $url : "{$scheme}://{$url}",
	    CURLOPT_HEADER 			=> 1,
	    CURLOPT_VERBOSE 		=> 0, // 0 normally
	    CURLOPT_RETURNTRANSFER 	=> 1,
	    CURLOPT_CONNECTTIMEOUT 	=> 30,
	    CURLOPT_TIMEOUT        	=> 30,
	    CURLOPT_SSL_VERIFYPEER  => $scheme === 'https' ? 1 : 0,
	    CURLOPT_SSL_VERIFYHOST  => $scheme === 'https' ? 2 : 0,
	    CURLOPT_FOLLOWLOCATION	=> 1,
	    CURLOPT_MAXREDIRS 		=> 5,
		CURLOPT_COOKIEJAR		=> 'session/SessionCookies.txt',
		CURLOPT_COOKIEFILE		=> 'session/SessionCookies.txt',
	    CURLOPT_HTTPHEADER		=> [
		    'User-Agent: '.set_user_agent(),
		    'Accept: */*',
		    'Cache-Control: no-cache',
	    ]
	];
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
			$opts[CURLOPT_URL] = $opts[CURLOPT_URL]."/?{$args}";
		}
	}
	return $opts;
}

function check_base_domain(string $base_domain, string $link_domain, string $scheme) : bool {
	$link_domain = parse_url($link_domain);
	if(substr($base_domain, 0, 4) === "www."){
		$base_domain = substr($base_domain, 4);
	}
	if(substr($link_domain['host'], 0, 4) === "www."){
		$link_domain['host'] = substr($link_domain['host'], 4);
	}
	$base_subdomain_by_levels = explode(".", $base_domain);
	$link_subdomain_by_levels = explode(".", $link_domain['host']);

	// format: {top_domain}.{extension}
	$base_index = count($base_subdomain_by_levels) -1;
	$base_top_domain = "{$base_subdomain_by_levels[$base_index]}.{$base_subdomain_by_levels[$base_index -1]}";

	$link_index = count($link_subdomain_by_levels) -1;
	$link_top_domain = "{$link_subdomain_by_levels[$link_index]}.{$link_subdomain_by_levels[$link_index -1]}";

	return ($base_top_domain === $link_top_domain); 
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
	if($tag === 'a'){
		foreach ($eles as $key => $a) {
            $_element_elements = [];
			recursive_loop_child_elements($a, $_element_elements);
            if(! empty($_element_elements)){
                $elements['node_elements']["{$node_element}-{$key}"] = $_element_elements;
            }
		}
	} else if($tag === 'form'){
        foreach ($eles as $key => $form){
            $_element_elements = [];
			recursive_loop_child_elements($form, $_element_elements);
            if(! empty($_element_elements)){
                $elements['node_elements']["{$node_element}-{$key}"] = $_element_elements;
            }
		}
	} else if($tag === 'li'){
        foreach ($eles as $key => $li){
            $_element_elements = [];
			recursive_loop_child_elements($li, $_element_elements);
            if(! empty($_element_elements)){
                $elements['node_elements']["{$node_element}-{$key}"] = $_element_elements;
            }
		}
	} else if($tag === 'table'){
        foreach ($eles as $key => $table){
            $_element_elements = [];
			recursive_loop_child_elements($table, $_element_elements);
            if(! empty($_element_elements)){
                $elements['node_elements']["{$node_element}-{$key}"] = $_element_elements;
            }
		}
    } else {
		foreach ($eles as $key => $ele){
            $_element_elements = [];
			recursive_loop_child_elements($ele, $_element_elements);
            if(! empty($_element_elements)){
                $elements['node_elements']["{$node_element}-{$key}"] = $_element_elements;
            }
		}
	}
	return $elements;
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
		$cnt = 0;
		foreach ($element as $key => $el) {
			if( ! empty($el->attributes)){
				foreach ($el->attributes as $attr){
					if( ! empty($attr->nodeValue)){
						if(in_array($el->nodeName, TAGS)){
							$el_elements[
								"{$el->nodeName}"
							] [$attr->nodeName] = trim(str_replace("\n", " ", $attr->nodeValue));
						} else {
							$el_elements[
								"{$el->nodeName}-{$cnt}"
							] [$attr->nodeName] = trim(str_replace("\n", " ", $attr->nodeValue));
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
					if(in_array($element->nodeName, TAGS)){
						$el_elements[
							"{$element->nodeName}"
						] [$attr->nodeName] = trim(str_replace("\n", " ", $attr->nodeValue));
					} else {
						$el_elements[
							"{$element->nodeName}-{$cnt}"
						] [$attr->nodeName] = trim(str_replace("\n", " ", $attr->nodeValue));
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
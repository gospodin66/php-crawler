#!/usr/bin/php
<?php

if (PHP_SAPI !== 'cli') { die("Script needs to be run as cli.\n"); }

$short = "p:s:x:";
$long  = ["path:", "scheme:", "torprox:"];

$opts = getopt($short,$long);
if(count($opts) < 2){ 
    die("Assign [-p]--path <example.com> [-s]--scheme <http/s> (optional) [-x]--torprox <1/0>\n");
}
$domain = array_key_exists("path", $opts)   ? trim($opts['path'])   : trim($opts['p']);
$scheme = array_key_exists("scheme", $opts) ? trim($opts['scheme']) : trim($opts['s']);
if(preg_match('/^((http|https)\:\/\/)/', $domain)){
    $domain = preg_replace('/^((http|https)\:\/\/)/', '', $domain);
}
// prox_opt is optional
if(count($opts) === 3){
    $prox_opt = array_key_exists("torprox", $opts)
			  ? intval(trim($opts['torprox']))
			  : intval(trim($opts['x']));
} else {
    $prox_opt = 0;
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

if($prox_opt !== 1){ $prox_opt = 0; }

$publicIp   = publicIp($prox_opt);
$parsed_url = parse_url($scheme."://".$domain);

if(!defined('DATA_DIR')){
	define("DATA_DIR", "crawled_websites/");
}
if(!file_exists(DATA_DIR)){
	mkdir(DATA_DIR);
}

$log  = "";
$results = call__curl($scheme, $domain, $prox_opt);
if(@!$results['status'] && !empty($results['error'])){
	die("> Error: ".$results['error'].",\r\n> Exit..\r\n");
	
}
if($results['info']['http_code'] === 403){
	while(readline('HTTP status 403, repeat? (y/n): ') === 'y'){
		$results = call__curl($scheme, $domain, $prox_opt);
		if(@!$results['status'] && !empty($results['error'])){
			die("> Error: ".$results['error'].",\r\n> Exit..\r\n");
		}
		if($results['info']['http_code'] !== 403){
			break;
		}
	}
}
$_time = date("Y-m-d H:i:s");
$log .= "> Public IP: [".trim($publicIp)."]\r\n\r\n";
$log .= "[".$_time."] > URL: ".$domain."\r\n";
$log .= "[".$_time."] > Host: ".$results['host']." | down/up speed[bytes]: ".$results['speed']."\r\n";
$log .= "> Page size: [".strlen($results['page'])."] bytes.\r\n";
$log .= "> Content length: ".$results['info']['size_download']." bytes\r\n";

print "\33[96m> Public IP: [".trim($publicIp)."]\33[0m\n";
print "> Content length: ".$results['info']['size_download']." bytes\n";

file_put_contents(DATA_DIR.str_replace("/", "-", $parsed_url['host']).".txt", $log, FILE_APPEND);
$log = "";

$doc = new DOMDocument();
@$doc->loadHTML($results['page']);

$title = $doc->getElementsByTagName("title");
@$title = $title->item(0)->nodeValue;


$hrefs = _get_elements($doc,TAGS['a'],"{$scheme}://{$domain}");
$lists = _get_elements($doc,TAGS['li'],"{$scheme}://{$domain}");
$imgs = _get_elements($doc,TAGS['img'],"{$scheme}://{$domain}");
$metas = _get_elements($doc,TAGS['meta'],"{$scheme}://{$domain}");
$links = _get_elements($doc,TAGS['link'],"{$scheme}://{$domain}");
$forms = _get_elements($doc,TAGS['form'],"{$scheme}://{$domain}");
$tables = _get_elements($doc,TAGS['table'],"{$scheme}://{$domain}");
$scripts = _get_elements($doc,TAGS['script'],"{$scheme}://{$domain}");

$i_page = [$links,$metas,$hrefs,$imgs,$scripts,$forms,$lists,$tables];
$json = json_encode($i_page);

print "Successfuly scrapped the main webpage!\r\n";

while(1){
	print "\33[93mOptions:\ne -exit\nj -read json file\nl -fetch elements by type\np -print main webpage results\ns -save main webpage results\nf -follow webpage links\33[0m\n";
	switch (readline("> ")) {
	 	case 'p':
			print_r($i_page);
			echo "\n";
			break;

        case 'j':
            $eles = read_json(DATA_DIR.str_replace("/", "-", $parsed_url['host']).".json");
            print "JSON file read successfuly!\n";
            break;

        case 'l':
            switch (readline("enter element to filter: > ")) {
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
			$eles_by_type = [];
            recursive_filter_elements($eles, $eles_by_type, $filter_el);
			recursive_read_elements($eles_by_type);
            break;

		case 's':
			file_put_contents(DATA_DIR.str_replace("/", "-", $parsed_url['host']).".json", json_encode($i_page));
			echo "\nSuccessfuly saved file!\n";
			break;
            
		case 'e':
			break 2;

        case 'f':
			follow_links($opts,$doc,$parsed_url['host'],$scheme,$prox_opt);
			break;

	 	default:
			print "\033[31m> Invalid argument.\33[0m\n";
	 		break;
	 } 
}	
// print "\x07"; // beep 
print "\033[32m\nFinished.\n\033[0m";
exit(0);

/******************************************************************************/

function follow_links(array $opts, $doc, string $domain, string $scheme, bool $prox_opt){
	$href_links = $doc->getElementsByTagName('a');
	$url_regex = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,5}(\/\S*)?/";
	$crawled = $links = $metas = $hrefs = $imgs = $scripts = $forms = $lists = $tables = [];
    $cnt = 0;
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
			else if(preg_match("/(\.(x{0,1})(apk$))|(\.ipa$)|\.mp{1}[(3{0,1})|(4{0,1})]$|\.jp(e{0,1})g$|(\.png$)/", $url)) {
				print "\33[94m[".$time."] > Content URL >>> ".$url." >>> skipping..\33[0m\n";
				$log .= "[".$time."] > Content URL [".strlen($url)." bytes] >>> ".$url." >>> skipping..\r\n";
				file_put_contents(DATA_DIR."content_".str_replace("/", "-", $domain).".txt", $url."\n", FILE_APPEND);
				file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
				break;
			}
            // do not exec .js | javascript:void(0) | .php!
			else if(preg_match("/(\.js$)|(\.php$)|(javascript:void\(0\))/", $url)) {
				print "\33[95m[".$time."] > Script format >>> skipping..\n\33[0m";
				$log .= "[".$time."] > Script format >>> ".$url." >>> skipping..\r\n";
				break;
			}
            // skip mailto: | tel:
            else if(preg_match("/(mailto:|tel:)/", $url)) {
				print "\33[95m[".$time."] > Mail|Tel format >>> skipping..\n\33[0m";
				$log .= "[".$time."] > Mail|Tel format >>> ".$url." >>> skipping..\r\n";
				break;
            }
            // broken paths => can add more
            else if(preg_match("/(_blank)/", $url)) {
                print "\33[95m[".$time."] > Broken path >>> skipping..\n\33[0m";
				$log .= "[".$time."] > Broken path >>> ".$url." >>> skipping..\r\n";
				break;
            }

			if(in_array($url, $crawled)){
			   break;  
			}

			$crawled [] = $url;
			$log .= "\r\n[".$time."] > URL: ".$url."\r\n";

            // invalid url format
			if(preg_match($url_regex, $url) === 0){ 
				print "\033[95m> ".$url." >>> Invalid link format..\n> Adapting..\033[0m \n";

				if($url[0] == "/" && substr($url, 0, 2) != "//")
				{
					$url = $scheme."://".$domain.$url;
				}
				// TODO: test case
				else if(substr($url, 0, 2) == "./")
				{
					$url = $scheme."://".$domain."/".basename($url);
					var_dump("CASE ./ ->", $url);
				}
				else if(substr($url, 0, 3) == "../")
				{
					$url = $scheme."://".$domain."/".realpath($url).basename($url);
					var_dump("CASE ../ ->",$url);
				}
				else if (substr($url, 0, 11) == "javascript:")
				{
					$log .= "[".$time."] > javascript:  ".$url."\r\nScript format, skipping..\r\n";
					print "\033[95m> Script format, skipping..\033[0m\n";
					break;
				}
				else {
					$url = $scheme."://".$domain."/".$url;
				}

				// save rel links
				file_put_contents(DATA_DIR."rel_".str_replace("/", "-", $domain).".txt", $url."\n", FILE_APPEND);

				echo "\33[32m> New url:  ".$url."\33[0m\n";
				$log .= "[".$time."] > New url:  ".$url."\r\n";

			} else {
				// save abs links
				file_put_contents(DATA_DIR."abs_".str_replace("/", "-", $domain).".txt", $url."\n", FILE_APPEND);
			}

			if(! check_base_domain($domain,$url,$scheme)){
				$log .= "> Invalid base domain [".$url."], skipping..\r\n";
				print "\033[95m> Invalid base domain [".$url."], skipping..\33[0m\n";
				continue;
			}
			$results = call__curl($scheme, $url, $prox_opt);
			if(@!$results['status'] && !empty($results['error'])){
				$log .= "> Error: ".$results['error'].", skipping..\r\n";
				print "> Error: ".$results['error'].", skipping..\n";
				continue;
			}
			$log .= "[".$time."] > Host: ".$results['host']." | down/up speed[bytes]: ".$results['speed']."\r\n";
			$log .= "> Page size: [".strlen($results['page'])."] bytes.\r\n";
			$log .= "> Content length: ".$results['info']['size_download']." bytes\r\n";
			file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);
			$log = "";
				
			$doc = new DOMDocument();
			@$doc->loadHTML($results['page']);

			print "> Content length: ".$results['info']['size_download']." bytes\n";
			print "\033[32m> Finished.\n\033[96m> Crawled webpages: ".(count($crawled)-1)."\33[0m\n";
			$log = "[".$time."] > Crawled webpages: ".(count($crawled)-1);
			file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".txt", $log, FILE_APPEND);

            
            echo "Scrapped ".(count($crawled) -1).". request: [{$url}]\r\n";
		}

		$hrefs [TAGS['a'].$key] = _get_elements($doc,TAGS['a'],$url);		
		$lists [TAGS['li'].$key] = _get_elements($doc,TAGS['li'],$url);
		$imgs [TAGS['img'].$key] = _get_elements($doc,TAGS['img'],$url);
		$links [TAGS['link'].$key] = _get_elements($doc,TAGS['link'],$url);
		$metas [TAGS['meta'].$key] = _get_elements($doc,TAGS['meta'],$url);
		$forms [TAGS['form'].$key] = _get_elements($doc,TAGS['form'],$url);
		$tables [TAGS['table'].$key] = _get_elements($doc,TAGS['table'],$url);
		$scripts [TAGS['script'].$key] = _get_elements($doc,TAGS['script'],$url);

    }

	$json = json_encode([$links,$metas,$imgs,$scripts,$hrefs,$forms,$lists,$tables]);
	print "\33[96mList of crawled pages:\n";
	print_r($crawled);
	print "\33[0m";

	while(1)
	{
		print "\33[93mOptions: \np -print all results\nj -decode json file\nl -fetch elements by type\nr -return\ns -save [JSON size: ".(strlen($json) / 1000000)."Mb]\33[0m\n";
		switch (readline("> "))
		{
			case 'r':
				break 2;

            case 'j':
                $eles = read_json(__DIR__."/".DATA_DIR.$domain.".json");
                print "JSON file read successfuly!\n";
                break;

            case 'l':
                switch (readline("enter element to filter: > ")) {
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
                $eles_by_type = fetch_html_elements_by_type($eles, $filter_el);
                if(file_put_contents(__DIR__."/".DATA_DIR.$domain.".filtered.json", json_encode($eles_by_type)) !== false){
                    echo "Fitered elements saved to file successfuly!\n";
                }
				recursive_read_elements($eles_by_type);
                break;

			case 's':
				file_put_contents(DATA_DIR.str_replace("/", "-", $domain).".json", $json);
				print "\33[32mSaved.\33[0m\n";
				print "\33[95mResult file >>> ".__DIR__."/".DATA_DIR.$domain.".json\33[0m\n";
				break;

			case 'p':
				print_r(json_decode($json));
				echo "\n";	
				break;

		 	default:
			 	print "\033[31m> Invalid argument.\33[0m\n";
			 	break;
		} 
	}
	return;
}

function call__curl(string $scheme, string $url, bool $prox_opt) : array {
	$ch   = curl_init();
	$opts = select_opts($scheme,$url,$prox_opt);
	curl_setopt_array($ch, $opts);
	$page 	  = curl_exec($ch);
	$curlinfo = curl_getinfo($ch);
	$cookies  = curl_getinfo($ch, CURLINFO_COOKIELIST);
	$host     = $curlinfo['primary_ip'].":".$curlinfo['primary_port'];
	$speed    = $curlinfo['speed_download']."/".$curlinfo['speed_upload'];
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
	];
}

function select_opts(string $scheme, string $url, bool $prox_opt) : array {
	return [
		CURLOPT_URL 			=> preg_match('/^'.$scheme.'./', $url) ? $url : $scheme."://".$url,
	    CURLOPT_HEADER 			=> 1,
	    CURLOPT_VERBOSE 		=> 0, // 0 normally
	    CURLOPT_RETURNTRANSFER 	=> 1,
	    CURLOPT_CONNECTTIMEOUT 	=> 30,	
	    CURLOPT_TIMEOUT        	=> 30,	
	    CURLOPT_SSL_VERIFYPEER  => $scheme == 'https' ? 1 : 0,
	    CURLOPT_SSL_VERIFYHOST  => $scheme == 'https' ? 2 : 0,
	    CURLOPT_FOLLOWLOCATION	=> 1,
	    CURLOPT_MAXREDIRS 		=> 5,
		CURLOPT_COOKIEJAR		=> 'SessionCookies.txt',
		CURLOPT_COOKIEFILE		=> 'SessionCookies.txt',
		CURLOPT_PROXY 			=> $prox_opt === 1 ? '127.0.0.1:9050' : false, 
	    CURLOPT_PROXYTYPE		=> CURLPROXY_SOCKS5_HOSTNAME,
	    CURLOPT_HTTPHEADER		=> [
		    'User-Agent: '.setUserAgent(),
		    'Accept: */*',
		    'Cache-Control: no-cache',
	    ]
	];
}

function check_base_domain(string $base_domain, string $link_domain, string $scheme){
	$link_domain = parse_url($link_domain);
	if(substr($base_domain, 0,4) === "www."){
		$base_domain = substr($base_domain,4);
	}
	if(substr($link_domain['host'], 0,4) === "www."){
		$link_domain['host'] = substr($link_domain['host'],4);
	}
	// subdomain check
    if(strpos($link_domain['host'], $base_domain)){
    	return true;
    }
	return ($base_domain === $link_domain['host']); 
}

function recursive_loop_child_elements($element, &$el_elements) {
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

function _get_elements($doc, string $tag, string $url) : array {
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
    } else { // default element => no child nodes 
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

function setUserAgent() : string {
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

function publicIp(bool $prox_opt) {
	$ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://ipinfo.io/ip', // alt-'https://httpbin.org/ip', 'ipv4.icanhazip.com'
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
        CURLOPT_HTTPHEADER => [
            'User-Agent: '.setUserAgent(),
        ],
    ]);
    if($prox_opt === 1){
		curl_setopt($ch,CURLOPT_PROXY, '127.0.0.1:9050');
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

function fetch_html_elements_by_type(array $elements, string $filter_element) : array {
    $data = [];
    if(empty($elements)){
        return $data;
    }
    recursive_filter_elements($elements, $data, $filter_element);
    return ! empty($data) ? $data : [];
}

function recursive_filter_elements($element, &$data, string $filter_element){
	foreach ($element as $k => $el_vals) {
		if(is_iterable($el_vals) && ! empty($el_vals)){
			recursive_filter_elements($el_vals, $data, $filter_element);
		}
		else {
			$node_el = $el_vals->node_elements ?? null;
			$node = $el_vals->node ?? null;
			if($node_el !== null && $node !== null && $node === $filter_element){
				foreach ($node_el as $node_el_key => $node_el_val) {
					$data["{$node_el_key}"] = $node_el_val;
				}
			}
		}
	}
}

function recursive_read_elements($elements) {
	foreach ($elements as $key => $el) {
		if(is_object($el) || is_array($el)) {
			recursive_read_elements($el);
		} else {
			echo "{$key}: {$el}\n\n";
		}
	}
}

?>
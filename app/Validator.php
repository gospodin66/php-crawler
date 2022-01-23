<?php
namespace App;

use App\CurlHandler;
use App\Output;
use App\Log;
use App\Helper;

class Validator {

    public function validate_url(
        string &$url,
        string $scheme,
        string $domain,
        int $key,
        int $hrefs_total,
        int $indent_len,
        array $crawled,
        string $target_dir,
        CurlHandler $curl_handler, // GLOBAL CURL HANDLER OBJ
    ) : bool
    {
        /**
         * &$url is passed by ref. => re-craft rel. url
         */
        $out = new Output;
        $abs_path = "{$target_dir}/links/abs.txt";
        $rel_path = "{$target_dir}/links/rel.txt";
        $log_path = "{$target_dir}/log.txt";
        $timestamp = date("Y-m-d H:i:s");
        $output_vars = [
            'response' => "Warning",
            'crawled_total' => count($crawled),
            'key' => $key,
            'hrefs_total' => $hrefs_total,
            'url' => $url,
            'indent_len' => $indent_len,
            'exectime' => 0,
            'http_code' => 0,
            'res_size' => 0,
            'errmsg' => '',
        ];
        
        // skip empty | broken
        if(empty($url) || preg_match(BROKEN_URL_REGEX, $url)) {
            $output_vars['errmsg'] = "Empty/broken path";
            $out->display_formatted_output($output_vars);
            Log::write_log("> [{$timestamp}] > Warning | [Empty/broken path] > [{$url}]\r\n", $log_path);

            // manual curl close should occur only when last request throws err|warning
            if($key === $hrefs_total){
                $curl_handler->manual_curl_close();
            }
            return false;
        }
        // do not download content!
        else if(preg_match(CONTENT_REGEX, $url, $matches)) {
            $remote_filesize = Helper::format_file_size_to_str($curl_handler->exec_remote_filesize_request($url));
            $output_vars['errmsg'] = sprintf(
                "> [".COLOR_YELLOW."Content URL ".COLOR_RESET.
                "> [".COLOR_YELLOW."{$remote_filesize}".COLOR_RESET."] ".
                "> [".COLOR_YELLOW."{$matches}".COLOR_RESET."]]\r\n"
            );
            $out->display_formatted_output($output_vars);
            Log::write_log("> [{$timestamp}] > Warning | [Content URL > [{$remote_filesize}] > [{$url}]]\r\n", $log_path);
            Log::write_log("{$url}\r\n", "{$target_dir}/results/content.txt");

            // manual curl close should occur only when last request throws err|warning
            if($key === $hrefs_total){
                $curl_handler->manual_curl_close();
            }
            return false;
        }
        // do not exec scripts!
        else if(preg_match(SCRIPT_REGEX, $url, $matches)) {
            $output_vars['errmsg'] = sprintf("Script format: %s", $matches[0]);
            $out->display_formatted_output($output_vars);
            Log::write_log("> [{$timestamp}] > Warning | [Script format: {$matches[0]}] > [{$url}]\r\n", $log_path);

            // manual curl close should occur only when last request throws err|warning
            if($key === $hrefs_total){
                $curl_handler->manual_curl_close();
            }
            return false;
        }
        // skip mailto: | tel:
        else if(preg_match("/^(mailto:|tel:)/", $url)) {
            $out->display_formatted_output($output_vars);
            Log::write_log("> [{$timestamp}] > Warning | [{$output_vars['errmsg']}] > [{$url}]\r\n", $log_path);

            // manual curl close should occur only when last request throws err|warning
            if($key === $hrefs_total){
                $curl_handler->manual_curl_close();
            }
            return false;
        }
    
        // invalid url format => adapt
        if(preg_match(URL_REGEX, $url) === 0) {
            // save rel links before adaptation
            Log::write_log("{$url}\r\n", $rel_path);
            if($url[0] === "/" && substr($url, 0, 2) !== "//") {
                $url = "{$scheme}://{$domain}{$url}";
            } else if(substr($url, 0, 2) === "//") {
                // => //some/path = example.com/path
                $url = "{$scheme}://{$domain}/".basename($url);
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
            Log::write_log("{$url}\r\n", $abs_path);
        }
    
        if(in_array($url, $crawled)){
            return false;  
        }
    
        if( ! $this->validate_base_domain($domain,$url,$scheme)){
            $errmsg = "Invalid base domain";
            $output_vars['errmsg'] = $errmsg;
            $out->display_formatted_output($output_vars);
            Log::write_log("> [{$timestamp}] > Invalid base domain [{$url}]\r\n", $log_path);

            // manual curl close should occur only when last request throws err|warning
            if($key === $hrefs_total){
                $curl_handler->manual_curl_close();
            }
            return false;
        }

        return true;
    }
    
    
    private function validate_base_domain(string $base_domain, string $link_domain, string $scheme) : bool {

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
    
            return $base_ip_port === $link_ip_port;
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
    
            return $b_top_domain === $l_top_domain; 
        }
    }
}


?>
<?php
namespace App;

use \CurlHandle;
use App\UserAgentGenerator;

class CurlHandler {

    private CurlHandle $ch;

    public function __construct(){
        $this->ch = curl_init();
    }

    public function exec_remote_filesize_request(string $url) : float {
        // init separate curl handler
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
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
    
    public function manual_curl_close() : void {
        printf("Manually closing cURL handle.".NL);
        curl_close($this->ch);
        return;
    }
    
    public function get_public_ip(int $prox_opt) : string {
        // init separate curl handler
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://ipinfo.io/ip', // alt-'https://httpbin.org/ip', 'ipv4.icanhazip.com'
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => [
                'User-Agent: '.(new UserAgentGenerator)->generate(),
            ],
        ]);
        if($prox_opt === 1) {
            curl_setopt($ch, CURLOPT_PROXY, TOR_SOCKS5_ADDR);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    
    function exec_curl_request(
        string $scheme,
        string $url,
        int $prox_opt,
        string $args = "",
        string $method = "GET",
        bool $close_curl_handle = false,
    ) : array
    {
        if(empty($this->ch)){
            printf("> Re-initializing curl..".NL);
            $this->ch = curl_init();
        }
        curl_setopt_array($this->ch, $this->select_opts($scheme,$url,$prox_opt,$args,$method));
        $page = curl_exec($this->ch);
        $curlinfo = curl_getinfo($this->ch);
        $cookies = curl_getinfo($this->ch, CURLINFO_COOKIELIST);
        if(curl_errno($this->ch)){
            $error = "Crawler cURL error: ".curl_error($this->ch);
            curl_close($this->ch);
            return [
                'status' => 'error',
                'error'  => $error,
                'info' => $curlinfo,
            ];
        }
        if($close_curl_handle){
            printf(NL."> ".COLOR_CYAN."Closed cURL handle!".COLOR_RESET.NL);
            curl_close($this->ch);
        }
        return [
            'status' => 'success',
            'page' => $page,
            'info' => $curlinfo,
            'host' => "{$curlinfo['primary_ip']}:{$curlinfo['primary_port']}",
            'cookies' => $cookies,
        ];
    }

    private function select_opts(
        string $scheme,
        string $url,
        int $prox_opt,
        string $args = "",
        string $method = "GET"
    ) : array
    {
        $opts = [
            CURLOPT_URL             => preg_match('/^'.$scheme.'/', $url) ? $url : "{$scheme}://{$url}",
            CURLOPT_HEADER          => 0,
            CURLOPT_VERBOSE         => 0, // 0
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_CONNECTTIMEOUT  => 30,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_SSL_VERIFYPEER  => $scheme === 'https' ? 1 : 0,
            CURLOPT_SSL_VERIFYHOST  => $scheme === 'https' ? 2 : 0,
            CURLOPT_FOLLOWLOCATION  => 1,
            CURLOPT_MAXREDIRS       => 5,
            CURLOPT_COOKIEJAR       => '../session/SessionCookies.txt',
            CURLOPT_COOKIEFILE      => '../session/SessionCookies.txt',
            CURLOPT_HTTPHEADER      => [
                'User-Agent: '.(new UserAgentGenerator)->generate(),
                'Accept: */*',
                'Cache-Control: no-cache',
            ]
        ];
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')){
            $opts += [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4];
        }
        if($prox_opt === 1){
            $opts += [CURLOPT_PROXY => TOR_SOCKS5_ADDR];
            $opts += [CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME];
        }
        if( ! empty($args)){
            if( ! empty($method) && $method === "POST"){
                // craft array of params from query string
                $args_temp_arr = explode("&",$args);
                $args_temp = $args_arr = [];
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
        } else {
            // no args -> "GET" as default method
            $opts += [CURLOPT_HTTPGET => 1];
            $opts[CURLOPT_URL] = $opts[CURLOPT_URL]."/?{$args}";
        }
        return $opts;
    }

}
?>
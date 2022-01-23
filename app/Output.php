<?php
namespace App;

use \DOMDocument;
use App\CurlHandler;
use App\Helper;
use App\HTMLProcessor;
use App\Core;


class Output {

    public static function display_options(
        bool $main_opts,
        array $collected_elements,
        array $parsed_url,
        string $target_dir,
        CurlHandler $curl_handler, // GLOBAL CURL HANDLER OBJ
        DOMDocument $doc,
        string $scheme,
        int $prox_opt,
    ) : void
    {
        $html_proc = new HTMLProcessor;
        $collected_json = json_encode($collected_elements);
        $indent = 0; // used to indent child elements in recursive output

        if($main_opts) {
            $opts_str = sprintf(
                            "\r\n> Main Options:".
                            "\r\n> p -print main results".
                            "\r\n> s -save main results [JSON file size: %s]".
                            "\r\n> j -fetch elements by type".
                            "\r\n> f -follow webpage links".
                            "\r\n> r -return".
                            "\r\n> e -exit\r\n",
                            Helper::format_file_size_to_str(strlen($collected_json))
                        );
            $results_path = "{$target_dir}/results/results_main.json";
            $results_filtered_path = "{$target_dir}/results/results_main_filtered.json";
            $line_arrow = "[1st] > ";
        } else {
            $opts_str = sprintf(
                            "\r\n> Options: ".
                            "\r\n> p -print all results".
                            "\r\n> s -save all results [JSON file size: %s]".
                            "\r\n> j -fetch elements by type".
                            "\r\n> r -return".
                            "\r\n> e -exit\r\n",
                            Helper::format_file_size_to_str(strlen($collected_json))
                        );
            $results_path = "{$target_dir}/results/results.json";
            $results_filtered_path = "{$target_dir}/results/results_filtered.json";
            $line_arrow = "[2nd] > ";
        }

        printf("%s", $opts_str);

        while(1){
            
            $opt = readline($line_arrow);
            readline_add_history($opt);

            if($opt === 'p'){
                $html_proc->recursive_read_elements($collected_elements, $indent);
                printf("\r\n");
            }
            else if($opt === 'j'){
                switch (readline("> Enter HTML element as filter: ")) {
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
                if($main_opts){
                    $html_proc->recursive_filter_main_elements(Helper::read_json($results_path), $eles_by_type, $filter_el);
                } else {
                    $html_proc->recursive_filter_all_elements(Helper::read_json($results_path), $eles_by_type, $filter_el);
                }
                $unique_eles_by_type = $html_proc->filter_iterable_unique($eles_by_type);
                if(file_put_contents($results_filtered_path, json_encode($unique_eles_by_type)) !== false){
                    printf("> Fitered elements saved to file successfuly > [%s]\r\n", $results_filtered_path);
                }
                $html_proc->recursive_read_elements($unique_eles_by_type, $indent);
            }
            else if($opt === 's'){
                if(file_put_contents($results_path, json_encode($collected_elements)) !== false){
                    printf("> ".COLOR_GREEN."Saved".COLOR_RESET." > File path: [%s]".COLOR_RESET."\r\n", $results_path);
                }
                printf("\r\n> Successfuly saved file > [%s]\r\n", $results_path);
            }
            else if($opt === 'f'){
                if( ! empty($parsed_url['port'])){
                    $fmt_url = "{$parsed_url['host']}:{$parsed_url['port']}";
                } else {
                    $fmt_url = $parsed_url['host'];
                }
                Core::follow_links($curl_handler,$doc,$fmt_url,$scheme,$prox_opt,$target_dir);
            }
            else if($opt === 'e'){ // exit
                exit(0);
            }
            else if($opt === 'r'){ // return
                break;
            }
            else {
                print(COLOR_RED."> Invalid argument".COLOR_RESET."\r\n");
            }
        }
        return;
    }


    public function display_formatted_output(array $vars) : void {
        $whitespace_indent = ' ';
        switch ($vars['response']) {
            case 'Error':
                $indents = ["%4s", "%-7s ", "%-1s", "%-1s",];
                $color = COLOR_RED;
                $response = $vars['response']; // ERROR|WARNING|SUCCESS
                $total_crawled = $vars['crawled_total']; // num of successfuly crawled pages
                $current_index = $vars['key']; // current index
                $hrefs_total = $vars['hrefs_total']; // total indexes
                $url = $vars['url'];
                $exectime = $vars['exectime'] ?? 0;
                $res_size = $vars['res_size'] ?? 0;
                $http_code = $vars['http_code'] ?? 0;
                $indent_len = $vars['indent_len'];
                $errmsg = sprintf("%-{$indent_len}s [{$color}%s".COLOR_RESET."]", $whitespace_indent, $vars['errmsg']);
                break;
            case 'Success':
                $http_code = $vars['http_code'];
                $res_size = $vars['res_size'] ?? 0;
                if($http_code === 200) {
                    $indents = ["%1s ", "%-".(8 - strlen($res_size))."s ", "%-1s", "%-1s",];
                    $color = COLOR_GREEN;
                    $errmsg = "";
                } else {
                    $indents = ["%3s", "%-".(8 - strlen($res_size))."s ", "%-1s", "%-1s",];
                    $color = COLOR_YELLOW;
                    $errmsg = $vars['errmsg'];
                }
                $response = $vars['response']; // ERROR|WARNING|SUCCESS
                $total_crawled = $vars['crawled_total']; // num of successfuly crawled pages
                $current_index = $vars['key']; // current index
                $hrefs_total = $vars['hrefs_total']; // total indexes
                $exectime = $vars['exectime'];
                $url = $vars['url'];
                $indent_len = $vars['indent_len'];
                $errmsg = "";
                break;
            case 'Warning':
                $indents = ["%2s", "%-8s", "%-5s", "%-3s",];
                $color = COLOR_YELLOW;
                $response = $vars['response']; // ERROR|WARNING|SUCCESS
                $total_crawled = $vars['crawled_total']; // num of successfuly crawled pages
                $current_index = $vars['key']; // current index
                $hrefs_total = $vars['hrefs_total']; // total indexes
                $url = $vars['url'];
                $exectime = $vars['exectime'] ?? 0;
                $res_size = $vars['res_size'] ?? 0;
                $http_code = $vars['http_code'] ?? 0;
                $indent_len = $vars['indent_len'];
                $errmsg = sprintf("%-{$indent_len}s [{$color}%s".COLOR_RESET."]", $whitespace_indent, $vars['errmsg']);
                break;
            default:
                printf("Invalid response: %s", $vars['response']);
                return;
        }
        printf(
            "> {$color}%s".COLOR_RESET.$indents[0].
            " [%d] [%d/%d] ".                            // total_crawled|current_index|hrefs_total
            " [{$color}%s".COLOR_RESET."]{$indents[1]}". // content size
            " [{$color}%s".COLOR_RESET."]{$indents[2]}". // exectime
            " [{$color}%d".COLOR_RESET."]{$indents[3]}". // HTTP response code
            " [%s]\r\n".                                 // URL
            ( ! empty($errmsg) ? " {$errmsg}\r\n" : ""),
    
            ucfirst($response),
            $whitespace_indent,
            $total_crawled, $current_index, $hrefs_total,
            $res_size,
            $whitespace_indent,
            $exectime,
            $whitespace_indent,
            $http_code,
            $whitespace_indent,
            $url,
        );
        return;
    }
}
?>
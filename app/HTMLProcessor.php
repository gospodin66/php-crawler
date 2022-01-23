<?php
namespace App;

use \DOMDocument;
use \DOMElement;
use \DOMText;
use \DOMXpath;

class HTMLProcessor {
    private function extract_text_from_html(DOMText|DOMElement $dom_element) : array {
        $new_dom = new DOMDocument;
        // convert DOMElement/DOMText to DOMDocument as DOMXpath accepts only DOMDocument as arg
        $new_dom->appendChild($new_dom->importNode($dom_element, true));
        $xpath = new DOMXpath($new_dom);
        $texts = [];
        foreach ($xpath->query('//text()', $new_dom) as $textNodeKey => $textNode) {
            $v = trim(str_replace("\n", " ", $textNode->nodeValue));
            if( ! empty($v) || preg_match_all("/^\s+$/", $v)){
                $texts[] = $v;
            }
        }
        return $texts;
    }

    public function extract_html_elements(DOMDocument $doc, string $tag, string $url) : array {
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
        foreach ($eles as $key => $el){
            $_elements = []; // array-collector
            $counter = 0;
            $this->recursive_loop_child_elements($el, $_elements, $counter);
            if( ! empty($_elements)){
                $elements['node_elements']["{$elements['node']}-{$key}"] = $_elements;
            }
        }
        return $elements;
    }

    public function recursive_loop_child_elements(array|object $element, array &$el_elements, &$counter) : void {
        if(is_iterable($element)){
            foreach ($element as $key => $el) {
                $this->extract_node_values($el, $el_elements, $counter);
                if($el->hasChildNodes()){
                    $this->recursive_loop_child_elements($el->childNodes, $el_elements, $counter);
                }
            }
        } else {
            $this->extract_node_values($element, $el_elements, $counter);
            if($element->hasChildNodes()){
                $this->recursive_loop_child_elements($element->childNodes, $el_elements, $counter);
            }
        }
    }

    private function extract_node_values(array|object $element, array &$el_elements, int &$counter) : void {
        $texts = ($element instanceof DOMText || $element instanceof DOMElement)
               ? $this->extract_text_from_html($element)
               : [];
        if( ! empty($element->attributes)){
            $elekey = "{$element->nodeName}-{$counter}";
            foreach($element->attributes as $attrkey => $attr){
                if( ! empty($attr->nodeValue)){
                    $el_elements[$elekey][$attr->nodeName] = [
                        "val" => trim(str_replace("\n", " ", $attr->nodeValue)),
                        "text" => $texts,
                    ];
                    if(array_key_exists($elekey, $el_elements)){
                        if($attrkey === array_key_first($el_elements[$elekey])){
                            // remove empty text vals
                            if(empty($el_elements[$elekey][$attrkey]['text'])){
                                unset($el_elements[$elekey][$attrkey]['text']);
                            }
                            continue;
                        }
                        // remove duplicate texts => only 1st element has text
                        foreach($el_elements[$elekey][$attr->nodeName]['text'] as $currkey => &$currttxt){
                            foreach($texts as $key => $txt){   
                                if($currttxt === $txt){
                                    $el_elements[$elekey][$attr->nodeName]['text'] = [];
                                    break 2;
                                }
                            }
                        }
                        unset($currtext);
                        // remove empty text vals
                        if(empty($el_elements[$elekey][$attr->nodeName]['text'])){
                            unset($el_elements[$elekey][$attr->nodeName]['text']);
                        }
                    }
                }
            }
            $counter++;
        }
    }

    public function recursive_filter_main_elements(array|object $elements, array &$data, string $filter_element) : void {
        foreach ($elements as $k => $el_vals) {
            if(empty($el_vals)){
                continue;
            }
            $node = $el_vals->node ?? null;
            $node_el = $el_vals->node_elements ?? null;
            if(is_iterable($node_el)) {
                $this->recursive_filter_main_elements($node_el, $data, $filter_element);
            }
            else {
                if( ! empty($node_el) && ! empty($node) && $node === $filter_element){
                    foreach ($node_el as $node_el_key => $node_el_val) {
                        $data[] = $node_el_val;
                    }
                }
            }
        }
    }

    public function recursive_filter_all_elements(array|object $elements, array &$data, string $filter_element) : void {
        foreach ($elements as $k => $el_vals) {
            foreach ($el_vals as $key => $el_node_elements) {

                $node = $el_node_elements->node ?? null;
                $node_el = $el_node_elements->node_elements ?? null;

                if( ! empty($node) && ! empty($node_el)){
                    if(is_iterable($node_el)){
                        $this->recursive_filter_all_elements($node_el, $data, $filter_element);
                    }
                    else {
                        if(substr($node, 0, strlen($filter_element)) === $filter_element){ // HTML element
                            foreach ($node_el as $node_el_key => $node_el_val) {
                                $data[] = $node_el_val;
                            }
                        }
                    }
                }
            }
        }
    }

    public function recursive_read_elements(array|object $elements, int &$indent) : void {
        foreach ($elements as $key => $el) {
            if(is_object($el) || is_array($el)) {
                $indent++; // increment indent on every new call => has children
                $this->recursive_read_elements($el, $indent);
            } else {
                if($key === 'node' || $key === 'node_url'){
                    $color = COLOR_LIGHT_BLUE;
                    $k = str_replace('_', ' ', strtoupper($key));
                } else {
                    $color = COLOR_WHITE;
                    $k = str_replace('_', ' ', $key);
                }
                printf("%-{$indent}s{$color}%s: %s".COLOR_RESET, ' ', $k, $el);
                printf("%s",NL);
            }
        }
        $indent--; // decrement indent
    }

    public function filter_iterable_unique(iterable $iterable, bool $keep_key_assoc = false) : array {

        $duplicate_keys = $tmp = [];

        foreach($iterable as $key => $val){
            // convert objects to arrays, in_array() does not support objects
            if(is_object($val)){
                $val = (array)$val;
            }
            if( ! in_array($val, $tmp)){
                $tmp[] = $val;
            } else {
                $duplicate_keys[] = $key;
            }        
        }
        foreach ($duplicate_keys as $key){
            unset($iterable[$key]);
        }
        return $keep_key_assoc ? $iterable : array_values($iterable);
    }
}
?>
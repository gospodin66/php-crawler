<?php
//setlocale(LC_CTYPE, "en_US.UTF-8");

if($argc < 2) die("Assign argument/s. -> php scrap.php <example.com>\n");
else if ($argc > 2) die("Only 1 argument needs to be asigned.\n");

$url = trim($argv[1]);
$ch = curl_init();

/*curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: *//*',
    //'Content-length: 1000'
    
));*/
$opts = array(
	CURLOPT_URL 			=> $url,
    CURLOPT_HEADER 			=> 0,
    CURLOPT_VERBOSE 		=> 1,
    CURLOPT_POST			=> 0,
    CURLOPT_RETURNTRANSFER 	=> 1,	// return page
    CURLOPT_CONNECTTIMEOUT 	=> 10,	// connect timeout
    CURLOPT_TIMEOUT        	=> 30,	// response timeout
    CURLOPT_SSL_VERIFYPEER  => 1,
    CURLOPT_FOLLOWLOCATION	=> 1,
    CURLOPT_MAXREDIRS 		=> 5
 );

curl_setopt_array($ch, $opts);
$page = curl_exec($ch);

if(curl_errno($ch)){ 							// check for execution errors
	die("Scraper error: ".curl_error($ch)."\n");
}
curl_close($ch);


$doc = new DOMDocument();
@$doc->loadHTML($page);

$title = $doc->getElementsByTagName("title");
@$title = $title->item(0)->nodeValue;

$d1 = "\n\33[93m********************************** 	<link>	 ************************************\n\n\33[0m ";
$d2 = "\n\33[93m********************************** 	<meta>	 ************************************\n\n\33[0m ";
$d3 = "\n\33[93m********************************** 	<a href>	 **********************************\n\n\33[0m ";
$d4 = "\n\33[93m********************************** 	<img>	 ************************************\n\n\33[0m ";
$d5 = "\n\33[93m********************************** 	<script>	********************************\n\n\33[0m ";


$string = "[".$d1.header_links($doc).$d2.metas($doc).$d3.hrefs($doc).$d4.imgs($doc).$d5.scripts($doc)."]";
$string = str_replace("||", "\n", $string);
$string = substr_replace($string, '', -3, 2);

$json = str_replace("> ", "", $string);
$json = str_replace($d1, "", $json);
$json = str_replace($d2, "", $json);
$json = str_replace($d3, "", $json);
$json = str_replace($d4, "", $json);
$json = str_replace($d5, "", $json);


file_put_contents($title.".json", $json);

while(1){
	print "\33[93mOptions: (e exit, p print, f follow): \33[0m";
	switch (readline()) {
	 	case 'p':
		print $string;
		break 2;
	 	
		case 'e':
		break 2;

		case 'f':
		follow_links($doc,$d1,$d2,$d3,$d4,$d5);
		break;

	 	default:
		print "\033[31m> Invalid argument.\33[0m\n";
	 	break;
	 } 
}	

//print "\x07";	// beep 
print "\033[32m\nFinished.\n \033[0m";
//print `ls -l`; // backtick operator `` <=> shell_exec()
exit();

/**************************************************************/



function follow_links($doc,$d1,$d2,$d3,$d4,$d5){

	$links = $doc->getElementsByTagName('a');
	$scraped = array();
	$string = "[";

		foreach ($links as $a) {	// loop through elements
			foreach ($a->attributes as $link) {	// loop through attributes

				if($link->nodeValue == "#" || preg_match('/#.+/', $link->nodeValue) == 1 ||
				 	$link->nodeName != "href" || empty($link->nodeValue))
				{
					print "\033[31m> Invalid link format.\033[0m \n";
					break;
				}


				$url = trim($link->nodeValue);
				if(in_array($url, $scraped)){
				   break;  
				}
				$scraped[] = $url;


				
				$ch = curl_init();
				$opts = array(
					CURLOPT_URL 			=> $url,
				    CURLOPT_HEADER 			=> 0,
				    CURLOPT_VERBOSE 		=> 1,
				    CURLOPT_POST			=> 0,
				    CURLOPT_RETURNTRANSFER 	=> 1,	// return page
				    CURLOPT_CONNECTTIMEOUT 	=> 10,	// connect timeout
				    CURLOPT_TIMEOUT        	=> 30,	// response timeout
				    CURLOPT_SSL_VERIFYPEER  => 1,
				    CURLOPT_FOLLOWLOCATION	=> 1,
				    CURLOPT_MAXREDIRS 		=> 5
				 );

				curl_setopt_array($ch, $opts);
				$page = curl_exec($ch);

				if(curl_errno($ch)){ 							// check for execution errors
					print "Scraper error: ".curl_error($ch)."\n";
					curl_close($ch);
					break;
				}
				curl_close($ch);


				$doc = new DOMDocument();
				@$doc->loadHTML($page);

				$title = $doc->getElementsByTagName("title");
				@$title = $title->item(0)->nodeValue;

				$string .= $d1.header_links($doc).$d2.metas($doc).$d3.hrefs($doc).$d4.imgs($doc).$d5.scripts($doc);
				$string = str_replace("||", "\n", $string);

				print $string."\n";
				print "\033[32m> Finished.\n\n";
				print "\n\033[96m>Scraped webpages: ".(count($scraped)-1)."\33[0m\n\n";
			}
		}
		print "\33[96mList of scraped pages:\n";
		print_r($scraped);
		print "\33[0m";

		while(1){
		print "\33[93mOptions: (r return, s save [".strlen($string)." bytes]): \33[0m";
		switch (readline()) {
			case 'r':
			break 2;

			case 's':
			$string = substr_replace($string, ']', -2, 2);
			$json = str_replace("> ", "", $string);
			$json = str_replace($d1, "", $json);
			$json = str_replace($d2, "", $json);
			$json = str_replace($d3, "", $json);
			$json = str_replace($d4, "", $json);
			$json = str_replace($d5, "", $json);
			file_put_contents("Output.json", $json);
			print "\33[32mSaved.\33[0m\n";
			break 2;

		 	default:
		 	print "\033[31m> Invalid argument.\33[0m\n";
		 	break;
		} 
	}
}



function header_links($doc){

	$links = $doc->getElementsByTagName('link');
	$fmtd = "";

	foreach ($links as $link) {	 // loop through elements
		$fmtd .= '> {"Element": "<'.$link->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($link->attributes as $attr) {	// loop through attributes
			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes(trim($attr->nodeValue),'"').'"},||';
		}				
	}
	return $fmtd;
}

function hrefs($doc){

	$links = $doc->getElementsByTagName('a');
	$fmtd = "";

	foreach ($links as $a) {	// loop through elements
		$fmtd .= '> {"Element": "<'.$a->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($a->attributes as $attr) {	// loop through attributes

			$fmtd .= ($attr->nodeValue == "#"   || 
						 	$attr->nodeName != "href" ||
						 	empty($attr->nodeValue))
				      		? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes($attr->nodeValue,'"').'"},||';
		}							
	}
	return $fmtd;
}

function metas($doc){

	$metas = $doc->getElementsByTagName('meta');
	$fmtd = "";

	foreach ($metas as $meta) {
		$fmtd .= '> {"Element": "<'.$meta->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($meta->attributes as $attr) {	

			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes($attr->nodeValue,'"').'"},||';
		}							
	}
	return $fmtd;
}

function imgs($doc){

	$imgs = $doc->getElementsByTagName('img');
	$fmtd = "";

	foreach ($imgs as $img) {
		$fmtd .= '> {" Element": "<'.$img->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($img->attributes as $attr) {	
			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addcslashes($attr->nodeValue,'"').'"},||';
		}							
	}
	return $fmtd;
}

function scripts($doc){

	$scripts = $doc->getElementsByTagName('script');
	$fmtd = "";

	foreach ($scripts as $script) {
		$fmtd .= '> {"Element": "<'.$script->nodeName.'>"},';
		$fmtd .= "\n";
		foreach ($script->attributes as $attr) {	
			$fmtd .= empty($attr->nodeValue) ? "" : '> {"Node": "'.$attr->nodeName.'", "Value": "'.addslashes($attr->nodeValue).'"},||';
		}							
	}
	return $fmtd;
}

?>

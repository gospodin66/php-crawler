# php-crawler
$ php crawler.php -p example.com -s https (OPTIONAL ARGS >) -x 1 -a"param1=x&param2=y" -mPOST<br/>
- initial request can use GET|POST methods with parameters<br/>
- crawling initial request links (not going deeper levels but can be easily adapted to crawl all links)<br/>
- supports cURL requests using Tor SOCKS5 proxy (127.0.0.1:9050)<br/>
- skipping content formats: .(x)apk | .ipa | .mp3(4) | .jp(e)g | .png | .pdf | .doc(x) | .odt | .xls |<br/>
      -> instead log file size and url<br/>
- skipping script formats: .js | javascript url | .php | .py | asp(x) |<br/>
- recursive:<br/>
    - scrapping webpages elements to infinite levels<br/>
    - reading results from .json decoded file<br/>
    - filtering results by html tag<br/>
- logging: <br/>
    - abs|rel links<br/>
    - initial-request-results|all-results (a|li|img|meta|link|form|table|script) > (.json)<br/>


TODOs::<br/>
  - fix recursive filter elements (on whole result) by html tag<br/>
  - fix output alignment<br/>
  - disable public IP check on argument<br/>
  - format code (many cases can be shortened)<br/>

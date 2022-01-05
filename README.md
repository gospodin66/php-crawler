# php-crawler
<p>$ php crawler.php -p example.com -s https (OPTIONAL ARGS >) -x 1 -a"param1=x&amp;param2=y" -mPOST</p>
<ul>
      <li>initial request can use GET|POST methods with parameters</li>
      <li>crawling initial request links (not going deeper levels but can be easily adapted to crawl all links)</li>
      <li>supports cURL requests using Tor SOCKS5 proxy (127.0.0.1:9050)</li>
      <li>skipping content formats: .(x)apk | .ipa | .mp3(4) | .jp(e)g | .png | .pdf | .doc(x) | .odt | .xls<br/>
            <ul>
                  <li>instead log file size and url</li>
            </ul>
      </li>
      <li>skipping script formats: .js | javascript url | .php | .py | asp(x)</li>
      <li>recursive:
            <ul>
                  <li>scrapping webpages elements to infinite levels</li>
                  <li>reading results from .json decoded file</li>
                  <li>filtering results by html tag</li>
            </ul>
      </li>
      <li>logging:
            <ul>
                  <li>abs|rel|content urls</li>
                  <li>initial-request-results|all-results (a|li|img|meta|link|form|table|script) > (.json)</li>
            </ul>
      </li>
</ul>
<p>TODOs::<br/>
  - fix recursive filter elements (on whole result) by html tag<br/>
  - fix output alignment<br/>
  - disable public IP check on argument<br/>
  - format code (many cases can be shortened)<br/>
</p>
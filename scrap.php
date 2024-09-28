<?php 
/*

scrap.php - (c) 2019 - Remi Peyronnet  ; released under GPL license

Scrap configuration documentation : 

    url : url with %s to be replaced by the resource (sprintf syntax)
    context : (optional, default null) context to be used with url
    token : (optional) array of valid tokens
    cacheable : number of seconds of cache validity (0 means no cache)
    method : (optional, default 'preg') search method  
        preg : test with https://regex101.com/
        xpath : xpath search 
        css : search with css-like selectors
    search : search string (depending on method)
    flags : (optional, default null) flags to be used with the search
        xpath: nohtml to force valid xml parsing
    post_method: (optional, default 'preg_replace') post-processing method
    post_search: (optional) search string for post-processing
    post_replace: (optional) replace string for post-processing
    doc: (optional) 


Developer documentation :

File structure : 
- configuration
- core functions
- bundled plugins
- plugins loading
- main function

To test in command line : REQUEST_METHOD=GET PATH_INFO=/ping php scrap.php

TODO :
- conf generale / locale  (tokens, cache,..) --> get-scrap --> get / global

*/


//  Configuration -------------------------------------------------------------

$scrap = [];

$scrap["google-numresults"] = [
        'url' => 'https://www.google.com/search?q=%s',
        'cacheable' => 3600,
        'search' => '/id="resultStats">\s*(?P<num>[^<]*)</ms',
        'tokens' => ['test'],
        'doc'=> 'Get the number of results of a Google search',
    ];

$scrap["google-numresults-xpath"] = [
        'url' => 'https://www.google.com/search?q=%s',
        'cacheable' => 3600,
        'method' => 'xpath',
        'search' => '//*[@id="resultStats"]/text()',
        'tokens' => ['test'],
        'doc'=> 'Get the number of results of a Google search (with XPath method)',
    ];

$scrap["google-numresults-css"] = [
        'url' => 'https://www.google.com/search?q=%s',
        'cacheable' => 3600,
        'method' => 'css',
        'search' => '#resultStats',
        'tokens' => ['test'],
        'post_search' => '/[^\d]/',
        'post_replace' => '',
        'doc'=> 'Get the number of results of a Google search (with CSS method)',
    ];

$cache_dir = "cache/";

// Local configuration file to customize above parameters
if (file_exists("scrap.conf.php")) { include("scrap.conf.php"); }



//  Core functions -------------------------------------------------------------

function get(&$value, $default = null) {
    return isset($value) ? $value : $default;
}

$endpoints= [];
function add_endpoint($method, $regexp, $function, $doc_function) {
 global $endpoints;
 $endpoints[] = [
   "method" => $method,
   "regexp" => $regexp,
   "function" => $function, // function($uri, $matches)
   "doc_function" => $doc_function,
 ];
}

$parsers= [];
function add_parser($method, $parser) {
 global $parsers;
 $parsers[$method] = $parser; // function($search, $content, $flags)
}

$postprocessors= [];
function add_postprocessor($method, $postprocessor) {
 global $postprocessors;
 $postprocessors[$method] = $postprocessor; // function($content; $search, $replace)
}

function cache_put($url, $content) {
    global $cache_dir;
    $md5 = md5($url);
    $cache_file = $cache_dir . DIRECTORY_SEPARATOR . $md5;
    if (is_writable($cache_dir)) { 
        file_put_contents($cache_file,$content);
    } else {
        // silently ignore if directory not writeable
        //die('Cache folder not writeable');
    }
    return false;
}

function cache_get($url, $timeout) {
    global $cache_dir;
    $md5 = md5($url);
    $cache_file = $cache_dir . DIRECTORY_SEPARATOR . $md5;
    if (time() <= (filemtime($cache_file) + $timeout)) {
        return file_get_contents($cache_file);
    }
    return false;
}

function cache_clean()  {
    global $cache_dir;
    
    if ( ($cache_dir == "") || ($cache_dir[0] == "/") || strpos ("..", $cache_dir) ) {
        die("Suspicious $cache_dir"); 
    }

    $nb = 0;
    $files = glob($cache_dir . '/*');
    foreach($files as $file){ // iterate files
      if(is_file($file)) {
        unlink($file); // delete file
        if (!file_exists($file)) $nb++;
        }
    }
    return $nb;
}


//  Bundled plugins -----------------------------------------------------------

// = plugin-ping.php 

function plugin_ping() {
    print("OK");
}
function plugin_ping_doc() { return <<<EOT
  /ping:
    get:
      summary: Ping scrap.php API
      description: Ping the API
      tags:
      - "tools"
      produces:
      - "text/plain"
      responses:
        200:
          description: "Successful operation"
          schema:
            type: string
            example: "OK"

EOT;
}
add_endpoint("GET","!/ping$!", 'plugin_ping', 'plugin_ping_doc');

// = plugin-clean.php
function plugin_clean_cache() {
    $nb = cache_clean();
    print("Cache cleaned ($nb files deleted)");
}
function plugin_clean_cache_doc() { return <<<EOT
  /clean-cache:
    get:
      summary: Clean the cache of the scap.php API
      description: Clean the cache of the scap.php API. Note that all files will be deleted (not based on timestamp, no check if source is still available)
      tags:
      - "tools"
      produces:
      - "text/plain"
      responses:
        200:
          description: "Successful operation"
          schema:
            type: string
            example: "OK"

EOT;
}
add_endpoint("GET","!/clean-cache$!", 'plugin_clean_cache', 'plugin_clean_cache_doc');

// = plugin-openapi.php

function plugin_openapi() {
 global $endpoints;
 header("Access-Control-Allow-Origin: *");
 // Swagger header
?>swagger: "2.0"
info:
  title: "Scrap.php API"
  description: "This is a generic API for easy webscrapping. (Documentation on https://github.com/rpeyron/scrap2api/)"
  version: "1.0.0"
host: "<?php print($_SERVER['SERVER_NAME']); ?>"
basePath: "<?php print($_SERVER['SCRIPT_NAME']); ?>"
schemes:
- "https"
- "http"
tags:
- name: "scrap"
  description: "Scrapping Ressources"
- name: "tools"
  description: "Tools"
paths:
<?php
  foreach($endpoints as $endpoint) {
    print($endpoint['doc_function']());
  }
}

function plugin_openapi_doc() { return <<<EOT
  /openapi:
    get:
      summary: Get OpenAPI specification
      description: Get OpenAPI specification of this scrap.php API
      tags:
      - "tools"
      produces:
      - "text/plain"
      responses:
        200:
          description: "Successful operation"
          schema:
            type: string
            description: "Swagger file (Yaml)"

EOT;
}
add_endpoint("GET","!/openapi$!", 'plugin_openapi', 'plugin_openapi_doc');

// = plugin-openapi-ui.php

function plugin_openapi_ui() { ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Swagger UI</title>
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,700|Source+Code+Pro:300,600|Titillium+Web:400,600,700" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="https://petstore.swagger.io/swagger-ui.css" >
  <link rel="icon" type="image/png" href="https://petstore.swagger.io/favicon-32x32.png" sizes="32x32" />
  <link rel="icon" type="image/png" href="https://petstore.swagger.io/favicon-16x16.png" sizes="16x16" />
  <style>
    html  {     box-sizing: border-box;       overflow: -moz-scrollbars-vertical;      overflow-y: scroll;     }
    *,    *:before,     *:after     {       box-sizing: inherit;     }
    body {      margin:0;      background: #fafafa;    }
  </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://petstore.swagger.io/swagger-ui-bundle.js"> </script>
<script src="https://petstore.swagger.io/swagger-ui-standalone-preset.js"> </script>
<script>
window.onload = function() {
        // Begin Swagger UI call region
      const ui = SwaggerUIBundle({
        "dom_id": "#swagger-ui",
        deepLinking: true,
        presets: [          SwaggerUIBundle.presets.apis,          SwaggerUIStandalonePreset        ],
        plugins: [          SwaggerUIBundle.plugins.DownloadUrl        ],
        layout: "StandaloneLayout",
        url: "<?php print($_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'] . "/openapi"); ?>",
      })
      // End Swagger UI call region
  window.ui = ui
}
</script>
</body>
</html>
<?php
}

function plugin_openapi_ui_doc() { return <<<EOT
  /openapi-ui:
    get:
      summary: View OpenAPI specification with Swagger UI
      description: View OpenAPI specification with Swagger UI
      tags:
      - "tools"
      produces:
      - "text/plain"
      responses:
        200:
          description: "Successful operation"
          schema:
            type: string
            description: "HTML Swagger UI"

EOT;
}
add_endpoint("GET","!/openapi-ui$!", 'plugin_openapi_ui', 'plugin_openapi_ui_doc');


// = plugin-scrap.php

function plugin_scrap($uri, $matches) {
    global $scrap;
    global $service;
    global $parsers;
    global $postprocessors;
    
    $service = $matches['service'];
    if (array_key_exists($service, $scrap)) {
        $tokens=get($scrap[$service]['tokens']);
        if ($tokens) {
            $token=$matches['token'];
            if (!in_array($token,$tokens)) {
                    http_response_code(401); // Not authorized
                    die("Invalid token");
            }
        }
        
        $ressource = $matches['ressource'];
        $content = "";
        if ($ressource) {
            $url = sprintf($scrap[$service]['url'], $ressource);
            
            // Check cache
            if (get($scrap[$service]['cacheable'])) {
                $content = cache_get($url, get($scrap[$service]['cacheable']));
            }
            
            if (!$content) {
                // If no cache or invalid we get the contents
                $content = file_get_contents($url, false, get($scrap[$service]['context']) );
                $cached = false;
            } else {
                $cached = true;
            }
        
            if ($content) {
                // Parse contents
                $method = get($scrap[$service]['method'],'preg');
                $search = get($scrap[$service]['search']);
                $result = "";
                if (!$search) {
                    http_response_code(400); // Bad request
                    die("No valid search");
                }
                
                if (array_key_exists($method, $parsers)) {
                    $result = $parsers[$method]($search, $content, get($scrap[$service]['flags']));
                } else { // No valid method
                    http_response_code(400); // Bad request
                    die("No valid method");
                }
                
                if (!$result) {
                    http_response_code(404); // Not found
                    die("Ressource not found in contents");
                } else {
                
                    if (get($scrap[$service]['post_search']) != "") {
                        $post_method = get($scrap[$service]['post_method'],'preg_replace');
                        if (array_key_exists($post_method, $postprocessors)) {
                            $result = $postprocessors[$post_method]($result, get($scrap[$service]['post_search']), get($scrap[$service]['post_replace']));
                        } else { // No valid method
                            http_response_code(400); // Bad request
                            die("No valid postprocessing method");
                        }
                    }
                    //header('Content-Type: application/xml; charset=utf-8');
                    //print("<result>" . $result . "</result>");
                    print($result);
                }
                
                // Put in cache
                if ((! $cached) && ($scrap[$service]['cacheable'])) {
                    cache_put($url, $content);
                }
            } else {  // $content is false
                http_response_code(404); // Not found
                die("Ressource page not found");
            }
            

        } else {  // $ressource is empty
            http_response_code(400); // Bad request
            die("Ressource identifier missing");
        }
    } else { // $service not in $scrap
        http_response_code(400); // Bad request
        die("Service undeclared");
    }
}

function plugin_scrap_doc() { 
  global $scrap;
  $doc = "";
  // Swagger services
  foreach($scrap as $service => $def) {
    $doc .= "  /$service/{id}:";
    $doc .= <<<EOT

    get:
      summary: "{$def[doc]}"
      description: "{$def[doc]}"
      produces:
      - "text/plain"
      tags:
      - "scrap"
      parameters:
      - name: "id"
        in: "path"
        description: "The identifier to be used with the ressource"
        required: true
        type: "string"
EOT;

    if ($def['tokens']) $doc .= <<<EOT

      - name: "token"
        in: "query"
        description: "The autorisation token"
        required: true
        type: "string"
EOT;
    $doc .= <<<EOT

      responses:
        200:
          description: "Successful operation : resulting scrapped value"
          schema:
            type: string
            description: "Result"
            example: ""
        404:
          description: "Not found"
        401:
          description: "Not authorized"
        400:
          description: "Bad request"

EOT;
 }
 return($doc);
}

add_endpoint("GET",'$/(?P<service>[^/]*)/(?P<ressource>[^/?&]*)/?(?:[&?]token=(?P<token>[\w\d]*))?$', 'plugin_scrap', 'plugin_scrap_doc');


// = plugin-parser-preg.php

if (function_exists('preg_match')) {

function plugin_parser_preg($search, $content, $flags) {
    global $scrap;
    global $service;
    if (preg_match($search, $content, $matches, get($scrap[$service]['flags']))) {
        return $matches[1];
    } 
    return false;
}
add_parser("preg", 'plugin_parser_preg');

}

// = plugin-post-preg.php

if (function_exists('preg_replace')) {

function plugin_post_preg($content, $search, $replace) {
    return preg_replace($search, $replace, $content);
}
add_postprocessor("preg_replace", 'plugin_post_preg');

}


// = plugin-parser-xpath.php

if (function_exists('simplexml_load_string')) {

function plugin_parser_xpath($search, $content, $flags) {
    try {
        if (!strpos(get($flags),"nohtml")) {
            $dom = new DOMDocument();
            $dom->loadHTML($content);
            $doc = simplexml_import_dom($dom);
        } else {
            $doc = simplexml_load_string($content);
        }
        if ($doc) {
            $result = $doc->xpath($search);
            if ($result) { return implode($result); } 
        }
    } catch (Exception $e) {}
    return false;
}
add_parser("xpath", 'plugin_parser_xpath');

}

// = plugin-parser-css.php

if (class_exists('DOMXpath')) {

// Based on CSS selector from https://github.com/tj/php-selector/blob/master/selector.inc

            // included here to  have single page 

            // --- Selector.inc - (c) Copyright TJ Holowaychuk <tj@vision-media.ca> MIT Licensed
            define('SELECTOR_VERSION', '1.1.6');
            /**
             * SelectorDOM.
             *
             * Persitant object for selecting elements.
             *
             *   $dom = new SelectorDOM($html);
             *   $links = $dom->select('a');
             *   $list_links = $dom->select('ul li a');
             *
             */
            class SelectorDOM {
              public function __construct($data) {
                if ($data instanceof DOMDocument) {
                    $this->xpath = new DOMXpath($data);
                } else {
                    $dom = new DOMDocument();
                    @$dom->loadHTML($data);
                    $this->xpath = new DOMXpath($dom);
                }
              }
              
              public function select($selector, $as_array = true) {
                $elements = $this->xpath->evaluate(selector_to_xpath($selector));
                return $as_array ? elements_to_array($elements) : $elements;
              }
            }
            /**
             * Select elements from $html using the css $selector.
             * When $as_array is true elements and their children will
             * be converted to array's containing the following keys (defaults to true):
             *
             *  - name : element name
             *  - text : element text
             *  - children : array of children elements
             *  - attributes : attributes array
             *
             * Otherwise regular DOMElement's will be returned.
             */
            function select_elements($selector, $html, $as_array = true) {
              $dom = new SelectorDOM($html);
              return $dom->select($selector, $as_array);
            }
            /**
             * Convert $elements to an array.
             */
            function elements_to_array($elements) {
              $array = array();
              for ($i = 0, $length = $elements->length; $i < $length; ++$i)
                if ($elements->item($i)->nodeType == XML_ELEMENT_NODE)
                  array_push($array, element_to_array($elements->item($i)));
              return $array;
            }
            /**
             * Convert $element to an array.
             */
            function element_to_array($element) {
              $array = array(
                'name' => $element->nodeName,
                'attributes' => array(),
                'text' => $element->textContent,
                'children' =>elements_to_array($element->childNodes)
                );
              if ($element->attributes->length)
                foreach($element->attributes as $key => $attr)
                  $array['attributes'][$key] = $attr->value;
              return $array;
            }
            /**
             * Convert $selector into an XPath string.
             */
            function selector_to_xpath($selector) {
                // remove spaces around operators
                $selector = preg_replace('/\s*>\s*/', '>', $selector);
                $selector = preg_replace('/\s*~\s*/', '~', $selector);
                $selector = preg_replace('/\s*\+\s*/', '+', $selector);
                $selector = preg_replace('/\s*,\s*/', ',', $selector);
                $selectors = preg_split('/\s+(?![^\[]+\])/', $selector);
                foreach ($selectors as &$selector) {
                    // ,
                    $selector = preg_replace('/,/', '|descendant-or-self::', $selector);
                    // input:checked, :disabled, etc.
                    $selector = preg_replace('/(.+)?:(checked|disabled|required|autofocus)/', '\1[@\2="\2"]', $selector);
                    // input:autocomplete, :autocomplete
                    $selector = preg_replace('/(.+)?:(autocomplete)/', '\1[@\2="on"]', $selector);
                    // input:button, input:submit, etc.
                    $selector = preg_replace('/:(text|password|checkbox|radio|button|submit|reset|file|hidden|image|datetime|datetime-local|date|month|time|week|number|range|email|url|search|tel|color)/', 'input[@type="\1"]', $selector);
                    // foo[id]
                    $selector = preg_replace('/(\w+)\[([_\w-]+[_\w\d-]*)\]/', '\1[@\2]', $selector);
                    // [id]
                    $selector = preg_replace('/\[([_\w-]+[_\w\d-]*)\]/', '*[@\1]', $selector);
                    // foo[id=foo]
                    $selector = preg_replace('/\[([_\w-]+[_\w\d-]*)=[\'"]?(.*?)[\'"]?\]/', '[@\1="\2"]', $selector);
                    // [id=foo]
                    $selector = preg_replace('/^\[/', '*[', $selector);
                    // div#foo
                    $selector = preg_replace('/([_\w-]+[_\w\d-]*)\#([_\w-]+[_\w\d-]*)/', '\1[@id="\2"]', $selector);
                    // #foo
                    $selector = preg_replace('/\#([_\w-]+[_\w\d-]*)/', '*[@id="\1"]', $selector);
                    // div.foo
                    $selector = preg_replace('/([_\w-]+[_\w\d-]*)\.([_\w-]+[_\w\d-]*)/', '\1[contains(concat(" ",@class," ")," \2 ")]', $selector);
                    // .foo
                    $selector = preg_replace('/\.([_\w-]+[_\w\d-]*)/', '*[contains(concat(" ",@class," ")," \1 ")]', $selector);
                    // div:first-child
                    $selector = preg_replace('/([_\w-]+[_\w\d-]*):first-child/', '*/\1[position()=1]', $selector);
                    // div:last-child
                    $selector = preg_replace('/([_\w-]+[_\w\d-]*):last-child/', '*/\1[position()=last()]', $selector);
                    // :first-child
                    $selector = str_replace(':first-child', '*/*[position()=1]', $selector);
                    // :last-child
                    $selector = str_replace(':last-child', '*/*[position()=last()]', $selector);
                    // :nth-last-child
                    $selector = preg_replace('/:nth-last-child\((\d+)\)/', '[position()=(last() - (\1 - 1))]', $selector);
                    // div:nth-child
                    $selector = preg_replace('/([_\w-]+[_\w\d-]*):nth-child\((\d+)\)/', '*/*[position()=\2 and self::\1]', $selector);
                    // :nth-child
                    $selector = preg_replace('/:nth-child\((\d+)\)/', '*/*[position()=\1]', $selector);
                    // :contains(Foo)
                    $selector = preg_replace('/([_\w-]+[_\w\d-]*):contains\((.*?)\)/', '\1[contains(string(.),"\2")]', $selector);
                    // >
                    $selector = preg_replace('/>/', '/', $selector);
                    // ~
                    $selector = preg_replace('/~/', '/following-sibling::', $selector);
                    // +
                    $selector = preg_replace('/\+([_\w-]+[_\w\d-]*)/', '/following-sibling::\1[position()=1]', $selector);
                    $selector = str_replace(']*', ']', $selector);
                    $selector = str_replace(']/*', ']', $selector);
                }
                // ' '
                $selector = implode('/descendant::', $selectors);
                $selector = 'descendant-or-self::' . $selector;
                // :scope
                $selector = preg_replace('/(((\|)?descendant-or-self::):scope)/', '.\3', $selector);
                // $element
                $sub_selectors = explode(',', $selector);
                foreach ($sub_selectors as $key => $sub_selector) {
                    $parts = explode('$', $sub_selector);
                    $sub_selector = array_shift($parts);
                    if (count($parts) && preg_match_all('/((?:[^\/]*\/?\/?)|$)/', $parts[0], $matches)) {
                        $results = $matches[0];
                        $results[] = str_repeat('/..', count($results) - 2);
                        $sub_selector .= implode('', $results);
                    }
                    $sub_selectors[$key] = $sub_selector;
                }
                $selector = implode(',', $sub_selectors);
                
                return $selector;
            }



function plugin_parser_css($search, $content, $flags) {
    try {
         $result = select_elements($search, $content, false);
         if ($result) { 
            $text = "";
            foreach($result as $node) {
                $text .= $node->nodeValue;
            }
            return $text;
         } 
    } catch (Exception $e) {}
    return false;
}
add_parser("css", 'plugin_parser_css');

}

//  Plugins loading -----------------------------------------------------------

$files = glob('plugin-*.php'); // get all potential plugins
foreach($files as $file){ // iterate files
  if(is_file($file))
    include($file); 
}

//  Main function -----------------------------------------------------------

// Get URI & metho
$uri="";
if (isset($_SERVER['PATH_INFO'])) { $uri = $_SERVER['PATH_INFO']; }
if (isset($_SERVER['QUERY_STRING']) && ($_SERVER['QUERY_STRING'] != "")) {
    if ($uri != "") $uri = $uri . "?";
    $uri = $uri . $_SERVER['QUERY_STRING']; 
}

$method = $_SERVER['REQUEST_METHOD'];

//var_dump($_SERVER);
//print("$method: $uri");

// Handle CORS
header("Access-Control-Allow-Origin: *");

// Handle OPTIONS
if ($method == "OPTIONS") {
    http_response_code(204); 
	die("OK");
}

// Handle HEAD
if ($method == "HEAD") {
	// Process as GET
	$method = "GET";
}

// Search & run endpoints
$found = false;
foreach($endpoints as $endpoint) {
    //var_dump($endpoint);
    if (($endpoint['method'] == $method)  && (preg_match($endpoint['regexp'], $uri, $matches))) {
        //var_dump($matches);
        $endpoint['function']($uri, $matches);
        $found = true;
        break;
    }
}

// If no endpoint found, report bad request
if (!$found) {
    http_response_code(400); // Bad request
    die("Query format error");
}


?>
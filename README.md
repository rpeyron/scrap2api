# scrap2api
A very lightweight solution to access websites via API through webscrapping.

# Installation

Prerequisites : 
- PHP 5.x or later
- a webserver configured to run PHP

Clone this repository or download the file scrap.php in the document folder of you webserver (docroot). That's all !

To enable caching of web contents, you may create a cache/ subfolder, and add the write access rights to your web server (www-data for instance)

Example on debian :
``` 
cd /var/www
wget https://github.com/rpeyron/scrap2api/raw/master/scrap.php
mkdir cache
chgrp www-data cache
chmod g+rw cache
```

# Use

Access to the API with the URL  https://<your server address>/scrap.php/<endpoint>
  
There is default endpoints defined :
- /ping : to check if the scrap.php API is working OK
- /openapi-ui : to see the different services that are configured in a convenient Swagger UI
- /openapi : to get the swagger definition of the API
- /clean-cache : to clean the cache (will delete all files, regardless of time validity)

You may then add your scrapping endpoints by creating a `scrap.conf.php` file and add you new ressources. Note that it must be a php file and thus start with `<?php ` and have a compliant syntax.

Here is a sample defintion to get the number of result of a google search :
```
$scrap["google-numresults"] = [
        'url' => 'https://www.google.com/search?q=%s',
        'cacheable' => 3600,
        'search' => '/id="resultStats">\s*(?P<num>[^<]*)</ms',
        'tokens' => ['test'],
        'post_search' => '/[^\d]/',
        'post_replace' => '',        
        'doc'=> 'Get the number of results of a Google search',
    ];
```
Example URL would be (with 'foo' as searh string) :
'''
https://<yourserver>/scrap.php/google-numresults/foo?token=test
'''
Once defined, you may use it in your programs, or also in Excel / Libreoffice with the formula
`=WEBSERVICE("https://<yourserver>/scrap.php/<endpoint>/<id>?token=<token>")` (Use `=SERVICEWEB` in French)

# Documentation of scrap definition

The pattern of a new endpoint URL is `https://<yourserver>/scrap.php/<endpoint>/<id>?token=<token>`
* `/endpoint` is the name of the ressource you are looking for  (eg : the number of results of a google search)
* `<id>` is the identifier of the ressource item you are looking for  (eg: the words to use with the google search)
* `<token>` is a token to have the right to access to this endpoint, if you have configured one

Basic definition is 
```
$scrap["endpoint"] = [
        'url' => '<url with %s to be replaced by the ressource identifier>',
        'search' => '<regular expression to search the result>',
    ];
```
* `endpoint` : will be the name of the new ressource to get with `https://<yourserver>/scrap.php/<endpoint>/<id>`
* `url` : the URL to be retrieved ; '%s' will be replaced with the <id> provided in the request (sprintf syntax)
* `context` : (optional, default null) context to be used with url
* `tokens` : (optional) array of valid tokens (if none, will be useable without token)
* `cacheable` : (optional) number of seconds of cache validity (none or 0 means no cache)
* `method` : (optional, default 'preg') search method  
   * `preg` : search contents with a preg regular expression (test with https://regex101.com/)
   * `xpath` : search contents with xpath expression
   * `css` : search contents with css-like selectors
* `search` : search string (depending on method : regular expression, xpath expression, css selectors,...)
* `flags` : (optional, default null) flags to be used with the search
   * for xpath : `nohtml` to force valid xml parsing
* `post_method` : (optional, default 'preg_replace') post-processing method
* `post_search` : (optional) search string for post-processing
* `post_replace` : (optional) replace string for post-processing
* `doc` : (optional) documentation string to be used in Swagger

Disclaimer : scrapping is not always allowed by the service provider ; always check terms of service of the website to see if it is allowed. This script cannot be held responsible of improper use.


# Developer's Information

This script is extendable through plugins. All files names `plugin-<x>.php` in the same folder will be included. You may extend :
* basic endpoints (like /ping) : all endpoints define a regular expression that will trigger the endpoint
* parsers : to add more methods to search in contents
* postprocessors : to add more methods to transform the items found

In fact all the defaults endpoints, parsers and postprocessors are written as plugins. They are bundled in the same file to help deployment, but you may easily see how to build a new plugin. Also the CSS selector library (written by TJ Holowaychuk - https://github.com/tj/php-selector/blob/master/selector.inc) is bundled in the scrap.php onefile.

More developer's information in the scrap.php source code.

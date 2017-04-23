<?php
 
// command to run the script in background with logfile logs.txt: php script.php > logs.txt 2>&1&
// command to stop the script: killall php
 
error_reporting(0);
 
$user = ""; //username instagram
$pass = ""; //password instagram
 
$schlampen = []; // <- which bitches do u wanna stalk? example: ["bitch1", "bitch2", "bitch3"]
 
// http://www.hashbangcode.com/blog/netscape-http-cooke-file-parser-php
function extractCookies($string) {
    $cookies = array();
    $lines = explode("\n", $string);
    foreach ($lines as $line) {
        if (isset($line[0]) && substr_count($line, "\t") == 6) {
            $tokens = explode("\t", $line);
            $tokens = array_map('trim', $tokens);
            $cookie = array();
            $cookie['domain'] = $tokens[0];
            $cookie['flag'] = $tokens[1];
            $cookie['path'] = $tokens[2];
            $cookie['secure'] = $tokens[3];
            $cookie['expiration'] = date('Y-m-d h:i:s', $tokens[4]);
            $cookie['name'] = $tokens[5];
            $cookie['value'] = $tokens[6];
            $cookies[] = $cookie;
        }
    }
   
    return $cookies;
}
 
function goodProfile($user, $cookies) {
    $url = "https://i.instagram.com/$user/media/";
    $json = json_decode(file_get_contents($url, false, stream_context_create(array('http'=>array('method'=>"GET", 'header'=>"Accept-language: en\r\n" . "Cookie: $cookies\r\n")))), true);
 
    if($http_response_header[0] == "HTTP/1.1 200 OK") {
        if(empty($json["items"])) {
            echo date("Y-m-d H:i:s")." [$user] User has private profile.\r\n";
            return false;
        }
        return true;
    } else {
        echo date("Y-m-d H:i:s")." [$user] User doesn't exists.\r\n";
        return false;
    }
}
 
function fetchJson($user, $cookies) {
    if(goodProfile($user, $cookies)) {
        if (!is_dir($user)) {
            mkdir($user);
        }
        $max_id = 0;
        do {
            $url = "https://i.instagram.com/$user/media/";
            if($max_id != "0") {
                $url .= "?max_id=$max_id";
            }
            $json = json_decode(file_get_contents($url, false, stream_context_create(array('http'=>array('method'=>"GET", 'header'=>"Accept-language: en\r\n" . "Cookie: $cookies\r\n")))), true);
 
            if($http_response_header[0] == "HTTP/1.1 200 OK") {
                $last_key = key( array_slice( $json["items"], -1, 1, TRUE ) );
                $max_id = $json["items"][$last_key]["id"];
                foreach($json["items"] as $image) {
                    $iId = $image["id"];
                    if(file_exists("$user/$iId.jpg")) {
                        echo date("Y-m-d H:i:s")." [$user] Found old pictures, aborting the operation...\r\n";
                        return;
                    }
                    $iContent = file_get_contents($image["images"]["standard_resolution"]["url"]);
                    file_put_contents("$user/$iId.jpg", $iContent);
                    echo date("Y-m-d H:i:s")." [$user] Download Image ID: $iId\r\n";
                }  
            } else {
                echo date("Y-m-d H:i:s")." [$user] Error...";
                return;
            }
        } while (!empty($json["items"]));
 
        echo date("Y-m-d H:i:s")." [$user] Downloaded all images.\r\n";
    }
}
 
function getCsrfToken() {
    $content = file_get_contents("https://instagram.com");
    preg_match("/csrf_token\": \"(.*)\", \"viewer/", $content, $csrf);
    if(empty($csrf)) {
        preg_match("/csrf_token\": \"(.*)\"},/", $content, $csrf);
        return $csrf[1];
    } else {
        return $csrf[1];
    }
}
 
function login($user, $pass) {
    unlink("cookies.txt");
    $csrf = getCsrfToken();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,"https://www.instagram.com/accounts/login/ajax/");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "username=$user&password=$pass");
    curl_setopt($ch, CURLOPT_COOKIEJAR, "cookies.txt");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Cookie: csrftoken=$csrf",
        "X-CSRFToken: $csrf",
        "Content-Type: application/x-www-form-urlencoded",
        "Referer: https://www.instagram.com/",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64; rv:50.0) Gecko/20100101 Firefox/50.0",
        "Host: www.instagram.com",
        "Accept-Language: de,en-US;q=0.7,en;q=0.3",
        "Accept: */*",
        "X-Requested-With: XMLHttpRequest",
        "X-Instagram-AJAX: 1",
        "Accept-Encoding: gzip, deflate, br"
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 
    $output = curl_exec($ch);
    curl_close ($ch);
 
    $json = json_decode($output, true);
 
    if($json) {
        if($json["status"] == "ok") {
            if($json["authenticated"] == "1") {
                echo date("Y-m-d H:i:s")." Logged in!\r\n";
                return true;
            } else {
                unlink("cookies.txt");
                die(date("Y-m-d H:i:s")." Error, I couldn't log in! Username/Pass may be wrong!\r\n");
                return false;  
            }
        } else {
            if($json["message"] == "checkpoint_required") {
                $checkpoint_url = $json["checkpoint_url"];
                unlink("cookies.txt");
                die(date("Y-m-d H:i:s")." Copy the following link in your browser and allow accsess to the bot: $checkpoint_url\r\n");
            } else {
                $error = $json["message"];
                unlink("cookies.txt");
                die(date("Y-m-d H:i:s")." Error, I couldn't log in!\nError: $error\nTry again!\r\n");
            }
        }
    } else {
        unlink("cookies.txt");
        die(date("Y-m-d H:i:s")." Error, I couldn't log in! Try again!\r\n");
    }
 
    return false;
}
 
function startScrapping($schlampen, $cookies) {
    foreach($schlampen as $schlampe) {
        echo date("Y-m-d H:i:s")." [$schlampe] Fetching all images...\r\n";
        fetchJson($schlampe, $cookies);
    }
    startScrapping($schlampen, $cookies);
}
 
if(login($user, $pass)) {
    console.log("Instagram Scrapper made by Askwrite\n\n");
    $cookies = "";
    foreach(extractCookies(file_get_contents("cookies.txt")) as $cookie) {
        if($cookie["value"] != "") {
            $cookies .= $cookie["name"]."=".$cookie["value"]."; ";
        }
    }
    $cookies = substr($cookies, 0, -2);
    startScrapping($schlampen, $cookies);
}

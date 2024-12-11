<?php
// automatyczne flushowanie przez printa i echo
ob_implicit_flush(true); 
// autoload z composera
require 'vendor/autoload.php';

use HieuLe\WordpressXmlrpcClient\WordpressClient;
use Forensic\FeedParser\Parser;
use voku\helper\HtmlDomParser;
use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;

// parsuje config.ini
$config = parse_ini_file("config.ini");
$GCapikey = $config["api_key"]; // Google Cloud API
$GMPrompt = $config["prompt"]; // Gemini Prompt
$WPlink = $config["wplocation"]; // Adres WordPressa
$WPxmlrpc = $config["xmlrpc"]; // WordPress XML-RPC
$WPuser = $config["username"]; // WordPress Username
$WPpass = $config["password"]; // WordPress Password
$RSSfeed = $config["rssfeed"]; // RSS Feed

// input z htmla z zabezpieczeniem przed XSS (slabym ale lepszy od niczego)
$prompt = strip_tags($_POST['temat']);
$slowaklucz = strip_tags($_POST['slowaklucz']);
function print_withpad($message) {
    // Funkcja print z padowaniem i line breakiem, aby przeglądarka nie używała cachu.
    // przyjmuje tekst w stringu
    // nic nie zwraca
    print(str_pad($message.'<br>',4096));
}

function scrape_sites($prompt) {
    // łączy funkcje scrape_google() i scrape_site() w jedną funkcje
    // funkcja przyjmuje strina z tematem
    // funkcja zwraca:
    // array {
    //  [(int)index] => array {
    //        "content" => (str)"html strony"
    //        "site" => (str)"link do strony
    //      }
    //  [(int)index] => array {
    //  ... 
    // }
    $sites = [];
    $c = 0;
    $scraped = scrape_google($prompt);
    foreach ($scraped as $data) {
        $content = scrape_site($data["link"]);
        $res = [
            "content" => $content,
            "site" => $data["link"]
        ];
        $sites += [ $c => $res ];
        $c++;
    }
    return $sites;
}


function scrape_site($link) {
    // scrapuje strone używając firefox readability
    $readability = new Readability(new Configuration());
    $options  = array('http' => array('user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'));
    $context  = stream_context_create($options);
    $html = file_get_contents($link, false, $context);
    try {
        $readability->parse($html);
    } catch (ParseException $e) {
        echo sprintf('Error processing text: %s', $e->getMessage());
    }
    $content = preg_replace('/<\/?(img|a)([^>]*?)>/i', '', $readability->getContent());
    return $content;
}


function scrape_google($prompt) {
    // scrapuje google z podanym tematem
    // przymuje stringa z tematem
    // zwraca:
    // array {
    //  [(int)index] => array {
    //        "title" => (str)"tytuł strony"
    //        "link" => (str)"link do strony"
    //        "snippet" => (str)"krótka zawartość strony"
    //        "pos" => (int)"pozycja strony"
    //      }
    //  [(int)index] => array {
    //  ... 
    // }
    $url = "https://www.google.com/search?q=".urlencode($prompt)."&gl=us&hl=en";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);

    $dom = new HtmlDomParser();
    $dom->load($html);

    $results = $dom->find("div.g");

    $data = [];
    $c = 0;
    foreach ($results as $result) {
        // bierze dane z strony
        $title = $result->find("h3", 0)->plaintext;
        $link = $result->find(".yuRUbf > div > span > a", 0)->href;
        $snippet = $result->find(".VwiC3b", 0)->plaintext;
        $pos = $c+1;
        if ($title == "" || $link == "" || $snippet == "") {
            print_withpad("Scraper google zwrócił błędne dane, oznacza to że mappingi prawdopodobnie są złe, lub google zrobił rate limit na IP.\n");
            print_withpad($title."\n");
            print_withpad($link."\n");
            print_withpad($snippet."\n");
        } 
        $res = [
            "title" => $title,
            "link" => $link,
            "snippet" => $snippet,
            "pos" => $pos,
        ];
        $data += [ $c => $res ];
        $c++;
    }
    return $data;
}

function generate_image($prompt, $width = 600, $height = 400, $model = "flux") {
    // tworzy zdjęcie przez API pollinations
    // przyjmuje string temat, int szerokość w px, int wysokość w px, i string modelu (dostępne modele: https://image.pollinations.ai/models)  
    $seed = random_int(0, 10000);
    $prompt = urlencode($prompt);
    $url = "https://pollinations.ai/p/$prompt?width=$width&height=$height&seed=$seed&model=$model&private=true&nologo=true";
    $result = file_get_contents($url);
    return $result;
}

function generate_text($prompt) {
    // generuje tekst przez api gemini, potrzebny jest extension curl do php.
    // przyjmuje temat w stringu
    // zwraca klase:
    // object {
    //    ["content"] => (str)"tekst wygenerowany przez gemini"
    //    ["placeholder_image_titles"] => (str)"nazwy zdjęć wygenerowane przez gemini, powinny być oddzielone ', ' i zawierają krótkie opisy zdjęć do tematu"
    //    ["post_title"] => (str)"wygenerowany tytuł postu"
    //    ["tags"] => (str)"wygenerowane tagi do postu, podobnie jak placeholder_image_titles powinny być oddzielone ', ' ale nigdy nie wiadomo co AI wymyśli"
    // }
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$GLOBALS['GCapikey']}";
    $postData = json_encode([
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 1,
            "topK" => 40,
            "topP" => 0.95,
            "maxOutputTokens" => 135000,
            "responseMimeType" => "application/json",
            "responseSchema" => [
                "type" => "object",
                "properties" => [
                    "post_title" => [
                        "type" => "string"
                    ],
                    "tags" => [
                        "type" => "string"
                    ],
                    "content" => [
                        "type" => "string"
                    ],
                    "placeholder_images_titles" => [
                        "type" => "string"
                    ]
                ]
            ]
        ]
    ]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    $responseData = json_decode($response);
    $text = json_decode($responseData->candidates[0]->content->parts[0]->text); // o boże
    return $text;
}

function upload_wordpress_media($image, $name) {
    // uploaduje plik do serweru wordpress
    // przyjmuje dane zdjęcia w jpegu i nazwa pliku w stringu
    // zwraca int attachment ID
    $wpClient = new WordpressClient();
    $wpClient->setCredentials($GLOBALS['WPxmlrpc'], $GLOBALS['WPuser'], $GLOBALS['WPpass']);
    $uploadMedia = $wpClient->uploadFile("$name.jpg", "image/jpeg", $image, false);
    $attachment_id = $uploadMedia['attachment_id'];
    return $attachment_id;
}

function get_wordpress_media_url($attachment_id) {
    // bierze link do pliku z attachment ID z serwera wordpress
    // przyjmuje attachment ID
    // zwraca link w stringu
    $wpClient = new WordpressClient();
    $wpClient->setCredentials($GLOBALS['WPxmlrpc'], $GLOBALS['WPuser'], $GLOBALS['WPpass']);
    return $wpClient->getMediaItem($attachment_id)['link'];
}

function create_wordpress_post($title, $content, $tags, $thumbnail_aID) {
    // tworzy post na serwerze wordpress
    // przyjmuje tytuł w stringu, zawartość postu, tagi, i attachment ID zdjęcia do postu w int
    // zwraca ID postu w int  
    $wpClient = new WordpressClient();
    $wpClient->setCredentials($GLOBALS['WPxmlrpc'], $GLOBALS['WPuser'], $GLOBALS['WPpass']);
    $parameters = [
        'post_type' => 'post',
        'post_status' => 'draft',
        'post_thumbnail' => $thumbnail_aID,
        'terms_names' => [
            'post_tag' => $tags,
        ],
    ];
    $post_id = $wpClient->newPost($title, $content, $parameters);
    return $post_id;
}

function get_feed($RSSfeed) {
    // bierze feed RSS
    // przyjmuje link do feedu RSS
    // zwraca posty z podanego feedu 
    $parser = new Parser();
    $feed = $parser->parseFromURL($RSSfeed);
    $items = $feed->items;
    return $items;
}

function get_rand_post($items) {
    // bierze losowy post z feedu RSS
    // przyjmuje posty z feedu
    // zwraca losowy pojedynczy post
    $rand_keys = array_rand($items);
    return $items[(int)$rand_keys];
}

function main($prompt, $slowaklucz = NULL) {
    // glowna funkcja
    // przyjmuje stringa tematu
    // nic nie zwraca
    $GMPrompt = $GLOBALS['GMPrompt'];
    $WPlink = $GLOBALS['WPlink'];
    /* Usun komentarz aby uzyc feedu RSS
    $RSSfeed = $GLOBALS['RSSfeed'];
    print_withpad("Pobieranie feedu RSS $RSSfeed..\n");
    $feed = get_feed($RSSfeed);
    $file = "titles.txt";
    $content = file($file);
    $tries = 0;
    // sprawdza czy post sie nie powtarza
    while (true) {
        $post = get_rand_post($feed);
        $post_title = $post->title;
        if ($tries >= 15) {
            print_withpad("Nie znaleziono żadnych nowych postów. \n");
            die();
        } elseif (in_array("$post_title\r\n", $content)) {
            print_withpad("Pomijanie duplikatu: $post_title \n");
            $tries += 1;
        } else {
            print_withpad("Wybrano $post_title\n");
            $content[] = $post_title . PHP_EOL;
            file_put_contents($file, implode('', $content));
            break;
        }
    }
    $site_data = $post->textContent;
    */
    print_withpad("Scrapowanie stron..\n");
    $data = scrape_sites($prompt);
    $c = 1;
    $site_data = "";
    print_withpad("Sprawdzanie zawartości stron..\n");
    foreach($data as $site) {
        if($c <= 3) {
            $content = $site["content"];
            if ($content) {
                print_withpad("Strona ".$site["site"]." ma zawartość.");
                $site_data = $content;
                $c++;
            }
            else {
                print_withpad("Strona ". $site["site"]. " nie zawiera żadnego tekstu! Pomijanie..");
            }
        }
    }
    print_withpad("Generowanie tekstu..\n");

    $GMPrompt .= "Dodaj nazwy zdjęć pasujące do paragrafu, wstaw taga zdjęcia HTML prowadzącego do '{placeholder}' w jego miejsce. $site_data . Pamiętaj aby wstawić tagi HTML prowadzącego do '{placeholder}' do paragrafów.";
    if ($slowaklucz) {
        print_withpad("Słowa kluczowe: $slowaklucz\n");
        $GMPrompt .= " Słowa kluczowe do zawartości postu: $slowaklucz. Dodaj je do tagów i do tekstu w sposób naturalny."; 
    }
    $geminijson = generate_text($GMPrompt);
    $geminicontent = $geminijson->content;
    $geminititle = $geminijson->post_title;
    $geminitags = explode(', ', $geminijson->tags);
    $geminiplaceholders = explode(', ', $geminijson->placeholder_images_titles);
    // sprawdza czy gemini cos zwrocil
    if (!isset($geminicontent)) {
        print_withpad("API Gemini zwrócił błędne dane! Spróbuj ponownie później lub sprawdź limity, albo zwiększ limit tokenów.");
        var_dump($geminicontent);
        die();
    }

    print_withpad("Generowanie zdjęcia..\n");
    $thumbnail = generate_image($geminititle);

    print_withpad("Generowanie i przesyłanie zdjęć placeholder..\n");

    $placeholder_ids = [];
    foreach($geminiplaceholders as $placeholder) {
        print_withpad("Generowanie $placeholder...");
        $placeholderimg = generate_image($placeholder);
        print_withpad("Pzesyłanie $placeholder...");
        array_push($placeholder_ids, upload_wordpress_media($placeholderimg, $placeholder));
    }

    print_withpad("Wstawianie zdjęć placeholder..\n");

    foreach($placeholder_ids as $plid) {
        $str = "{placeholder}";
        $link = get_wordpress_media_url($plid);
        $pos = strpos($geminicontent, $str); 
        if ($pos !== false) {
            $geminicontent = substr_replace($geminicontent, $link, $pos, strlen($str));
        }
        else {
            print_withpad("Nie znaleziono taga placeholder dla zdjęcia, Gemini nie wstawił tak jak mu powiedzono. \n");
            print_withpad("Zdjęcie placeholder powinno być przesłane, ale nie będzie wstawione w tekst. \n");
        }
    }

    print_withpad("Przesyłanie zdjęcia..\n");
    $attachment_id = upload_wordpress_media($thumbnail, $geminititle);

    print_withpad("Tworzenie posta..\n");
    $post_id = create_wordpress_post($geminititle, $geminicontent, $geminitags, $attachment_id);

    print_withpad("Gotowe! Stworzono posta o nazwie \"$geminititle\", $WPlink/wp-admin/post.php?post=$post_id&action=edit");
} 
if ($prompt) {
    if ($slowaklucz) {
        main($prompt, $slowaklucz);
    }
    else {
        main($prompt);
    }
}
else {
    print_withpad("Nie podano żadnego tematu!");
    die();
}

?>
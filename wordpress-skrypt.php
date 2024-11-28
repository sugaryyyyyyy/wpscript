<?php
ob_start();
//include composer autoload.php
require 'vendor/autoload.php';

//use the project's Parser module
use HieuLe\WordpressXmlrpcClient\WordpressClient;
use Forensic\FeedParser\Parser;

# parsuje config.ini
$config = parse_ini_file("config.ini");
$GCapikey = $config["api_key"]; // Google Cloud API
$GMPrompt = $config["prompt"]; // Gemini Prompt
$WPlink = $config["wplocation"]; // Adres WordPressa
$WPxmlrpc = $config["xmlrpc"]; // WordPress XML-RPC
$WPuser = $config["username"]; // WordPress Username
$WPpass = $config["password"]; // WordPress Password
$RSSfeed = $config["rssfeed"]; // RSS Feed

function generate_image($prompt, $width = 600, $height = 400, $model = "flux") {
    // tworzy zdjęcie przez API pollinations, zwraca zdjęcie w
    $seed = random_int(0, 10000);
    $prompt = urlencode($prompt);
    $url = "https://pollinations.ai/p/$prompt?width=$width&height=$height&seed=$seed&model=$model&private=true&nologo=true";
    $result = file_get_contents($url);
    return $result;
}

function generate_text($prompt) {
    // generuje tekst przez api gemini, potrzebny jest extension curl do php.
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
            "maxOutputTokens" => 8192,
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
    $wpClient = new WordpressClient();
    $wpClient->setCredentials($GLOBALS['WPxmlrpc'], $GLOBALS['WPuser'], $GLOBALS['WPpass']);
    $uploadMedia = $wpClient->uploadFile("$name.jpg", "image/jpeg", $image, false);
    $attachment_id = $uploadMedia['attachment_id'];
    return $attachment_id;
}

function create_wordpress_post($title, $content, $tags, $attachment_id = null) {
    $wpClient = new WordpressClient();
    $wpClient->setCredentials($GLOBALS['WPxmlrpc'], $GLOBALS['WPuser'], $GLOBALS['WPpass']);
    $parameters = [
        'post_type' => 'post',
        'post_status' => 'draft',
        'post_thumbnail' => $attachment_id,
        'terms_names' => [
            'post_tag' => $tags,
        ],
    ];
    $post_id = $wpClient->newPost($title, $content, $parameters);
    return $post_id;
}

function get_feed($RSSfeed) {
    // bierze feed RSS
    $parser = new Parser();
    $feed = $parser->parseFromURL($RSSfeed);
    $items = $feed->items;
    return $items;
}

function get_rand_post($items) {
    $rand_keys = array_rand($items);
    return $items[(int)$rand_keys];
}

echo "Pobieranie feedu RSS $RSSfeed..\n";
flush();
ob_flush();

$feed = get_feed($RSSfeed);
$file = "titles.txt";
$content = file($file);
$tries = 0;

while (true) {
    $post = get_rand_post($feed);
    $post_title = $post->title;
    if ($tries >= 15) {
        print("Nie znaleziono żadnych nowych postów. \n");
        die();
    } elseif (in_array("$post_title\r\n", $content)) {
        print("Pomijanie duplikatu: $post_title \n");
        $tries += 1;
    } else {
        echo "Wybrano $post_title\n";
        $content[] = $post_title . PHP_EOL;
        file_put_contents($file, implode('', $content));
        break;
    }
}
$post_content = $post->textContent;

echo "Generowanie tekstu..\n";
flush();
ob_flush();
$GMPrompt .= " $post_content";
$geminijson = generate_text("Przetłumacz z Angielskiego na Polski post na blog o temacie suplementów diety. Napisz post w markdownie używając HTMLa dzieląc post na paragrafy i nagłówki, aby był długi. Nie pisz tematu postu w zawartości postu. Dodaj zdjęcia placeholder, w sposób naturalny 'https://placehold.co/600x400'. Napisz go w język przystępny dla SEO i dodaj backlinki w sposób naturalny do strony 'example.com' ale nie pisz ich za dużo, napisz bez żadnych ostrzeżen i komentarzy: $post_content");

$geminicontent = $geminijson->content;
$geminititle = $geminijson->post_title;
$geminitags = explode(', ', $geminijson->tags);

if (!isset($geminicontent)) {
    print("API Gemini zwrócił błędne dane! Spróbuj ponownie później lub sprawdź limity.\n");
    die();
}

echo "Generowanie zdjęcia..\n";
flush();
ob_flush();

$thumbnail = generate_image($geminititle);

echo "Przesyłanie zdjęcia..\n";
flush();
ob_flush();

$attachment_id = upload_wordpress_media($thumbnail, $geminititle);

echo "Tworzenie posta..\n";
flush();
ob_flush();

$post_id = create_wordpress_post($geminititle, $geminicontent, $geminitags, $attachment_id);

echo "Gotowe! Stworzono posta o nazwie \"$geminititle\", $WPlink/wp-admin/post.php?post=$post_id&action=edit";
flush();
ob_flush();
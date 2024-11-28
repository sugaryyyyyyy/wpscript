import feedparser
import google.generativeai as genai
from google.ai.generativelanguage_v1beta.types import content
from wordpress_xmlrpc import Client, WordPressPost
from wordpress_xmlrpc.methods.posts import NewPost
from wordpress_xmlrpc.methods import media
from wordpress_xmlrpc.compat import xmlrpc_client
import json
import random
import configparser
import requests

def generate_image(prompt, width=600, height=400, seed=random.randint(1,10000), model="flux"):
    # Generuje zdjęcie na podstawie podanego promptu. Zwraca zdjęcie encodowane w base64.
    response = requests.get(f"https://pollinations.ai/p/{prompt}?width={width}&height={height}&seed={seed}&model={model}&private=true&nologo=true").content
    return response

def generate_text(prompt):
    # Generuje tekst na podstawie podanego promptu. Zwraca jako json z tokenami: "content", "post_title", "tags"
    genai.configure(api_key=gemini_apikey)

    generation_config = {
        "temperature": 1,
        "top_p": 0.95,
        "top_k": 40,
        "max_output_tokens": 10000,
        "response_schema": content.Schema(
            type=content.Type.OBJECT,
            properties={
                "post_title": content.Schema(
                    type=content.Type.STRING,
                ),
                "tags": content.Schema(
                    type=content.Type.STRING,
                ),
                "content": content.Schema(
                    type=content.Type.STRING,
                ),
            },
        ),
        "response_mime_type": "application/json",
    }

    model = genai.GenerativeModel(
        "gemini-1.5-flash", generation_config=generation_config
    )

    response = model.generate_content(prompt)
    return response.text


'''
#------ FOR TESTING ------#
def generate_text(prompt):
    with open('data.json', encoding="utf-8") as file:
        data = file.read()
    
    return data
#-------------------------#
'''


def create_wordpress_post(title, content, tags, attachment_id):
    # Tworzy nowy post na WordPress. Przyjmuje zdjęcie do thumbnaila jako base64.
    wp = Client(wordpressurl, wordpressuser, wordpresspw)
    post = WordPressPost()
    post.title = title
    post.content = content
    post.post_status = "draft"
    post.thumbnail = attachment_id
    post.terms_names = {"post_tag": tags}
    wp.call(NewPost(post))

def upload_wordpress_image(image, name):
    # Uploaduje zdjęcie do WordPressa, zwraca ID wysłanego zdjęcia.
    wp = Client(wordpressurl, wordpressuser, wordpresspw)
    
    data = {
        "name": f"{name}.jpg",
        "type": "image/jpeg",
    }
    data['bits'] = xmlrpc_client.Binary(image)

    response = wp.call(media.UploadFile(data))
    return response['id']

# Configparser
config = configparser.ConfigParser()
config.read('config.ini')

# Konfiguracja
feed_url = config["RSS"]["rssfeed"]

wordpressurl = config["WORDPRESS"]["xmlrpc"]
wordpressuser = config["WORDPRESS"]["username"]
wordpresspw = config["WORDPRESS"]["password"]

gemini_apikey = config["GEMINI"]["api_key"]

# Pobranie wpisu z feedu
print("Pobieranie wpisu z feedu RSS")
feed = feedparser.parse(feed_url)
try:
    with open("titles.txt", "r+", encoding="utf-8") as file:
        titles = file.read()
        if len(titles.splitlines()) >= len(feed.entries):
            print("Nie znaleziono źadnych postów.")
            exit(1)
        while True:
            entry = random.choice(feed.entries)
            if entry.title not in titles:
                print(f"Wybrano: {entry.title}")
                file.write(f"{entry.title}\n")
                break
            else:
               print(f"Pomijanie duplikatu: {entry.title}")
except FileNotFoundError:
    print("Plik titles.txt nie istnieje, tworzenie go.")
    entry = random.choice(feed.entries)
    with open("titles.txt", "a", encoding="utf-8") as file:
        file.write(f"{entry.title}\n")
        print(f"Wybrano: {entry.title}")


# Tłumaczenie i modyfikacja za pomocą Gemini
print("Generowanie posta.")
prompt = f"Przetłumacz z Angielskiego na Polski post na blog o temacie suplementów diety. Napisz post w markdownie używając HTMLa dzieląc post na paragrafy i nagłówki, aby był długi. Nie pisz tematu postu w zawartości postu. Pamiętaj aby nie dodawaj zdjęć do postu, nawet jeżeli są w podanym dalej poście, zamiast tego użyj placeholdera 'https://placehold.co/600x400'. Napisz go w język przystępny dla SEO i dodaj backlinki w sposób naturalny do strony example.com ale nie pisz ich za dużo, napisz bez żadnych ostrzeżen i komentarzy: {entry.summary}"
modified_content = generate_text(prompt)

# Parsowanie JSONa od Gemini
r_json = json.loads(modified_content)
jsoncontent = r_json["content"]
jsontitle = r_json["post_title"]
jsontags = r_json["tags"].split(", ")

print("Tworzenie zdjęcia do postu.")
image = generate_image(jsontitle)

print("Przesyłanie zdjęcia")
imgID = upload_wordpress_image(image, jsontitle)

print("Tworzenie posta.")
create_wordpress_post(jsontitle, jsoncontent, jsontags, imgID)
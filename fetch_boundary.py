import urllib.request
import json

url = "https://overpass-api.de/api/interpreter?data=%5Bout%3Ajson%5D%3Brelation%282885744%29%3Bout%20geom%3B"
req = urllib.request.Request(url, headers={
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept': 'application/json'
})
try:
    with urllib.request.urlopen(req) as response:
        res_data = response.read().decode('utf-8')
        res_json = json.loads(res_data)
        
        with open("raw_relation.json", "w", encoding="utf-8") as out:
            json.dump(res_json, out, indent=2)
        print("Successfully fetched relation and saved to raw_relation.json")
except Exception as e:
    print(f"Error fetching: {e}")

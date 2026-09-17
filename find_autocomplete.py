with open("resources/views/home.blade.php", "r", encoding="utf-8") as f:
    for idx, line in enumerate(f):
        if "autocomplete" in line.lower():
            print(f"Line {idx+1}: {line.strip()[:150]}")

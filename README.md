<h1 align="center">Sinhala Subtitle Search & Download API 🎬</h1>

<p align="center">
  <strong>A lightweight PHP API to search and download Sinhala subtitles from popular subtitle websites.</strong>
</p>

---
</br>

## ✨ Features

* **🔍 Smart Search:** Search Sinhala subtitles by movie or TV show name.
* **🌐 Multi-Site Support:** Works with **Baiscope.lk**, **Cineru.lk**, **PirateLK**, and **Zoom.lk**.
* **⚡ Fast Responses:** Clean JSON responses, optimized for bots and apps.
* **⬇️ Direct Downloads:** Extracts real subtitle download links (including official portals).
* **🧩 Flexible Filtering:** Choose a specific site or search all at once.
* **🛠 No Database:** Pure PHP scraping — simple and lightweight.
* **🔓 CORS Enabled:** Ready for web apps, bots, and extensions.

</br>

## 🌍 Supported Sites

* `baiscope`
* `cineru`
* `piratelk`
* `zoom`
* `all` (default)

</br>

## 🔗 API Endpoints

### Root (`/`)

Returns API info and available endpoints.

```json
{
  "author": "TheCHARITH (Charith Pramodya Senananayake)",
  "api": "Sinhala Subtitle Search API",
  "endpoints": [
    "/search?query=oppenheimer&site=all",
    "/search?query=deadpool&site=baiscope",
    "/download?url=https://www.baiscope.lk/..."
  ],
  "sites": ["baiscope", "cineru", "piratelk", "zoom"]
}
````

</br>

### 🔍 Search (`/search`)

**Parameters**

* `query` (required) → Movie / TV series name
* `site` (optional) → `all`, `baiscope`, `cineru`, `piratelk`, `zoom`

**Example**

```json
{
  "author": "TheCHARITH (Charith Pramodya Senananayake)",
  "query": "arcane",
  "sites_searched": ["baiscope", "cineru", "piratelk", "zoom"],
  "results": {
    "baiscope": [
      {
        "title": "Arcane S02 E01",
        "url": "https://www.baiscope.lk/arcane-s02-e01-sinhala-subtitles/"
      }
    ]
  }
}
```

</br>

### ⬇️ Download (`/download`)

**Parameters**

* `url` (required) → Full subtitle page URL

**Example**

```json
{
  "author": "TheCHARITH (Charith Pramodya Senananayake)",
  "success": true,
  "page_url": "https://www.baiscope.lk/...",
  "download_url": "https://baiscopedownloads.link/..."
}
```

</br>

## 🚀 Deployment

1. Upload the PHP file to any **PHP-enabled server**.
2. Recommended PHP version: **7.4+**
3. Make sure these extensions are enabled:

   * `curl`
   * `DOM`

No database setup required.

</br>

## ⚠️ Notes

* Website structures may change — scrapers are updated for **January 2026** layouts.
* Baiscope.lk uses official redirect portals (`baiscopedownloads.link`, `.xyz`).
* Use responsibly and respect source website terms.

</br>

## 🤝 Contributing

Pull requests and improvements are welcome.

```bash
git clone https://github.com/TheCHARIITH/sinhala-subtitle-api.git
cd sinhala-subtitle-api
# make changes
# commit and open a PR
```

</br>

## 📄 License

MIT © [Charith Pramodya](https://github.com/TheCHARITH)

</br>

---

<div align="center">

<strong>Made with 💜 and too much ☕ by <a href="https://github.com/TheCHARITH">TheCHARITH</a></strong>

</div>

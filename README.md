# Anime-Loads Universal Downloader 🎬📦

![Hero Banner](screenshots/hero_banner.jpg)

A fully automated, self-hosted Anime and Movie downloader for [Anime-Loads](https://www.anime-loads.org/), packed into a portable, easy-to-use Docker container. 

This project is a complete modern rewrite of the original bare-metal Python CLI scripts, bringing a premium Web UI, an interactive Setup Wizard, automated smart-moving, and Plex integration into a single containerized solution!

---

## 📸 Interface Showcase

**The Modern Dashboard** — Monitor your active downloads, see disk usage, and manage your JDownloader queue directly from the WebUI.
![Dashboard Top](screenshots/dashboard.png)

**Interactive Setup Wizard** — A simple 4-step wizard guides you through configuring your paths, JDownloader credentials, and Plex integrations without ever touching a config file.
![Setup Wizard](screenshots/wizard.png)

**Settings & Validation** — Tweak your setup on the fly with live credential validation for JDownloader and Plex Webhooks.
![Settings](screenshots/settings.png)

**Smart Folder Manager** — Easily move finished extractions from your staging drives to your final long-term storage or USB drives with a single click.
![Folder Manager](screenshots/folder_manager.png)

---

## ✨ Key Features

- **Universal Docker Container**: Everything you need (PHP-FPM, Nginx, Python 3, Selenium/Geckodriver, Cron) is baked into a single image. No complex multi-container setup or host dependencies!
- **Beautiful Web Interface**: A premium, responsive dashboard built with Bootstrap 5, featuring dark mode, quick-add trending anime, and disk monitors.
- **Overseerr / Jellyseerr Auto-Sync**: Automatically poll your Overseerr or Jellyseerr media request server and queue newly approved anime requests straight into Anime-Loads with persistent request ID tracking.
- **Automated Watchlist Supervision**:
  - Add an anime URL to your monitored list.
  - The in-container `anibot.py` daemon automatically checks for new episodes in the background and pushes links to JDownloader.
- **Smart Auto-Mover**: Once JDownloader finishes extracting a file, the built-in `auto_mover.py` detects it and transfers it cleanly to your final media storage without leaving temporary archives behind.
- **Plex Integration**: Automatically triggers a Plex Library scan via Webhook as soon as new episodes are moved to your final storage.
- **System Diagnostics Suite**: Built-in 7-point health check (`/test.php`) verifying filesystem permissions, configuration files, background daemons, JDownloader API, Plex webhook, Overseerr connectivity, and Selenium scraping engine.
- **Folder Manager**: Built-in tool to archive and organize your finished series across multiple disks or USB drives.
- **Security Hardened**: Built-in Nginx rules denying public access to `/config`, state files, dotfiles, scripts, and logs.

---

## 🚀 Quickstart Guide

Deploying the container is incredibly simple. All configuration is done through the WebUI on your first visit!

### Using Docker Run

```bash
docker run -d \
  --name anime-loads \
  -p 8080:80 \
  -v /path/to/your/appdata/anime-loads:/config \
  -v /path/to/downloads:/downloads \
  -v /path/to/final/media:/video \
  anime-loads-universal:latest
```

### Using Docker Compose

```yaml
services:
  anime-loads:
    image: anime-loads-universal:latest
    container_name: anime-loads
    ports:
      - "8080:80"
    volumes:
      - ./config:/config           # Configuration, database, and queues
      - /mnt/downloads:/downloads  # Where JDownloader downloads/extracts
      - /mnt/media:/video          # Your final Plex media storage
    restart: unless-stopped
```

Once the container is running, open your browser and navigate to:
`http://localhost:8080`

You will automatically be redirected to the **Setup Wizard** to complete your installation!

---

## ⚙️ How It Works (Architecture)

1. **Frontend (PHP 8 / Nginx)**: Serves the WebUI, Dashboard, Settings, Folder Manager, and Diagnostics Suite. Manages requests, state files, and sequential queues in `/config/`.
2. **Watchlist Daemon (`anibot.py`)**: Runs continuously in the background under supervisor management. Reads `/config/ani.json` and automatically triggers headless Firefox scraping when new episodes air on Anime-Loads.
3. **Overseerr Sync (`parseRequestedAnimeMoviesAndSeries.py`)**: Runs every 15 minutes to pull open anime requests from your Overseerr instance and append them sequentially to `/config/queue.txt`.
4. **Auto-Mover (`auto_mover.py`)**: Runs every 5 minutes via cron to scan your extraction staging directory, safely moves completed anime to your final storage, and pings your Plex server to trigger a library refresh.

---

## 🛠️ Requirements & Notes

- **JDownloader**: You must have an active instance of JDownloader connected to your [MyJDownloader](https://my.jdownloader.org/) account. The container sends links remotely to your JDownloader instance.
- **Storage Volumes**: You must map your download directories into the container so the WebUI and Auto-Mover can see and move the files.
- **Diagnostics**: Visit `/test.php` or click "Diagnostics" on the dashboard to test all services, engines, and credentials.


# Anime-Loads Universal Downloader - Agent Knowledge Base

Welcome! If you are an AI agent working on this repository, please review this guide to understand the current architecture, conventions, and constraints of the project.

## 🏗 Architecture Overview
This project has been heavily refactored from a multi-container, bare-metal Python CLI approach into a **Single Universal Docker Container**.
The container bundles:
- **Nginx & PHP-FPM**: Serving the web UI (Dashboard, Settings, Setup Wizard).
- **Python 3**: Running the backend automation scripts (`download_anime.py`, `auto_mover.py`, `animeloads.py`).
- **Selenium & Geckodriver**: For scraping the Anime-Loads website.
- **Cron**: For scheduling automated checks.

**Important Rule**: Do not try to split the project back into multiple containers (e.g., separate UI and Bot containers). All components execute within the same environment (`/usr/src/app`).

## 📁 Key File Structures & State Management
- `www/`: Contains all the PHP source code for the Web UI.
  - `index.php`: The main dashboard.
  - `setup.php`: The initial setup wizard.
  - `settings.php`: Post-setup configuration.
  - `folder_manager.php`: UI for managing completed downloads.
- `ani.json`: The central state database shared between the PHP frontend and the Python backend. It holds the active queue, settings, and download status.
- `config.json`: The dynamic path mapping configuration. **Never hardcode paths.**

## 🚫 Pathing Constraints & Best Practices
- **No Hardcoded NAS Paths**: Historically, this project contained hardcoded Synology NAS paths (like `/volume1/video` or `/volumeUSB10`). These have been entirely eradicated.
- **Dynamic Paths Only**: Always read paths from the user's `config.json` (which they set up via the Web UI wizard). 
- Expected Container Mounts (from docker run/compose):
  - `/config`: Configuration and state files (`config.json`, `ani.json`).
  - `/downloads`: The staging directory (where JDownloader downloads).
  - `/video`: The final destination directory (where the auto-mover moves files).

## 🔄 Inter-Process Communication
Because the WebUI and the Bot now live in the same container, the PHP scripts use direct `shell_exec` calls to run Python scripts (e.g., `python3 jd_backend.py status`).
**Do not use `docker exec`** inside the PHP code to trigger backend scripts. 

## 🖼 Generating Screenshots
If you need to generate new screenshots for the `README.md`:
1. Use the `screenshot-bot/screenshot.js` Puppeteer script.
2. The script runs on the host and visits `http://localhost:8080/index.php?user=admin&pass=admin` to bypass login.
3. It uses `fullPage: true` to capture the entire height of the dashboard without sticky header overlapping issues.
4. Ensure the container has a populated `config.json` and `ani.json` before running the script, otherwise all pages will redirect to the setup wizard.
5. You can overwrite `jd_backend.py` with a mock JSON output to simulate an active JDownloader remote state for the dashboard.

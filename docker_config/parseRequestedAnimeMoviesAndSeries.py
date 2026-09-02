#!/usr/bin/env python3
import json
import os
import re
import sys
import time
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError

CONFIG_FILE = "/config/config.json"
QUEUE_FILE = "/config/queue.txt"
REQUEST_LOG_FILE = "/config/requestlog.txt"
ANI_FILE = "/config/ani.json"
SYNCED_FILE = "/config/overseerr_synced.json"


def load_config():
    if os.path.isfile(CONFIG_FILE):
        try:
            with open(CONFIG_FILE, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception as e:
            print(f"Error reading {CONFIG_FILE}: {e}")
    return {}


def load_synced_state():
    if os.path.isfile(SYNCED_FILE):
        try:
            with open(SYNCED_FILE, "r", encoding="utf-8") as f:
                data = json.load(f)
                if isinstance(data, dict):
                    return data
        except Exception as e:
            print(f"Error reading {SYNCED_FILE}: {e}")
    return {"processed_request_ids": [], "history": []}


def save_synced_state(state):
    try:
        os.makedirs(os.path.dirname(SYNCED_FILE), exist_ok=True)
        temp_file = SYNCED_FILE + ".tmp"
        with open(temp_file, "w", encoding="utf-8") as f:
            json.dump(state, f, indent=2, ensure_ascii=False)
        os.replace(temp_file, SYNCED_FILE)
    except Exception as e:
        print(f"[OVERSEERR] Failed to save {SYNCED_FILE}: {e}")


def is_already_known(title):
    clean_target = re.sub(r'[^a-zA-Z0-9]+', ' ', title).strip().lower()
    if not clean_target:
        return True

    # 1. Check active & monitored anime in ani.json
    if os.path.isfile(ANI_FILE):
        try:
            with open(ANI_FILE, "r", encoding="utf-8") as f:
                data = json.load(f)
                for item in data.get("anime", []):
                    item_name = re.sub(r'[^a-zA-Z0-9]+', ' ', item.get("name", "")).strip().lower()
                    if clean_target == item_name or clean_target in item_name or item_name in clean_target:
                        return True
        except Exception:
            pass

    # 2. Check pending queue.txt
    if os.path.isfile(QUEUE_FILE):
        try:
            with open(QUEUE_FILE, "r", encoding="utf-8") as f:
                content = f.read().lower()
                if clean_target in content:
                    return True
        except Exception:
            pass

    return False


def queue_title(title, default_lang="german", default_res="1080p"):
    clean_title = re.sub(r'[^a-zA-Z0-9\s\-_]+', '', title).strip()
    if not clean_title:
        return False

    if is_already_known(clean_title):
        print(f'[OVERSEERR] "{clean_title}" is already monitored or queued. Skipping.')
        return False

    queue_line = f"{clean_title};{default_lang};{default_res};0;0;0;0\n"
    os.makedirs("/config", exist_ok=True)
    
    with open(QUEUE_FILE, "a", encoding="utf-8") as f:
        f.write(queue_line)
    with open(REQUEST_LOG_FILE, "a", encoding="utf-8") as f:
        f.write(queue_line)

    print(f'[OVERSEERR] Successfully queued "{clean_title}" ({default_lang} {default_res}) for download.')
    return True


def sync_overseerr():
    config = load_config()
    enabled = config.get("overseerr_enabled", False)
    base_url = config.get("overseerr_url", os.environ.get("OVERSEERR_URL", "")).rstrip("/")
    api_key = config.get("overseerr_api_key", os.environ.get("OVERSEERR_API_KEY", "")).strip()

    if not enabled and not (base_url and api_key):
        print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Overseerr integration is disabled.")
        return

    if not base_url or not api_key:
        print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Overseerr URL or API key is missing. Skipping sync.")
        return

    print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Connecting to Overseerr at {base_url}...")

    # Fetch unavailable / pending requests
    endpoint = f"{base_url}/api/v1/request?take=100&filter=unavailable&sort=added"
    try:
        req = Request(endpoint)
        req.add_header("X-Api-Key", api_key)
        req.add_header("User-Agent", "AnimeLoadsUniversal/2.0")
        with urlopen(req, timeout=15) as resp:
            data = json.loads(resp.read().decode())
    except (HTTPError, URLError, Exception) as e:
        print(f"[OVERSEERR ERROR] Failed to fetch requests from {endpoint}: {e}")
        return

    results = data.get("results", [])
    print(f"[OVERSEERR] Found {len(results)} open requests.")

    synced_state = load_synced_state()
    synced_ids = set(synced_state.get("processed_request_ids", []))
    state_modified = False

    preferred_lang = config.get("overseerr_lang", "german")
    preferred_res = config.get("overseerr_res", "1080p")
    queued_count = 0

    for item in reversed(results):
        req_id = item.get("id")
        # If this request ID was already processed in a previous run, do not process again
        if req_id is not None and req_id in synced_ids:
            continue

        media = item.get("media", {})
        result_type = item.get("type", "tv")

        # TMDB or TVDB lookup ID
        lookup_id = media.get("tmdbId") or media.get("tvdbId")
        if not lookup_id:
            continue

        detail_endpoint = f"{base_url}/api/v1/{result_type}/{lookup_id}?language=de"
        try:
            detail_req = Request(detail_endpoint)
            detail_req.add_header("X-Api-Key", api_key)
            detail_req.add_header("User-Agent", "AnimeLoadsUniversal/2.0")
            with urlopen(detail_req, timeout=10) as detail_resp:
                detail = json.loads(detail_resp.read().decode())
        except Exception:
            continue

        title = detail.get("name") if result_type == "tv" else detail.get("title")
        if not title:
            continue

        genres = [g.get("name", "") for g in detail.get("genres", [])]
        keywords = [k.get("name", "") for k in detail.get("keywords", [])]
        orig_lang = detail.get("originalLanguage", "")

        is_anime = False
        if any("anime" in k.lower() for k in keywords):
            is_anime = True
        elif orig_lang == "ja" and any("animation" in g.lower() or "anime" in g.lower() for g in genres):
            is_anime = True

        if is_anime:
            queued = queue_title(title, preferred_lang, preferred_res)
            if queued:
                queued_count += 1
            if req_id is not None:
                synced_ids.add(req_id)
                synced_state["processed_request_ids"].append(req_id)
                synced_state.setdefault("history", []).append({
                    "request_id": req_id,
                    "title": title,
                    "type": result_type,
                    "status": "queued" if queued else "already_monitored",
                    "synced_at": int(time.time())
                })
                state_modified = True
        else:
            # Not an anime: record as handled so we don't query TMDB/TVDB repeatedly
            if req_id is not None:
                synced_ids.add(req_id)
                synced_state["processed_request_ids"].append(req_id)
                synced_state.setdefault("history", []).append({
                    "request_id": req_id,
                    "title": title,
                    "type": result_type,
                    "status": "ignored_non_anime",
                    "synced_at": int(time.time())
                })
                state_modified = True

    if state_modified:
        if len(synced_state["history"]) > 500:
            synced_state["history"] = synced_state["history"][-500:]
        save_synced_state(synced_state)

    print(f"[OVERSEERR] Sync completed. {queued_count} new anime added to queue.")


if __name__ == "__main__":
    sync_overseerr()

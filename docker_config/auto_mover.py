import os
import json
import time
import shutil
import urllib.request

CONFIG_FILE = "/config/config.json"
MOVE_FILES_AFTER_MODIFIED_IDLE_MINUTES = 1

def load_config():
    if not os.path.exists(CONFIG_FILE):
        return None
    with open(CONFIG_FILE, 'r') as f:
        return json.load(f)

def get_idle_time(path):
    # mtime or ctime
    mtime = os.path.getmtime(path)
    # also check files inside
    for root, dirs, files in os.walk(path):
        for f in files:
            fpath = os.path.join(root, f)
            m = os.path.getmtime(fpath)
            if m > mtime:
                mtime = m
    return (time.time() - mtime) / 60.0

def has_rar_files(path):
    for root, dirs, files in os.walk(path):
        for f in files:
            if '.rar' in f.lower() or '.r0' in f.lower() or '.r1' in f.lower():
                return True
    return False

def refresh_plex(config):
    host = config.get("plex_host", "").strip()
    token = config.get("plex_token", "").strip()
    if not host or not token:
        return
    
    # URL: http://[PMS_IP_Address]:32400/library/sections/all/refresh?X-Plex-Token=YourTokenGoesHere
    url = f"{host}/library/sections/all/refresh?X-Plex-Token={token}"
    try:
        req = urllib.request.Request(url, method='GET')
        with urllib.request.urlopen(req, timeout=10) as response:
            print(f"Plex refresh triggered. Status: {response.status}")
    except Exception as e:
        print(f"Failed to refresh Plex: {e}")

def main():
    config = load_config()
    if not config:
        print("Config not found.")
        return
    
    main_storage = config.get("main_storage_dir", "")
    if not main_storage:
        print("Auto-mover disabled: No main_storage_dir configured.")
        return
        
    src_dir = config.get("jd_extraction_dir")
    if not src_dir:
        src_dir = config.get("jd_download_dir")
        
    if not src_dir or not os.path.isdir(src_dir):
        print(f"Source dir {src_dir} not found.")
        return
        
    plex_refreshed = False
    
    for item in os.listdir(src_dir):
        item_path = os.path.join(src_dir, item)
        if not os.path.isdir(item_path):
            continue
            
        idle_mins = get_idle_time(item_path)
        if idle_mins < MOVE_FILES_AFTER_MODIFIED_IDLE_MINUTES:
            continue
            
        if has_rar_files(item_path):
            continue
            
        # Determine category
        target_sub = ""
        base_name = item
        
        lname = item.lower()
        if "hentai_" in lname:
            target_sub = config.get("cat_hentai", "Hentai")
            base_name = item.replace("HENTAI_", "").replace(" japanese 1080p movie", "").replace(" japanese 720p movie", "").replace(" japanese 1080p tv", "").replace(" japanese 720p tv", "").replace(" german 1080p movie", "").replace(" german 720p movie", "").replace(" german 1080p tv", "").replace(" german 720p tv", "")
        elif " german 1080p tv" in lname or " german 720p tv" in lname:
            target_sub = config.get("cat_anime_ger", "Anime (Ger)")
            base_name = item.replace(" german 1080p tv", "").replace(" german 720p tv", "")
        elif " german 1080p movie" in lname or " german 720p movie" in lname:
            target_sub = config.get("cat_filme_ger", "Filme")
            base_name = item.replace(" german 1080p movie", "").replace(" german 720p movie", "")
        elif " japanese 1080p tv" in lname or " japanese 720p tv" in lname:
            target_sub = config.get("cat_anime_jap", "Anime (Jap)")
            base_name = item.replace(" japanese 1080p tv", "").replace(" japanese 720p tv", "")
        elif " japanese 1080p movie" in lname or " japanese 720p movie" in lname:
            target_sub = config.get("cat_filme_jap", "Filme (Jap)")
            base_name = item.replace(" japanese 1080p movie", "").replace(" japanese 720p movie", "")
            
        if target_sub:
            dest_dir = os.path.join(main_storage, target_sub, base_name)
            os.makedirs(dest_dir, exist_ok=True)
            
            print(f"Moving {item_path} to {dest_dir}")
            
            # Move files
            for f in os.listdir(item_path):
                src_file = os.path.join(item_path, f)
                dst_file = os.path.join(dest_dir, f)
                if not os.path.exists(dst_file):
                    shutil.move(src_file, dst_file)
                    
            shutil.rmtree(item_path)
            plex_refreshed = True

    if plex_refreshed:
        refresh_plex(config)

if __name__ == "__main__":
    main()

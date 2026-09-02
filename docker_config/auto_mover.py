import os
import json
import time
import shutil
import urllib.request
import re

CONFIG_FILE = "/config/config.json"
MOVE_FILES_AFTER_MODIFIED_IDLE_MINUTES = 3

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

def is_extracting_or_downloading(path):
    for root, dirs, files in os.walk(path):
        for f in files:
            lf = f.lower()
            if any(ext in lf for ext in ['.rar', '.r0', '.r1', '.part', '.crdownload', '.tmp', '.downloading']):
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
            
        if is_extracting_or_downloading(item_path):
            continue
            
        # Determine category
        target_sub = ""
        base_name = item
        lname = item.lower()
        
        if "hentai_" in lname:
            target_sub = config.get("cat_hentai", "Hentai")
            base_name = re.sub(r'(?i)^hentai_', '', item)
            base_name = re.sub(r'(?i)\s+(japanese|german)\s+(1080p|720p)\s+(tv|movie)', '', base_name)
        elif " german 1080p tv" in lname or " german 720p tv" in lname:
            target_sub = config.get("cat_anime_ger", "Anime (Ger)")
            base_name = re.sub(r'(?i)\s+german\s+(1080p|720p)\s+tv', '', item)
        elif " german 1080p movie" in lname or " german 720p movie" in lname:
            target_sub = config.get("cat_filme_ger", "Filme")
            base_name = re.sub(r'(?i)\s+german\s+(1080p|720p)\s+movie', '', item)
        elif " japanese 1080p tv" in lname or " japanese 720p tv" in lname:
            target_sub = config.get("cat_anime_jap", "Anime (Jap)")
            base_name = re.sub(r'(?i)\s+japanese\s+(1080p|720p)\s+tv', '', item)
        elif " japanese 1080p movie" in lname or " japanese 720p movie" in lname:
            target_sub = config.get("cat_filme_jap", "Filme (Jap)")
            base_name = re.sub(r'(?i)\s+japanese\s+(1080p|720p)\s+movie', '', item)
            
        base_name = base_name.strip()
            
        if target_sub:
            dest_dir = os.path.join(main_storage, target_sub, base_name)
            os.makedirs(dest_dir, exist_ok=True)
            
            print(f"Moving {item_path} to {dest_dir}")
            
            move_success = True
            for f in os.listdir(item_path):
                src_file = os.path.join(item_path, f)
                dst_file = os.path.join(dest_dir, f)
                try:
                    if os.path.exists(dst_file):
                        if os.path.isdir(dst_file):
                            shutil.copytree(src_file, dst_file, dirs_exist_ok=True)
                            if os.path.isdir(src_file):
                                shutil.rmtree(src_file)
                            else:
                                os.remove(src_file)
                        else:
                            os.remove(dst_file)
                            shutil.move(src_file, dst_file)
                    else:
                        shutil.move(src_file, dst_file)
                except Exception as e:
                    print(f"Error moving {src_file} to {dst_file}: {e}")
                    move_success = False

            if move_success:
                shutil.rmtree(item_path, ignore_errors=True)
                plex_refreshed = True

    if plex_refreshed:
        refresh_plex(config)

if __name__ == "__main__":
    main()


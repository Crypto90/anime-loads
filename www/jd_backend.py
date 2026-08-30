
import json
import os
config_path = '/config/config.json'
config = {}
if os.path.exists(config_path):
    with open(config_path, 'r') as f:
        config = json.load(f)
import sys
import json
import myjdapi

# --- CREDENTIALS ---
EMAIL = config.get('jd_email', '')
PASSWORD = config.get('jd_password', '')
DEVICE_ID = 'dc69ebedb8e5d8a1bd2618dddbee1280' 

try:
    jd = myjdapi.Myjdapi()
    jd.set_app_key("AnimeLoadsDashboard")
    jd.connect(EMAIL, PASSWORD)
    
    jd.update_devices()
    
    device = None
    for d in jd.list_devices():
        if d['id'] == DEVICE_ID:
            device = jd.get_device(d['name'])
            break
            
    if not device:
        raise Exception(f"Gerät mit der ID {DEVICE_ID} wurde nicht gefunden.")

    action = sys.argv[1] if len(sys.argv) > 1 else 'status'

    if action == 'start':
        device.downloadcontroller.start_downloads()
        print("OK")
    elif action == 'pause':
        device.downloadcontroller.pause_downloads()
        print("OK")
    elif action == 'stop':
        device.downloadcontroller.stop_downloads()
        print("OK")
    elif action == 'update':
        device.action("/update/restartAndUpdate", [])
        print("OK")
    elif action == 'speedlimit':
        if len(sys.argv) > 2:
            limit = int(sys.argv[2])
            device.action("/downloadcontroller/setSpeedlimit", [limit])
        print("OK")
    elif action in ['delete', 'reset', 'force', 'extract', 'resume']:
        if len(sys.argv) > 2:
            # Parse comma-separated IDs for bulk actions
            package_ids = [int(x) for x in sys.argv[2].split(',')]
            
            if action == 'delete':
                device.downloads.remove_links(package_ids=package_ids)
            elif action == 'reset':
                device.action("/downloadsV2/resetLinks", [[], package_ids])
            elif action == 'force':
                device.action("/downloadsV2/forceDownload", [[], package_ids])
            elif action == 'resume':
                device.action("/downloadsV2/resumeLinks", [[], package_ids])
            elif action == 'extract':
                device.action("/extraction/startExtractionNow", [[], package_ids])
        
        print("OK")
    else:
        # Status abfragen
        state = device.downloadcontroller.get_current_state()
        speed = device.downloadcontroller.get_speed_in_bytes()
        
        has_update = False
        speed_limit = 0
        try:
            has_update = device.action("/update/isUpdateAvailable", [])
            speed_limit = device.action("/downloadcontroller/getSpeedlimit", [])
        except Exception:
            pass 
        
        # Packages abrufen
        packages = device.downloads.query_packages([{
            "bytesLoaded": True,
            "bytesTotal": True,
            "speed": True,
            "eta": True,
            "status": True,
            "finished": True,
            "running": True
        }])

        status_text = "Running" if state == "RUNNING" else ("Paused" if state == "PAUSED" else "Stopped")

        result = {
            "global": {
                "speed": speed if state == "RUNNING" else 0,
                "status": status_text,
                "hasUpdate": has_update,
                "speedLimit": speed_limit
            },
            "packages": packages
        }
        print(json.dumps(result))

except Exception as e:
    print(json.dumps({"error": str(e)}))
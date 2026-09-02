import sys
import json
import myjdapi

if len(sys.argv) != 3:
    print(json.dumps({"success": False, "error": "Invalid arguments"}))
    sys.exit(1)

jd = myjdapi.Myjdapi()
try:
    if jd.connect(sys.argv[1], sys.argv[2]):
        devices = []
        try:
            jd.update_devices()
            devices = [device.name for device in jd.list_devices()]
            print(json.dumps({"success": True, "devices": devices}))
        except Exception as e:
            print(json.dumps({"success": True, "devices": [], "error": f"Failed to get devices: {e}"}))
        sys.exit(0)
    else:
        print(json.dumps({"success": False, "error": "Invalid credentials"}))
        sys.exit(1)
except Exception as e:
    print(json.dumps({"success": False, "error": str(e)}))
    sys.exit(1)

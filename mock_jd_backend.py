import sys
import json

if len(sys.argv) > 1 and sys.argv[1] == 'status':
    print(json.dumps({
        "global": {
            "speed": 12450000,
            "progress": 78,
            "eta": 120,
            "status": "DOWNLOADING"
        },
        "packages": [
            {
                "name": "Demon Slayer (2024) [1080p]",
                "speed": 12450000,
                "progress": 45,
                "eta": 120,
                "status": "DOWNLOADING",
                "uuid": "pkg1"
            },
            {
                "name": "Solo Leveling (2024) [1080p]",
                "speed": 0,
                "progress": 0,
                "eta": 0,
                "status": "QUEUED",
                "uuid": "pkg2"
            }
        ]
    }))

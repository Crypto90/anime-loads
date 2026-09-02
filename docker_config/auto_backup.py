#!/usr/bin/env python3
import glob
import json
import os
import sys
import time
import zipfile

CONFIG_DIR = "/config"
BACKUP_DIR = os.path.join(CONFIG_DIR, "backups")
MAX_BACKUPS = 10

CRITICAL_FILES = [
    "config.json",
    "ani.json",
    "ani_paused.json",
    "queue.txt",
    "requestlog.txt",
    "overseerr_synced.json"
]


def ensure_backup_dir():
    os.makedirs(BACKUP_DIR, exist_ok=True)
    try:
        import pwd
        uid = pwd.getpwnam("www-data").pw_uid
        gid = pwd.getpwnam("www-data").pw_gid
        os.chown(BACKUP_DIR, uid, gid)
    except Exception:
        pass


def create_backup(prefix="backup"):
    ensure_backup_dir()
    timestamp = time.strftime("%Y-%m-%d_%H%M%S")
    zip_filename = f"{prefix}_{timestamp}.zip"
    zip_path = os.path.join(BACKUP_DIR, zip_filename)

    backed_up_count = 0
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zipf:
        for filename in CRITICAL_FILES:
            src_path = os.path.join(CONFIG_DIR, filename)
            if os.path.isfile(src_path):
                zipf.write(src_path, arcname=filename)
                backed_up_count += 1

    try:
        import pwd
        uid = pwd.getpwnam("www-data").pw_uid
        gid = pwd.getpwnam("www-data").pw_gid
        os.chown(zip_path, uid, gid)
    except Exception:
        pass

    # Prune old backups
    prune_old_backups(MAX_BACKUPS)

    file_size = os.path.getsize(zip_path)
    result = {
        "success": True,
        "filename": zip_filename,
        "path": zip_path,
        "file_count": backed_up_count,
        "size_bytes": file_size,
        "created_at": timestamp
    }
    return result


def prune_old_backups(max_keep):
    pattern = os.path.join(BACKUP_DIR, "backup_*.zip")
    backups = sorted(glob.glob(pattern), key=os.path.getmtime)
    
    # Exclude special pre_restore snapshots from standard count
    while len(backups) > max_keep:
        oldest = backups.pop(0)
        try:
            os.remove(oldest)
        except Exception:
            pass


def list_backups():
    ensure_backup_dir()
    pattern = os.path.join(BACKUP_DIR, "*.zip")
    backup_files = sorted(glob.glob(pattern), key=os.path.getmtime, reverse=True)

    result = []
    for bp in backup_files:
        filename = os.path.basename(bp)
        stat = os.stat(bp)
        size_bytes = stat.st_size
        if size_bytes < 1024:
            size_str = f"{size_bytes} B"
        elif size_bytes < 1024 * 1024:
            size_str = f"{size_bytes / 1024:.1f} KB"
        else:
            size_str = f"{size_bytes / (1024 * 1024):.1f} MB"

        result.append({
            "filename": filename,
            "size_bytes": size_bytes,
            "size_formatted": size_str,
            "timestamp": int(stat.st_mtime),
            "date_formatted": time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(stat.st_mtime)),
            "is_pre_restore": filename.startswith("pre_restore_")
        })

    return result


def restore_backup(filename_or_path):
    ensure_backup_dir()
    clean_name = os.path.basename(filename_or_path)
    target_zip = os.path.join(BACKUP_DIR, clean_name)

    if not os.path.isfile(target_zip):
        return {"success": False, "error": f"Backup file {clean_name} not found"}

    # 1. Create a safety snapshot before restoring
    try:
        create_backup(prefix="pre_restore")
    except Exception as e:
        print(f"Warning: Failed to create pre-restore safety snapshot: {e}")

    # 2. Extract into /config
    restored_files = []
    with zipfile.ZipFile(target_zip, "r") as zipf:
        for member in zipf.namelist():
            clean_member = os.path.basename(member)
            if clean_member in CRITICAL_FILES:
                zipf.extract(member, CONFIG_DIR)
                dest = os.path.join(CONFIG_DIR, clean_member)
                restored_files.append(clean_member)
                try:
                    import pwd
                    uid = pwd.getpwnam("www-data").pw_uid
                    gid = pwd.getpwnam("www-data").pw_gid
                    os.chown(dest, uid, gid)
                except Exception:
                    pass

    return {
        "success": True,
        "restored_from": clean_name,
        "files_restored": restored_files
    }


def delete_backup(filename):
    clean_name = os.path.basename(filename)
    target_path = os.path.join(BACKUP_DIR, clean_name)
    if os.path.isfile(target_path):
        try:
            os.remove(target_path)
            return {"success": True, "deleted": clean_name}
        except Exception as e:
            return {"success": False, "error": str(e)}
    return {"success": False, "error": "File not found"}


def main():
    action = sys.argv[1] if len(sys.argv) > 1 else "create"
    
    if action == "create":
        res = create_backup()
        print(json.dumps(res, indent=2))
    elif action == "list":
        res = list_backups()
        print(json.dumps(res, indent=2))
    elif action == "restore":
        if len(sys.argv) < 3:
            print(json.dumps({"success": False, "error": "Missing backup filename"}))
            sys.exit(1)
        res = restore_backup(sys.argv[2])
        print(json.dumps(res, indent=2))
    elif action == "delete":
        if len(sys.argv) < 3:
            print(json.dumps({"success": False, "error": "Missing backup filename"}))
            sys.exit(1)
        res = delete_backup(sys.argv[2])
        print(json.dumps(res, indent=2))
    else:
        print(json.dumps({"success": False, "error": f"Unknown action: {action}"}))
        sys.exit(1)


if __name__ == "__main__":
    main()

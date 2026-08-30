import os
import re
import glob

def refactor_files():
    base_dir = r"c:\Users\GamingLaptop\Documents\AntiGravity_Projects\Anime-Loads-Docker\anime-loads"
    
    php_files = glob.glob(os.path.join(base_dir, 'www', '*.php'))
    py_files_www = glob.glob(os.path.join(base_dir, 'www', '*.py'))
    py_files_docker = glob.glob(os.path.join(base_dir, 'docker_config', '*.py'))
    sh_files_docker = glob.glob(os.path.join(base_dir, 'docker_config', '*.sh'))

    all_files = php_files + py_files_www + py_files_docker + sh_files_docker

    for file_path in all_files:
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
            content = f.read()

        original_content = content
        
        # PHP specific
        if file_path.endswith('.php'):
            if 'setup.php' in file_path: continue
            
            # Add config loading to the top of index.php and folder_manager.php
            if not "config.json" in content:
                setup_check = """<?php
$configFile = '/config/config.json';
if (!file_exists($configFile)) {
    header('Location: setup.php');
    exit;
}
$config = json_decode(file_get_contents($configFile), true);
$base_dir = $config['base_dir'];
"""
                content = content.replace("<?php", setup_check, 1)
            
            # Replace /volumeUSB10/usbshare/docker/anime-loads with $base_dir
            content = content.replace("'/volumeUSB10/usbshare/docker/anime-loads", "$base_dir . '")
            content = content.replace('"/volumeUSB10/usbshare/docker/anime-loads', '$base_dir . "')
            content = content.replace('/volumeUSB10/usbshare/docker/anime-loads', '".$base_dir."')
            
        elif file_path.endswith('.py'):
            # Python specific
            config_load = """
import json
import os
config_path = '/config/config.json'
config = {}
if os.path.exists(config_path):
    with open(config_path, 'r') as f:
        config = json.load(f)
"""
            # Prepend to python files if they use hardcoded paths/passwords
            if 'IphoneKeks90' in content or '/volumeUSB10' in content or 'nico.sprang@googlemail.com' in content:
                content = config_load + content
                
            content = content.replace("'IphoneKeks90'", "config.get('jd_password', '')")
            content = content.replace("'nico.sprang@googlemail.com'", "config.get('jd_email', '')")
            content = content.replace("'/volumeUSB10/usbshare/docker/anime-loads", "config.get('base_dir', '/usr/src/app') + '")
            content = content.replace('"/volumeUSB10/usbshare/docker/anime-loads', "config.get('base_dir', '/usr/src/app') + \"")
            
        if content != original_content:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Refactored {os.path.basename(file_path)}")

if __name__ == '__main__':
    refactor_files()

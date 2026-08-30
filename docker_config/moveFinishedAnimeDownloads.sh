#!/bin/bash

# This script checks for anime content (movie/tv type) in the downloads directory
# and moves the folders if they do not show any change for more than X minutes.

#DOWNLOADS_DIRECTORY="/volumeUSB10/usbshare"
DOWNLOADS_DIRECTORY="/volume1/Downloads"
MOVE_FILES_AFTER_MODIFIED_IDLE_MINUTES=1
LOCK_DIR="/tmp/move_locks"

mkdir -p "$LOCK_DIR"

move_folder() {
    local src="$1"
    local dest="$2"

    local lock_file="$LOCK_DIR/$(basename "$src").lock"

    if [[ -f "$lock_file" ]]; then
        echo "$src is already being moved by another process. Skipping..."
        return
    fi

    touch "$lock_file"

    if [[ $(find "$src" -mmin -$MOVE_FILES_AFTER_MODIFIED_IDLE_MINUTES -ls) ]] || [[ $(find "$src" -name '*.rar*') ]]; then
        echo "$src download is still processing..."
        rm -f "$lock_file"
        return
    fi

    echo ""
    echo "-----------------------------------------"
    echo "Moving folder: $src"
    echo "Destination: $dest"
    echo "-----------------------------------------"

    mkdir -p "$dest"

    # Loop over files to show progress
    for f in "$src"/*; do
        echo "Moving file: $(basename "$f")"
        cp -a "$f" "$dest/"
    done

    # Remove source folder after all files copied
    rm -rf "$src"
    echo "Finished moving folder: $src"
    echo ""

    rm -f "$lock_file"
}



refresh_plex() {
    local section="$1"
    wget -q "http://plex.shieldserver.de/library/sections/$section/refresh?X-Plex-Token=VJPqAirYm3xzXXQ4r5Yw" -O /dev/null
}

# ---------------------------
# Process folders by category
# ---------------------------

shopt -s nullglob
for d in "$DOWNLOADS_DIRECTORY"/*; do
    # German TV
    if { [[ "$d" =~ " german 1080p tv" ]] || [[ "$d" =~ " german 720p tv" ]]; } && [[ "$d" != *"HENTAI_"* ]]; then
        BASE_DIR=$(basename "$d")
        BASE_DIR=${BASE_DIR//" german 1080p tv"/}
        BASE_DIR=${BASE_DIR//" german 720p tv"/}
        move_folder "$d" "/volume1/video/Anime (Ger)/$BASE_DIR"
        refresh_plex 8
    fi

    # German Movies
    if { [[ "$d" =~ " german 1080p movie" ]] || [[ "$d" =~ " german 720p movie" ]]; } && [[ "$d" != *"HENTAI_"* ]]; then
        BASE_DIR=$(basename "$d")
        BASE_DIR=${BASE_DIR//" german 1080p movie"/}
        BASE_DIR=${BASE_DIR//" german 720p movie"/}
        move_folder "$d" "/volume1/video/Filme/$BASE_DIR"
        refresh_plex 2
    fi

    # Japanese TV
    if { [[ "$d" =~ " japanese 1080p tv" ]] || [[ "$d" =~ " japanese 720p tv" ]]; } && [[ "$d" != *"HENTAI_"* ]]; then
        BASE_DIR=$(basename "$d")
        BASE_DIR=${BASE_DIR//" japanese 1080p tv"/}
        BASE_DIR=${BASE_DIR//" japanese 720p tv"/}
        move_folder "$d" "/volume1/video/Anime (Jap)/$BASE_DIR"
        refresh_plex 3
    fi

    # Japanese Movies
    if { [[ "$d" =~ " japanese 1080p movie" ]] || [[ "$d" =~ " japanese 720p movie" ]]; } && [[ "$d" != *"HENTAI_"* ]]; then
        BASE_DIR=$(basename "$d")
        BASE_DIR=${BASE_DIR//" japanese 1080p movie"/}
        BASE_DIR=${BASE_DIR//" japanese 720p movie"/}
        move_folder "$d" "/volume1/video/Filme (Jap)/$BASE_DIR"
        refresh_plex 25
    fi

    # Hentai
    if [[ "$d" =~ "HENTAI_" ]]; then
        BASE_DIR=$(basename "$d")
        # remove all quality/language prefixes
        BASE_DIR=${BASE_DIR//" japanese 1080p movie"/}
        BASE_DIR=${BASE_DIR//" japanese 720p movie"/}
        BASE_DIR=${BASE_DIR//" japanese 1080p tv"/}
        BASE_DIR=${BASE_DIR//" japanese 720p tv"/}
        BASE_DIR=${BASE_DIR//" german 1080p movie"/}
        BASE_DIR=${BASE_DIR//" german 720p movie"/}
        BASE_DIR=${BASE_DIR//" german 1080p tv"/}
        BASE_DIR=${BASE_DIR//" german 720p tv"/}
        BASE_DIR=${BASE_DIR//"HENTAI_"/}

        move_folder "$d" "/volume1/video/Hentai/$BASE_DIR"
        refresh_plex 26
    fi
done

echo ""
echo "All checks complete."

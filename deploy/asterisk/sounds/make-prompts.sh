#!/bin/sh
# Turns "name|text" lines into 8 kHz mono wav files (the format Asterisk plays on a phone line) with the Indonesian voice of espeak-ng.
# A machine voice is a placeholder: replace each file with a recorded one by putting `<name>.wav` in deploy/asterisk/sounds/custom (see README).
set -eu

list=$1
out=$2
mkdir -p "$out"

while IFS='|' read -r name text; do
    [ -n "$name" ] || continue
    espeak-ng -v id -s 140 -w "/tmp/$name.raw.wav" "$text"
    ffmpeg -loglevel error -y -i "/tmp/$name.raw.wav" -ar 8000 -ac 1 -acodec pcm_s16le "$out/$name.wav"
done < "$list"

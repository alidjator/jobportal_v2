#!/usr/bin/env python3
"""Recording converter of the job portal video interviews (docs/design/phase-7-tele-interview.md, form 1 part 4).

The application posts {"recording_id": 7, "directory": "/recordings/raw/7"} with `Authorization: Bearer <JANUS_WEBHOOK_SECRET>`; the answer is 202 at once.
The worker turns the raw Janus tracks (.mjr) in that directory into one WebM file, /recordings/ready/7.webm, and posts the outcome to
`<APP_WEBHOOK_BASE>/recordings` with the same secret: {"recording_id", "status": "ready"|"failed", "file": "ready/7.webm", "duration_seconds"}.

Layout: the video tracks sit side by side (one column per participant, 640x360), the audio tracks are mixed. Tracks start at the time their file was
created, so a participant who joined late is delayed by the same amount. Needs janus-pp-rec and ffmpeg on the PATH. Standard library only.
"""

import hmac
import json
import os
import queue
import re
import shutil
import subprocess
import sys
import threading
import urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

ROOT = Path(os.environ.get("RECORDINGS_ROOT", "/recordings"))
SECRET = os.environ.get("JANUS_WEBHOOK_SECRET", "")
CALLBACK = os.environ.get("APP_WEBHOOK_BASE", "").rstrip("/") + "/recordings"
BIND = ("127.0.0.1", int(os.environ.get("CONVERTER_PORT", "18090")))
STEP_TIMEOUT = int(os.environ.get("CONVERTER_STEP_TIMEOUT", "3000"))
TILE_WIDTH, TILE_HEIGHT = 640, 360

jobs: "queue.Queue[tuple[int, Path]]" = queue.Queue()


def log(message: str) -> None:
    print(f"[converter] {message}", flush=True)


class Handler(BaseHTTPRequestHandler):
    def do_POST(self) -> None:
        if self.path.rstrip("/") != "/convert":
            return self.reply(404, {"error": "not found"})

        token = self.headers.get("Authorization", "").removeprefix("Bearer ").strip()
        if not SECRET or not hmac.compare_digest(token, SECRET):
            return self.reply(401, {"error": "invalid credentials"})

        try:
            body = json.loads(self.rfile.read(min(int(self.headers.get("Content-Length", "0")), 4096)) or b"{}")
            recording_id = int(body["recording_id"])
            directory = Path(body["directory"]).resolve()
        except (ValueError, KeyError, TypeError):
            return self.reply(422, {"error": "recording_id and directory are required"})

        raw_root = (ROOT / "raw").resolve()
        if directory.parent != raw_root or not directory.is_dir():
            return self.reply(422, {"error": "directory must be a folder directly under the raw directory"})

        jobs.put((recording_id, directory))
        self.reply(202, {"queued": recording_id})

    def reply(self, status: int, payload: dict) -> None:
        data = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def log_message(self, fmt: str, *args) -> None:  # noqa: A003
        log(fmt % args)


def run(command: list[str]) -> None:
    subprocess.run(command, check=True, timeout=STEP_TIMEOUT, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)


def start_time(track: Path) -> int:
    """The creation time in the file name (`...-<timestamp>-audio.mjr`); microseconds or seconds, so it is normalised to milliseconds."""
    found = re.search(r"-(\d{9,})-(?:audio|video)\.mjr$", track.name)
    if not found:
        return 0
    value = int(found.group(1))
    return value // 1000 if value > 10**14 else value * 1000


def duration(path: Path) -> int:
    out = subprocess.run(
        ["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", str(path)],
        check=True, capture_output=True, text=True, timeout=60,
    ).stdout.strip()
    return int(float(out)) if out else 0


def convert(recording_id: int, directory: Path) -> Path:
    work = ROOT / "work" / str(recording_id)
    shutil.rmtree(work, ignore_errors=True)
    work.mkdir(parents=True)
    try:
        return build(recording_id, directory, work)
    finally:
        shutil.rmtree(work, ignore_errors=True)


def build(recording_id: int, directory: Path, work: Path) -> Path:

    audios, videos = [], []
    for track in sorted(directory.glob("*.mjr")):
        kind = "audio" if track.name.endswith("-audio.mjr") else "video"
        out = work / f"{track.stem}.{'opus' if kind == 'audio' else 'webm'}"
        run(["janus-pp-rec", str(track), str(out)])
        (audios if kind == "audio" else videos).append((start_time(track), out))

    if not audios and not videos:
        raise RuntimeError("no tracks")

    origin = min(t for t, _ in audios + videos)
    command = ["ffmpeg", "-y", "-nostdin", "-threads", "2"]
    for _, path in videos + audios:
        command += ["-i", str(path)]

    graph, tiles, mixes = [], [], []
    for index, (started, _) in enumerate(videos):
        graph.append(
            f"[{index}:v]setpts=PTS-STARTPTS+{(started - origin) / 1000}/TB,fps=15,"
            f"scale={TILE_WIDTH}:{TILE_HEIGHT}:force_original_aspect_ratio=decrease,"
            f"pad={TILE_WIDTH}:{TILE_HEIGHT}:(ow-iw)/2:(oh-ih)/2[v{index}]"
        )
        tiles.append(f"[v{index}]")
    for offset, (started, _) in enumerate(audios):
        index = len(videos) + offset
        delay = max(started - origin, 0)
        graph.append(f"[{index}:a]adelay={delay}:all=1[a{offset}]")
        mixes.append(f"[a{offset}]")

    maps: list[str] = []
    if tiles:
        graph.append(f"{''.join(tiles)}hstack=inputs={len(tiles)}[video]" if len(tiles) > 1 else f"{tiles[0]}null[video]")
        maps += ["-map", "[video]"]
    if mixes:
        graph.append(f"{''.join(mixes)}amix=inputs={len(mixes)}:normalize=0[audio]" if len(mixes) > 1 else f"{mixes[0]}anull[audio]")
        maps += ["-map", "[audio]"]

    ready = ROOT / "ready"
    ready.mkdir(exist_ok=True)
    target = ready / f"{recording_id}.webm"
    partial = ready / f".{recording_id}.partial.webm"
    codecs = (["-c:v", "libvpx", "-b:v", "1M", "-deadline", "realtime", "-cpu-used", "5"] if tiles else []) + (["-c:a", "libopus", "-b:a", "96k"] if mixes else [])
    run(command + ["-filter_complex", ";".join(graph), *maps, *codecs, str(partial)])
    os.replace(partial, target)
    return target


def callback(payload: dict) -> None:
    request = urllib.request.Request(
        CALLBACK,
        data=json.dumps(payload).encode(),
        headers={"Content-Type": "application/json", "Authorization": f"Bearer {SECRET}"},
        method="POST",
    )
    for attempt in range(5):
        try:
            with urllib.request.urlopen(request, timeout=15):
                return
        except Exception as error:  # the application may be restarting: try again
            log(f"callback attempt {attempt + 1} failed: {error}")
            threading.Event().wait(5 * (attempt + 1))


def worker() -> None:
    while True:
        recording_id, directory = jobs.get()
        try:
            target = convert(recording_id, directory)
            callback({"recording_id": recording_id, "status": "ready", "file": f"ready/{target.name}", "duration_seconds": duration(target)})
            log(f"recording {recording_id} converted")
        except Exception as error:
            log(f"recording {recording_id} failed: {error}")
            callback({"recording_id": recording_id, "status": "failed"})


if __name__ == "__main__":
    if not SECRET or not CALLBACK.startswith("http"):
        sys.exit("JANUS_WEBHOOK_SECRET and APP_WEBHOOK_BASE are required")
    # One conversion at a time: ffmpeg is the heaviest thing on the server.
    threading.Thread(target=worker, daemon=True).start()
    log(f"listening on {BIND[0]}:{BIND[1]}")
    ThreadingHTTPServer(BIND, Handler).serve_forever()

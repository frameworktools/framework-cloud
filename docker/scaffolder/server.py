"""The scaffolder's HTTP service. Standard library only.

POST /build with JSON {"layers": <layers.v1 envelope>, "folder": "my-project"}
runs `fw new --from layers.json --headless` in a fresh temporary folder and
answers with a zip of the project, whose files sit under folder/. A failed
build answers 422 with {"error": ..., "log": ...}.

GET /health answers {"ok": true} and the pinned tool versions.

SCAFFOLDER_TOKEN, when set, must arrive as "Authorization: Bearer <token>"
on /build. The service is meant for a private network next to Framework
Cloud, never the internet.
"""

import hmac
import io
import json
import os
import re
import shutil
import stat
import subprocess
import tempfile
import threading
import time
import zipfile
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

PORT = int(os.environ.get("SCAFFOLDER_PORT", "8000"))
TOKEN = os.environ.get("SCAFFOLDER_TOKEN", "")
TIMEOUT = int(os.environ.get("SCAFFOLDER_TIMEOUT", "180"))
MAX_BODY = 2 * 1024 * 1024
SLOTS = threading.BoundedSemaphore(int(os.environ.get("SCAFFOLDER_CONCURRENCY", "2")))
FOLDER = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$")

# Machine-specific files a project rebuilds itself: renv's library holds
# packages built for this container, not for the person's computer
SKIP = (("renv", "library"), ("renv", "staging"), ("renv", "local"), (".git",))


class BuildError(Exception):
    def __init__(self, message, log=""):
        super().__init__(message)
        self.log = log


def versions():
    return {
        "fw": os.environ.get("FW_REF", ""),
        "framework": os.environ.get("FRAMEWORK_REF", ""),
        "framework_py": os.environ.get("FRAMEWORK_PY_REF", ""),
    }


def build(layers, folder):
    work = Path(tempfile.mkdtemp(prefix="scaffold-"))
    try:
        home = work / "home"
        home.mkdir()
        layers_file = work / "layers.json"
        layers_file.write_text(json.dumps(layers))
        target = work / "out" / folder

        env = {
            "PATH": os.environ["PATH"],
            "HOME": str(home),
            "LANG": "C.UTF-8",
            "FW_PYTHON": os.environ.get("FW_PYTHON", ""),
            "R_LIBS_USER": str(home / "R"),
        }
        try:
            result = subprocess.run(
                ["fw", "new", "--from", str(layers_file), "--dir", str(target), "--headless"],
                cwd=work, env=env, stdin=subprocess.DEVNULL,
                stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                timeout=TIMEOUT, text=True,
            )
        except subprocess.TimeoutExpired as e:
            raise BuildError(f"Building the project took longer than {TIMEOUT} seconds.", tail(e.output))

        if result.returncode != 0 or not target.is_dir():
            raise BuildError("The project could not be built.", tail(result.stdout))

        return zip_folder(target, folder)
    finally:
        shutil.rmtree(work, ignore_errors=True)


def tail(text, lines=60):
    if isinstance(text, bytes):
        text = text.decode("utf-8", "replace")
    return "\n".join((text or "").splitlines()[-lines:])


def zip_folder(root, folder):
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w", zipfile.ZIP_DEFLATED) as archive:
        archive.writestr(dir_entry(folder + "/"), b"")
        for path in sorted(root.rglob("*")):
            relative = path.relative_to(root)
            if any(relative.parts[: len(skip)] == skip for skip in SKIP):
                continue
            if path.is_symlink():
                continue
            name = f"{folder}/{relative.as_posix()}"
            if path.is_dir():
                # Empty folders matter: they are the project's structure
                archive.writestr(dir_entry(name + "/"), b"")
            else:
                info = zipfile.ZipInfo.from_file(path, name)
                info.compress_type = zipfile.ZIP_DEFLATED
                # Keep the executable bit, drop everything else odd
                mode = 0o755 if path.stat().st_mode & stat.S_IXUSR else 0o644
                info.external_attr = (stat.S_IFREG | mode) << 16
                archive.writestr(info, path.read_bytes())
    return buffer.getvalue()


def dir_entry(name):
    info = zipfile.ZipInfo(name, date_time=time.localtime()[:6])
    info.external_attr = (stat.S_IFDIR | 0o755) << 16 | 0x10
    return info


class Handler(BaseHTTPRequestHandler):
    server_version = "framework-scaffolder"

    def do_GET(self):
        if self.path == "/health":
            self.send_json(200, {"ok": True, "versions": versions()})
        else:
            self.send_json(404, {"error": "Not found."})

    def do_POST(self):
        if self.path != "/build":
            return self.send_json(404, {"error": "Not found."})
        if TOKEN and not hmac.compare_digest(self.headers.get("Authorization", ""), f"Bearer {TOKEN}"):
            return self.send_json(401, {"error": "Unauthorized."})

        length = int(self.headers.get("Content-Length") or 0)
        if length <= 0 or length > MAX_BODY:
            return self.send_json(413 if length > MAX_BODY else 400, {"error": "Send the layers as a JSON body under 2 MB."})
        try:
            body = json.loads(self.rfile.read(length))
        except ValueError:
            return self.send_json(400, {"error": "The body is not JSON."})

        layers, folder = body.get("layers"), body.get("folder", "")
        if not isinstance(layers, dict) or not FOLDER.match(str(folder)) or folder in (".", ".."):
            return self.send_json(400, {"error": "Send layers (an object) and folder (letters, numbers, ., _, -)."})

        if not SLOTS.acquire(timeout=60):
            return self.send_json(503, {"error": "The scaffolder is busy. Try again in a minute."})
        try:
            data = build(layers, folder)
        except BuildError as e:
            return self.send_json(422, {"error": str(e), "log": e.log})
        finally:
            SLOTS.release()

        self.send_response(200)
        self.send_header("Content-Type", "application/zip")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def send_json(self, status, payload):
        data = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def log_message(self, format, *args):
        print(f"{self.address_string()} {format % args}", flush=True)


if __name__ == "__main__":
    print(f"Scaffolder listening on {PORT}", flush=True)
    ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()

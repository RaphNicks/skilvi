#!/usr/bin/env python3
"""Static server for the Skilvi preview.

Sends no-store cache headers for every page/asset so the preview browser
and any proxy edge can NEVER serve a stale copy of the site.
"""
import http.server
import os

ROOT = "/home/user/skilvi-frontend"
NO_CACHE_EXTS = (".html", ".css", ".js", ".jpg", ".jpeg", ".png",
                 ".svg", ".webp", ".gif", ".ico", ".woff", ".woff2")


class NoCacheHandler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=ROOT, **kwargs)

    def end_headers(self):
        path = self.path.split("?", 1)[0].split("#", 1)[0].lower()
        if path.endswith(NO_CACHE_EXTS) or path == "/":
            self.send_header("Cache-Control", "no-store, max-age=0, must-revalidate")
            self.send_header("Pragma", "no-cache")
            self.send_header("Expires", "0")
        super().end_headers()


if __name__ == "__main__":
    os.chdir(ROOT)
    http.server.ThreadingHTTPServer(("0.0.0.0", 8000), NoCacheHandler).serve_forever()

#!/usr/bin/env python3
"""Render the Skilvi hero background loop (1920x1080, 30fps, 12s, seamless).

Every motion in the scene is a periodic function of time with a period that
divides 12s, and the grid drifts exactly one cell per loop, so the last frame
hands back to the first with no visible seam.

Scene (all flat brand colors, no gradients):
  - white base + fine royal grid, drifting slowly to the bottom-right
  - large royal-100 circle (right of center) breathing gently
  - two concentric pulse rings expanding from the circle, fading out
  - small royal dots floating on staggered vertical cycles
  - faint plus-marks at grid intersections, pulsing in and out
"""
import math
import sys
from PIL import Image, ImageDraw

W, H = 1920, 1080
FPS = 30
DUR = 12
FRAMES = FPS * DUR

BG = (255, 255, 255)
GRID = (15, 23, 42, 11)          # rgba 11/255 ~ 4.3%
CELL = 64
CIRCLE = (223, 232, 253)         # royal-100
CIRCLE_EDGE = (195, 212, 251, 90)  # royal-200 soft rim
RING = (151, 180, 247)           # royal-300
DOTS = (58, 107, 232)            # royal-500
PLUS = (31, 68, 190)             # royal-600
SOFT = (242, 246, 254)           # royal-50 (top-left depth circle)

MX, MY = int(W * 0.70), int(H * 0.46)     # main circle center
BASE_R = 330


def sin_cyc(t, period, phase=0.0):
    return math.sin(2 * math.pi * (t / period) + phase)


def draw_grid(d, ox, oy):
    sx = int(ox % CELL)
    sy = int(oy % CELL)
    for x in range(sx - CELL, W + CELL, CELL):
        d.line([(x, -1), (x, H + 1)], fill=GRID, width=1)
    for y in range(sy - CELL, H + CELL, CELL):
        d.line([(-1, y), (W + 1, y)], fill=GRID, width=1)


def render_frame(fi):
    t = fi / FPS
    base = Image.new("RGBA", (W, H), BG + (255,))
    ov = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(ov)

    # grid drift: exactly three cells per loop (0.53px/frame = smooth creep)
    off = (fi / FRAMES) * CELL * 3
    draw_grid(d, off, off)

    # soft depth circle, top-left (breathes on the 12s cycle, opposite phase)
    r2 = 210 + 10 * sin_cyc(t, 12, math.pi)
    d.ellipse([W * 0.14 - r2, H * 0.16 - r2, W * 0.14 + r2, H * 0.16 + r2],
              fill=SOFT + (255,))

    # three pulse rings expanding from the hero's visual area
    # (period 6s divides the 12s loop, so every offset stays seamless)
    for k in range(3):
        local = (t + k * 2) % 6
        prog = local / 6
        a = int(80 * math.sin(math.pi * prog))          # 0 -> 80 -> 0
        if a <= 2:
            continue
        rr = 345 + 300 * prog
        d.ellipse([MX - rr, MY - rr, MX + rr, MY + rr],
                  outline=RING + (a,), width=2)

    # (no filled circle here — the hero's real HTML circle sits above the
    # video; this layer is pure background texture: rings, dots, marks)

    # floating dots: staggered vertical cycles (periods divide 12s)
    dot_spec = [
        (0.30, 0.30, 9,  16, 4, 0.0),
        (0.86, 0.74, 13, 12, 6, 1.7),
        (0.55, 0.84, 8,  20, 12, 3.1),
        (0.11, 0.60, 11, 14, 4, 4.2),
    ]
    for fx, fy, rad, amp, period, phase in dot_spec:
        x = W * fx
        y = H * fy + amp * sin_cyc(t, period, phase)
        d.ellipse([x - rad, y - rad, x + rad, y + rad],
                  fill=DOTS + (44,))

    # pulsing plus marks
    plus_spec = [
        (0.44, 0.22, 6, 0.0),
        (0.78, 0.18, 4, 1.1),
        (0.88, 0.40, 6, 2.4),
        (0.62, 0.70, 4, 3.5),
        (0.24, 0.48, 6, 5.0),
        (0.36, 0.90, 4, 2.0),
    ]
    for fx, fy, period, phase in plus_spec:
        a = int(38 * (0.5 + 0.5 * sin_cyc(t, period, phase)))
        if a <= 3:
            continue
        x, y = W * fx, H * fy
        s = 9
        d.line([(x - s, y), (x + s, y)], fill=PLUS + (a,), width=2)
        d.line([(x, y - s), (x, y + s)], fill=PLUS + (a,), width=2)

    out = Image.alpha_composite(base, ov).convert("RGB")
    return out


def main():
    out_w = sys.stdout.buffer
    # poster frame first (mid-loop, t=6s)
    poster = render_frame(FRAMES // 2)
    with open("assets/video/hero-loop-poster.jpg", "wb") as f:
        poster.save(f, "JPEG", quality=88)
    for fi in range(FRAMES):
        render_frame(fi).save(out_w, format="PNG")
        if fi % 60 == 0:
            print(f"frame {fi}/{FRAMES}", file=sys.stderr)
    print("done", file=sys.stderr)


if __name__ == "__main__":
    main()

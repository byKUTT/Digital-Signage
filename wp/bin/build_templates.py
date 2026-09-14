#!/usr/bin/env python3
"""Build editable Vellum templates and exact raster previews from one catalog."""

from __future__ import annotations

import json
import math
import random
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parents[1]
IMAGE_DIR = ROOT / "public" / "images" / "templates"
DOC_DIR = ROOT / "public" / "templates" / "designs"

CATALOG = {
    "offers": {
        "name": "Daily offers", "heading": "TODAY'S\nOFFERS", "detail": "Truffle prawn pasta",
        "value": "€8.90", "note": "FRESHLY MADE · ALL DAY", "image": "offers.webp",
        "palettes": [["#10261d", "#e6ff3b", "#f8f6ed"], ["#b84b2f", "#f3c35c", "#fff7e8"], ["#172b61", "#7cc8ff", "#f8fbff"], ["#f4eadf", "#6e2724", "#2a1d19"]],
    },
    "menu": {
        "name": "Cafe menu", "heading": "GOOD\nCOFFEE", "detail": "Flat white · Filter · Espresso",
        "value": "FROM €2.80", "note": "ROASTED THIS WEEK", "image": "menu.webp",
        "palettes": [["#f1e9dc", "#e55e35", "#382b25"], ["#d9e7dc", "#173f35", "#142c26"], ["#17171a", "#f3ce52", "#faf7ee"], ["#dce8f5", "#1f4ba8", "#15213a"]],
    },
    "event": {
        "name": "Event night", "heading": "SUMMER\nNIGHT", "detail": "Live music · Seasonal menu",
        "value": "18:00", "note": "24 AUGUST", "image": "event.webp",
        "palettes": [["#1f39d1", "#c8ff45", "#ffffff"], ["#40144f", "#ff8a5b", "#fff5ef"], ["#0d3234", "#f5d76f", "#f5fbf7"], ["#f0e7dc", "#ec384d", "#201416"]],
    },
    "welcome": {
        "name": "Welcome", "heading": "WELCOME", "detail": "Good things flow better together.",
        "value": "09:00–21:00", "note": "OPEN TODAY", "image": "welcome.webp",
        "palettes": [["#431529", "#ffcf4f", "#fff5ee"], ["#173d2c", "#9de07d", "#f7f3e8"], ["#172b61", "#90d8ed", "#ffffff"], ["#efe6d7", "#d84932", "#2c201b"]],
    },
}

FONT_BOLD = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
FONT_REGULAR = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(FONT_BOLD if bold else FONT_REGULAR, max(8, size))


def texture(image: Image.Image, strength: int = 12) -> Image.Image:
    noise = Image.effect_noise(image.size, 18).convert("L")
    overlay = Image.merge("RGBA", (noise, noise, noise, Image.new("L", image.size, strength)))
    return Image.alpha_composite(image.convert("RGBA"), overlay)


def build_source_images() -> None:
    random.seed(47)
    size = 1400
    for family in CATALOG:
        im = Image.new("RGBA", (size, size), "#102019")
        d = ImageDraw.Draw(im, "RGBA")
        if family == "offers":
            d.rectangle((0, 0, size, size), fill="#c7a06d")
            for _ in range(80):
                x, y = random.randrange(size), random.randrange(size)
                d.ellipse((x - 5, y - 5, x + 5, y + 5), fill=(83, 58, 35, 28))
            d.ellipse((180, 120, 1260, 1200), fill="#f3eee2", outline="#d8d0c0", width=24)
            d.ellipse((290, 230, 1150, 1090), fill="#d49d43")
            for i in range(30):
                a = i * .72
                x, y = 720 + math.cos(a) * (80 + i * 10), 660 + math.sin(a) * (80 + i * 10)
                d.arc((x - 180, y - 70, x + 180, y + 70), 5, 340, fill="#f5d68b", width=24)
            for x, y in [(510, 520), (880, 610), (700, 850), (590, 760)]:
                d.ellipse((x - 55, y - 40, x + 55, y + 40), fill="#e87863")
            d.ellipse((630, 440, 790, 600), fill="#4f7d42")
        elif family == "menu":
            d.rectangle((0, 0, size, size), fill="#b78963")
            d.ellipse((240, 190, 1190, 1140), fill="#e9dfd0", outline="#f8f2e8", width=35)
            d.ellipse((350, 300, 1080, 1030), fill="#4c2717")
            d.ellipse((410, 360, 1020, 970), fill="#b87446")
            d.arc((520, 430, 910, 800), 195, 345, fill="#f4eadc", width=65)
            d.arc((610, 500, 820, 720), 20, 160, fill="#f4eadc", width=45)
            for i in range(20):
                x, y = random.randrange(90, 1320), random.randrange(80, 1320)
                d.ellipse((x - 22, y - 35, x + 22, y + 35), fill="#4a281b", outline="#25120c", width=4)
        elif family == "event":
            d.rectangle((0, 0, size, size), fill="#09130f")
            for color, center in [((230, 255, 59, 210), (310, 170)), ((124, 200, 255, 190), (1040, 120)), ((255, 107, 102, 170), (720, 300))]:
                glow = Image.new("RGBA", im.size)
                gd = ImageDraw.Draw(glow, "RGBA")
                gd.ellipse((center[0]-360, center[1]-360, center[0]+360, center[1]+360), fill=color)
                glow = glow.filter(ImageFilter.GaussianBlur(160))
                im = Image.alpha_composite(im, glow)
            d = ImageDraw.Draw(im, "RGBA")
            for i in range(9):
                x = 60 + i * 165
                d.line((x, 0, 560 + i * 70, size), fill=(248, 246, 237, 38), width=8)
            d.rectangle((0, 1040, size, size), fill=(4, 10, 8, 180))
            for i in range(28):
                x = i * 55
                d.ellipse((x, 990-random.randrange(80), x+80, 1120), fill=(3, 8, 6, 230))
        else:
            d.rectangle((0, 0, size, size), fill="#bfd2bd")
            for i in range(18):
                angle = i * .72
                cx = 700 + math.cos(angle) * (80 + i * 24)
                cy = 700 + math.sin(angle) * (70 + i * 22)
                w, h = 170 + i * 10, 380
                color = (24 + i * 3, 86 + i * 4, 54 + i * 2, 230)
                d.ellipse((cx-w/2, cy-h/2, cx+w/2, cy+h/2), fill=color)
                d.line((700, 700, cx, cy), fill=(226, 238, 218, 150), width=8)
        texture(im).convert("RGB").save(IMAGE_DIR / CATALOG[family]["image"], "WEBP", quality=90, method=6)


def base_node(node_type: str, node_id: str, parent: str | None, x: int, y: int, w: int, h: int, **extra):
    data = {"id": node_id, "type": node_type, "name": extra.pop("name", node_type.title()), "parentId": parent,
            "x": x, "y": y, "w": w, "h": h, "rotation": 0, "fill": "#ffffff", "fill2": "#ffffff",
            "fillType": "solid", "gradientAngle": 90, "fillOpacity": 1, "stroke": "#000000", "strokeWidth": 0,
            "radius": 0, "opacity": 1, "visible": True, "locked": False, "clip": False, "shadow": False,
            "shadowColor": "#000000", "shadowOpacity": .16, "shadowBlur": 20, "shadowX": 0, "shadowY": 6, "version": 0}
    data.update(extra)
    return data


def make_document(family: str, variation: int, orientation: str):
    item = CATALOG[family]
    portrait = orientation == "portrait"
    width, height = (1080, 1920) if portrait else (1920, 1080)
    bg, accent, ink = item["palettes"][variation - 1]
    root = f"root-{family}-{variation}-{orientation}"
    nodes = [base_node("frame", root, None, 0, 0, width, height, name=f"{width} × {height}", fill=bg, clip=True)]
    aid = f"asset-{family}"
    margin = round(width * .065)

    def rect(nid, x, y, w, h, color, **kw): nodes.append(base_node("rect", nid, root, x, y, w, h, fill=color, **kw))
    def image(nid, x, y, w, h, radius=0): nodes.append(base_node("image", nid, root, x, y, w, h, assetId=aid, radius=radius, name="Editorial image"))
    def label(nid, value, x, y, w, h, size, color, weight=600, align="left", spacing=0):
        nodes.append(base_node("text", nid, root, x, y, w, h, text=value, fontFamily="Geist", fontSize=size,
                               fontWeight=weight, fontStyle="normal", lineHeight=1.04, letterSpacing=spacing,
                               textAlign=align, textDecoration="none", textCase="none", direction="auto", fill=color, name=nid.replace("-", " ").title()))

    if variation == 1:
        iw = round(width * (.48 if not portrait else .78)); ix = width - iw
        image("photo", ix, 0 if not portrait else round(height * .50), iw, height if not portrait else round(height * .50))
        rect("signal", ix - round(width*.012), 0, round(width*.012), height, accent)
        label("heading", item["heading"], margin, margin, round(width*.43 if not portrait else width*.82), round(height*.34), 134 if not portrait else 102, ink, 800)
        label("note", item["note"], margin, round(height*.47 if not portrait else height*.30), round(width*.42 if not portrait else width*.8), 70, 28 if not portrait else 25, ink, 700, spacing=2)
        label("detail", item["detail"], margin, round(height*.61 if not portrait else height*.39), round(width*.39 if not portrait else width*.8), 150, 42 if not portrait else 36, ink, 500)
        label("value", item["value"], margin, round(height*.82 if not portrait else height*.84), round(width*.4 if not portrait else width*.75), 130, 68 if not portrait else 62, accent, 800)
    elif variation == 2:
        image("photo", 0, 0, width, height)
        rect("veil", 0, 0, width, height, bg, opacity=.68)
        rect("signal", margin, margin, round(width*.016), round(height*.2), accent)
        label("note", item["note"], margin+round(width*.04), margin, round(width*.7), 60, 27, ink, 700, spacing=3)
        label("heading", item["heading"], margin, round(height*.28), round(width*.84), round(height*.34), 148 if not portrait else 112, ink, 800, "center")
        label("detail", item["detail"], margin, round(height*.66), round(width*.84), 90, 40 if not portrait else 34, ink, 500, "center")
        label("value", item["value"], margin, round(height*.80), round(width*.84), 110, 62, accent, 800, "center")
    elif variation == 3:
        ih = round(height * .56)
        image("photo", 0, 0, width, ih)
        rect("signal", 0, ih, width, round(height*.025), accent)
        label("note", item["note"], margin, ih+round(height*.07), round(width*.35), 55, 25, ink, 700, spacing=3)
        label("heading", item["heading"].replace("\n", " "), margin, ih+round(height*.15), round(width*.64), round(height*.18), 88 if not portrait else 68, ink, 800)
        label("detail", item["detail"], margin, ih+round(height*.34), round(width*.56), 70, 34, ink, 500)
        label("value", item["value"], round(width*.68), ih+round(height*.16), round(width*.25), 100, 60, accent, 800, "right")
    else:
        rect("accent", 0, 0, width, height, accent)
        pad = round(width*.075); iw = round(width*(.46 if not portrait else .78)); ih = round(height*(.72 if not portrait else .43))
        image("photo", width-iw-pad if not portrait else pad, pad if not portrait else round(height*.49), iw, ih, radius=round(width*.018))
        label("note", item["note"], pad, pad, round(width*.38 if not portrait else width*.82), 60, 25, bg, 700, spacing=3)
        label("heading", item["heading"], pad, round(height*.23 if not portrait else height*.13), round(width*.38 if not portrait else width*.82), round(height*.28), 116 if not portrait else 92, bg, 800)
        label("detail", item["detail"], pad, round(height*.61 if not portrait else height*.36), round(width*.35 if not portrait else width*.82), 120, 34, bg, 500)
        label("value", item["value"], pad, round(height*.82), round(width*.35 if not portrait else width*.75), 100, 58, bg, 800)

    return {"format": "vellum", "version": 1, "name": f'{item["name"]} {variation} · {orientation.title()}',
            "pages": [{"id": f"page-{family}-{variation}-{orientation}", "name": item["name"], "nodes": nodes}],
            "pageId": f"page-{family}-{variation}-{orientation}", "assets": {}, "assetFiles": {aid: item["image"]},
            "fonts": {}, "components": {}, "tokens": {"colors": [], "typography": []}}


def cover_crop(source: Image.Image, size: tuple[int, int]) -> Image.Image:
    scale = max(size[0] / source.width, size[1] / source.height)
    resized = source.resize((round(source.width*scale), round(source.height*scale)), Image.Resampling.LANCZOS)
    left, top = (resized.width-size[0])//2, (resized.height-size[1])//2
    return resized.crop((left, top, left+size[0], top+size[1]))


def render_preview(doc: dict, target: Path) -> None:
    nodes = doc["pages"][0]["nodes"]
    root = nodes[0]; scale = min(720/root["w"], 720/root["h"])
    w, h = round(root["w"]*scale), round(root["h"]*scale)
    canvas = Image.new("RGBA", (w, h), root["fill"]); d = ImageDraw.Draw(canvas, "RGBA")
    for n in nodes[1:]:
        x, y, nw, nh = [round(n[k]*scale) for k in ("x", "y", "w", "h")]
        opacity = round(255*float(n.get("opacity", 1)))
        if n["type"] == "image":
            filename = doc["assetFiles"][n["assetId"]]
            with Image.open(IMAGE_DIR/filename) as source:
                patch = cover_crop(source.convert("RGBA"), (nw, nh))
            canvas.alpha_composite(patch, (x, y))
        elif n["type"] == "rect":
            color = n["fill"] + f"{opacity:02x}" if len(n["fill"]) == 7 else n["fill"]
            d.rounded_rectangle((x, y, x+nw, y+nh), radius=round(n.get("radius", 0)*scale), fill=color)
        elif n["type"] == "text":
            f = font(round(n["fontSize"]*scale), n["fontWeight"] >= 700)
            lines = n["text"].split("\n"); line_h = round(n["fontSize"]*n.get("lineHeight", 1.04)*scale)
            for idx, line in enumerate(lines):
                box = d.textbbox((0, 0), line, font=f); tw = box[2]-box[0]
                tx = x if n.get("textAlign") == "left" else x+nw-tw if n.get("textAlign") == "right" else x+(nw-tw)//2
                d.text((tx, y+idx*line_h), line, font=f, fill=n["fill"])
    canvas.convert("RGB").save(target, "WEBP", quality=84, method=6)


def main() -> None:
    IMAGE_DIR.mkdir(parents=True, exist_ok=True); DOC_DIR.mkdir(parents=True, exist_ok=True)
    build_source_images()
    (DOC_DIR/"catalog.json").write_text(json.dumps(CATALOG, indent=2, ensure_ascii=False)+"\n", encoding="utf-8")
    for family in CATALOG:
        for variation in range(1, 5):
            for orientation in ("landscape", "portrait"):
                key = f"{family}-{variation}-{orientation}"
                doc = make_document(family, variation, orientation)
                (DOC_DIR/f"{key}.json").write_text(json.dumps(doc, separators=(",", ":"), ensure_ascii=False), encoding="utf-8")
                render_preview(doc, IMAGE_DIR/f"{key}.webp")


if __name__ == "__main__":
    main()

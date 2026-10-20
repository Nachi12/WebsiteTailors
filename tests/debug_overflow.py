import subprocess
import time
import os
import sys
import json
import base64

sys.path.insert(0, os.path.dirname(__file__))
from cdp_client import SimpleCDP

CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
URL = "http://127.0.0.1:8080/"

proc = subprocess.Popen([
    CHROME,
    "--headless=new",
    "--remote-debugging-port=9227",
    "--no-sandbox",
    "--disable-gpu",
    "--user-data-dir=/tmp/chrome_hero_debug_2",
    "--window-size=320,800",
    URL
])

time.sleep(2.5)

try:
    cdp = SimpleCDP(9227)
    cdp.connect()
    
    cdp.send_cmd("Emulation.setDeviceMetricsOverride", {
        "width": 320,
        "height": 700,
        "deviceScaleFactor": 1,
        "mobile": True
    })
    time.sleep(0.5)
    
    res = cdp.evaluate("""
    (() => {
        const w = window.innerWidth;
        const hero = document.querySelector('.hero');
        const elements = [
            '.hero-eyebrow-pill',
            '.hero-heading',
            '.hero-heading .hero-highlight',
            '.hero-subheading',
            '.hero-ctas',
            '.hero-ctas .btn-primary',
            '.hero-ctas .btn-secondary',
            '.hero-trust-row'
        ];
        
        const details = {};
        elements.forEach(sel => {
            const el = document.querySelector(sel);
            if (!el) {
                details[sel] = null;
                return;
            }
            const r = el.getBoundingClientRect();
            const s = window.getComputedStyle(el);
            details[sel] = {
                text: el.innerText.trim().slice(0, 40),
                rect: { left: Math.round(r.left), right: Math.round(r.right), width: Math.round(r.width), height: Math.round(r.height) },
                overflowsWindow: r.right > w,
                fontSize: s.fontSize,
                lineHeight: s.lineHeight,
                textTransform: s.textTransform,
                fontFamily: s.fontFamily,
                whiteSpace: s.whiteSpace,
                display: s.display
            };
        });
        
        return details;
    })()
    """)
    
    print(json.dumps(res, indent=2))
    
    # Capture screenshot via CDP
    screenshot_res = cdp.send_cmd("Page.captureScreenshot", {"format": "png"})
    if "data" in screenshot_res.get("result", {}):
        img_data = base64.b64decode(screenshot_res["result"]["data"])
        out_path = "/Users/apple/.gemini/antigravity-ide/brain/8950b8b5-001e-4f75-bd6a-696a2b914383/cdp_320_hero.png"
        with open(out_path, "wb") as f:
            f.write(img_data)
        print("Saved screenshot to", out_path)
finally:
    proc.terminate()

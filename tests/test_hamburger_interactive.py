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

def run_test():
    proc = subprocess.Popen([
        CHROME,
        "--headless=new",
        "--remote-debugging-port=9231",
        "--no-sandbox",
        "--disable-gpu",
        "--user-data-dir=/tmp/chrome_hamburger_test3",
        "--window-size=390,844",
        URL
    ])
    
    time.sleep(3.5)
    try:
        cdp = SimpleCDP(9231)
        cdp.connect()

        cdp.send_cmd("Emulation.setDeviceMetricsOverride", {
            "width": 390,
            "height": 844,
            "deviceScaleFactor": 1,
            "mobile": True
        })
        time.sleep(0.5)

        # Force loader hidden if any remains
        cdp.evaluate("const l = document.getElementById('loader'); if (l) l.style.display = 'none';")
        time.sleep(0.5)

        # 1. Closed state screenshot
        shot1 = cdp.send_cmd('Page.captureScreenshot', {'format': 'png'})
        with open('/Users/apple/.gemini/antigravity-ide/brain/8950b8b5-001e-4f75-bd6a-696a2b914383/menu_closed_390.png', 'wb') as f:
            f.write(base64.b64decode(shot1['result']['data']))

        # 2. Click to open
        cdp.evaluate("document.getElementById('menuButton').click()")
        time.sleep(0.6)

        # 3. Open state screenshot
        shot2 = cdp.send_cmd('Page.captureScreenshot', {'format': 'png'})
        with open('/Users/apple/.gemini/antigravity-ide/brain/8950b8b5-001e-4f75-bd6a-696a2b914383/menu_open_390.png', 'wb') as f:
            f.write(base64.b64decode(shot2['result']['data']))

        # 4. Click to close again
        cdp.evaluate("document.getElementById('menuButton').click()")
        time.sleep(0.6)

        # 5. Closed again screenshot
        shot3 = cdp.send_cmd('Page.captureScreenshot', {'format': 'png'})
        with open('/Users/apple/.gemini/antigravity-ide/brain/8950b8b5-001e-4f75-bd6a-696a2b914383/menu_closed_again_390.png', 'wb') as f:
            f.write(base64.b64decode(shot3['result']['data']))

        print("Visual screenshots captured!")

    finally:
        proc.terminate()

if __name__ == "__main__":
    run_test()

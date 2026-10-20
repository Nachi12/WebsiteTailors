import subprocess
import json
import time
import os

CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
URL = "http://127.0.0.1:8080/"
ARTIFACT_DIR = "/Users/apple/.gemini/antigravity-ide/brain/8950b8b5-001e-4f75-bd6a-696a2b914383"

WIDTHS = [320, 360, 375, 390, 430, 768, 1024, 1280]

js_inspect = """
(() => {
    const hero = document.querySelector('.hero');
    const heading = document.querySelector('.hero-heading');
    const highlight = document.querySelector('.hero-highlight');
    const subheading = document.querySelector('.hero-subheading');
    const btnPrimary = document.querySelector('.hero-ctas .btn-primary');
    const btnSecondary = document.querySelector('.hero-ctas .btn-secondary');
    const trustItems = Array.from(document.querySelectorAll('.trust-item')).map(el => el.innerText.trim());
    
    // Check overflow
    const docScrollW = document.documentElement.scrollWidth;
    const winInnerW = window.innerWidth;
    
    // Find any overflowing elements
    const overflowing = [];
    document.querySelectorAll('*').forEach(el => {
        const rect = el.getBoundingClientRect();
        if (rect.right > winInnerW + 1) {
            overflowing.push({
                tag: el.tagName,
                cls: el.className,
                id: el.id,
                right: rect.right,
                winW: winInnerW
            });
        }
    });

    const getComputed = (el) => {
        if (!el) return null;
        const s = window.getComputedStyle(el);
        const r = el.getBoundingClientRect();
        return {
            fontFamily: s.fontFamily,
            fontSize: s.fontSize,
            lineHeight: s.lineHeight,
            fontWeight: s.fontWeight,
            color: s.color,
            background: s.backgroundColor,
            rect: { top: r.top, left: r.left, width: r.width, height: r.height, bottom: r.bottom, right: r.right }
        };
    };

    return {
        viewport: { width: window.innerWidth, height: window.innerHeight },
        docScrollW,
        winInnerW,
        hasHorizontalOverflow: docScrollW > winInnerW,
        overflowingCount: overflowing.length,
        overflowingSample: overflowing.slice(0, 5),
        hero: getComputed(hero),
        heading: {
            text: heading ? heading.innerText.trim() : null,
            computed: getComputed(heading)
        },
        highlight: {
            text: highlight ? highlight.innerText.trim() : null,
            computed: getComputed(highlight)
        },
        subheading: {
            text: subheading ? subheading.innerText.trim() : null,
            computed: getComputed(subheading)
        },
        btnPrimary: {
            text: btnPrimary ? btnPrimary.innerText.trim() : null,
            href: btnPrimary ? btnPrimary.getAttribute('href') : null,
            computed: getComputed(btnPrimary)
        },
        btnSecondary: {
            text: btnSecondary ? btnSecondary.innerText.trim() : null,
            href: btnSecondary ? btnSecondary.getAttribute('href') : null,
            computed: getComputed(btnSecondary)
        },
        trustItems
    };
})()
"""

# Test one breakpoint first with Chrome headless CDP or screenshot/dump
print("Starting breakpoint verification...")
for w in WIDTHS:
    h = 800 if w >= 768 else 750
    screenshot_path = os.path.join(ARTIFACT_DIR, f"hero_vp_{w}.png")
    cmd = [
        CHROME,
        "--headless=new",
        "--no-sandbox",
        "--disable-gpu",
        "--hide-scrollbars",
        f"--window-size={w},{h}",
        f"--screenshot={screenshot_path}",
        URL
    ]
    subprocess.run(cmd, capture_output=True, timeout=15)
    print(f"Captured screenshot at {w}px -> {screenshot_path}")

print("Screenshots captured successfully.")

import json
import urllib.request
import subprocess
import time
import socket
import struct
import base64
import os

class SimpleCDP:
    def __init__(self, port=9222):
        self.port = port
        self.ws = None
        self.msg_id = 0

    def connect(self):
        url = f"http://127.0.0.1:{self.port}/json"
        req = urllib.request.urlopen(url)
        tabs = json.loads(req.read().decode())
        page_tab = next((t for t in tabs if t.get("type") == "page" and "localhost" in t.get("url", "")), None)
        if not page_tab:
            page_tab = next(t for t in tabs if t.get("type") == "page")
        ws_url = page_tab["webSocketDebuggerUrl"]
        # parse ws://127.0.0.1:port/devtools/page/...
        path = ws_url.split(f":{self.port}")[1]
        
        self.sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        self.sock.connect(("127.0.0.1", self.port))
        
        # Handshake
        key = base64.b64encode(os.urandom(16)).decode()
        handshake = (
            f"GET {path} HTTP/1.1\r\n"
            f"Host: 127.0.0.1:{self.port}\r\n"
            f"Upgrade: websocket\r\n"
            f"Connection: Upgrade\r\n"
            f"Sec-WebSocket-Key: {key}\r\n"
            f"Sec-WebSocket-Version: 13\r\n\r\n"
        )
        self.sock.sendall(handshake.encode())
        resp = self.sock.recv(4096)
        if b"101" not in resp:
            raise Exception("WS handshake failed")

    def send_cmd(self, method, params=None):
        self.msg_id += 1
        msg = json.dumps({"id": self.msg_id, "method": method, "params": params or {}})
        # Send unmasked or masked frame (client to server must be masked)
        data = msg.encode()
        frame = bytearray([0x81]) # FIN + text
        mask = os.urandom(4)
        length = len(data)
        if length <= 125:
            frame.append(0x80 | length)
        elif length <= 65535:
            frame.append(0x80 | 126)
            frame.extend(struct.pack(">H", length))
        else:
            frame.append(0x80 | 127)
            frame.extend(struct.pack(">Q", length))
        frame.extend(mask)
        masked_data = bytes(b ^ mask[i % 4] for i, b in enumerate(data))
        frame.extend(masked_data)
        self.sock.sendall(frame)

        # Read response
        return self._read_msg(self.msg_id)

    def _read_msg(self, target_id):
        while True:
            head = self.sock.recv(2)
            if not head:
                return None
            length = head[1] & 0x7F
            if length == 126:
                length = struct.unpack(">H", self.sock.recv(2))[0]
            elif length == 127:
                length = struct.unpack(">Q", self.sock.recv(8))[0]
            payload = bytearray()
            while len(payload) < length:
                chunk = self.sock.recv(length - len(payload))
                if not chunk: break
                payload.extend(chunk)
            data = json.loads(payload.decode(errors="ignore"))
            if data.get("id") == target_id:
                return data

    def evaluate(self, expr):
        res = self.send_cmd("Runtime.evaluate", {"expression": expr, "returnByValue": True, "awaitPromise": True})
        return res.get("result", {}).get("result", {}).get("value")

    def close(self):
        try:
            self.sock.close()
        except:
            pass

if __name__ == "__main__":
    print("CDP helper ready")

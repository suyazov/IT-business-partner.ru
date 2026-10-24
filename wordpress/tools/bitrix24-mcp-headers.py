#!/usr/bin/env python3
"""Supply official Bitrix24 MCP authentication without secrets in Codex config."""
import json
import os
import stat
import sys

PATH = '/root/.config/client-access/sy3.ru/bitrix24-mcp.json'

try:
    fd = os.open(PATH, os.O_RDONLY | os.O_NOFOLLOW)
    with os.fdopen(fd) as stream:
        info = os.fstat(stream.fileno())
        if (info.st_uid != 0 or stat.S_IMODE(info.st_mode) != 0o600
                or not stat.S_ISREG(info.st_mode) or info.st_nlink != 1 or info.st_size > 8192):
            raise ValueError()
        value = json.load(stream)
    token = value['authorization']
    if (value.get('url') != 'https://mcp.bitrix24.tech/mcp/'
            or value.get('portal') != 'https://sy3.bitrix24.ru/'
            or not isinstance(token, str) or not token.startswith('Bearer ')
            or not 64 < len(token) < 4096 or any(c.isspace() for c in token[7:])):
        raise ValueError()
    print(json.dumps({'Authorization': token}))
except Exception:
    print('SY3 Bitrix24 MCP credential unavailable', file=sys.stderr)
    raise SystemExit(1)

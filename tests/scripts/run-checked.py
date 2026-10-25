#!/usr/bin/env python3
"""Keep native logs and sticky receipts owned after the PHP process exits."""
import os
from pathlib import Path
import subprocess
import sys
import tempfile


def main():
    root = Path(__file__).resolve().parents[2]
    environment = dict(os.environ)
    directory = None
    if not environment.get('LOW_SEG2CAT_DIAGNOSTIC_JOURNAL') or not environment.get('LOW_SEG2CAT_NATIVE_ERROR_LOG'):
        directory = Path(tempfile.mkdtemp(prefix='low-seg2cat-checked-'))
    for key, filename in [('LOW_SEG2CAT_DIAGNOSTIC_JOURNAL', 'diagnostics.jsonl'),
                          ('LOW_SEG2CAT_NATIVE_ERROR_LOG', 'native-errors.log')]:
        if not environment.get(key):
            environment[key] = str(directory / filename)
            Path(environment[key]).touch(mode=0o600)
    command = [sys.argv[1], '-d', 'auto_prepend_file=' + str(root / 'tests/Support/diagnostics.php'),
               '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'display_startup_errors=1',
               '-d', 'html_errors=0', '-d', 'log_errors=1',
               '-d', 'error_log=' + environment['LOW_SEG2CAT_NATIVE_ERROR_LOG'], *sys.argv[2:]]
    result = subprocess.run(command, env=environment)
    invalid = any(Path(environment[key]).read_bytes() for key in
                  ['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL', 'LOW_SEG2CAT_NATIVE_ERROR_LOG'])
    if invalid:
        print('LOW_SEG2CAT_NATIVE_FAILURE: retained PHP diagnostics reject the checked process.', file=sys.stderr)
    return result.returncode or (70 if invalid else 0)


if __name__ == '__main__':
    sys.exit(main())

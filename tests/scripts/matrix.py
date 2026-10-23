#!/usr/bin/env python3
"""Run the whole suite on explicit Herd runtimes, rejecting empty and stale reports."""
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parents[2]
herd = Path.home() / 'Library/Application Support/Herd/bin'
report = root / 'tests/reports'
report.mkdir(exist_ok=True)
run = Path(tempfile.mkdtemp(prefix='matrix-', dir=report))
results = []
for version in ('74', '80', '81', '82', '83', '84', '85'):
    binary = herd / ('php' + version)
    junit = run / ('php' + version + '.xml')
    command = [str(root / 'tests/.phpunit/run-phpunit.sh'), '-c', str(root / 'phpunit.xml'), '--log-junit', str(junit)]
    env = dict(os.environ, PHP_BINARY=str(binary))
    completed = subprocess.run(command, cwd=root, env=env, capture_output=True, text=True)
    (run / ('php' + version + '.log')).write_text(completed.stdout + completed.stderr)
    runtime = subprocess.check_output([str(binary), '-r', 'echo PHP_VERSION;'], text=True)
    if not junit.is_file():
        raise RuntimeError('No fresh JUnit report for PHP ' + version)
    suite = ET.parse(junit).getroot().find('testsuite')
    if suite is None or int(suite.attrib['tests']) == 0:
        raise RuntimeError('Empty suite for PHP ' + version)
    risky = sum('RiskyTest' in error.attrib.get('type', '') for error in suite.iter('error'))
    incomplete = sum('IncompleteTest' in skip.attrib.get('type', '') for skip in suite.iter('skipped'))
    record = dict(version=runtime, status=completed.returncode, command=command, risky=risky, incomplete=incomplete, **suite.attrib)
    results.append(record)
    print(json.dumps(record), flush=True)
(run / 'matrix.json').write_text(json.dumps(results, indent=2) + '\n')
print('Reports: ' + str(run), flush=True)
sys.exit(1 if any(result['status'] != 0 for result in results) else 0)

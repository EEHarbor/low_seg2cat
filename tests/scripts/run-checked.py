#!/usr/bin/env python3
"""Retain native channels and reject PHP-framed diagnostics after checked exit."""
import base64
import hashlib
import html
import json
import os
from pathlib import Path
import re
import secrets
import selectors
import subprocess
import sys
import tempfile


def native_frames(output, root, command, sources=None):
    """Recognize complete PHP frames, never bare Warning prose or HTML markup."""
    kinds = r'(Warning|Notice|Deprecated|Fatal error|Parse error|Recoverable fatal error|Strict Standards)'
    labels = kinds[1:-1]
    plain_body = r'((?:(?!\r?\n(?:PHP )?(?:' + labels + r'): )[\s\S])*?)'
    html_body = r'((?:(?!<b>(?:' + labels + r')</b>:)[\s\S])*?)'
    patterns = [r'(?m)^(?:PHP )?' + kinds + ': ' + plain_body + r' in ([^\r\n]+) on line ([1-9][0-9]*)\r?$',
                r'<b>' + kinds + r'</b>:\s*' + html_body + r' in <b>([^<\r\n]+)</b> on line <b>([1-9][0-9]*)</b><br\s*/?>']
    frames = []
    inline = any(arg in ('-r', '--run') or (arg.startswith('-r') and len(arg) > 2)
                 or arg.startswith('--run=') for arg in command)
    for pattern in patterns:
        for match in re.finditer(pattern, output.decode('utf-8', 'surrogateescape')):
            kind, message, filename, line = match.groups()
            filename = html.unescape(filename)
            path = Path(filename)
            verified = False
            digest = None
            if sources and filename in sources:
                source = sources[filename]
                verified = int(line) <= len(source.splitlines())
                digest = hashlib.sha256(source).hexdigest()
            elif filename == 'Command line code' and inline:
                verified = True
            elif path.is_file():
                resolved = path.resolve()
                owned = resolved == root or root in resolved.parents or any(
                    os.path.isfile(arg) and Path(arg).resolve() == resolved for arg in command)
                if owned:
                    source = path.read_bytes()
                    verified = int(line) <= len(source.splitlines())
                    digest = hashlib.sha256(source).hexdigest()
            if verified:
                frames.append({'severity': kind, 'message': html.unescape(message), 'file': filename,
                               'line': int(line), 'source_sha256': digest,
                               'raw_base64': base64.b64encode(match.group(0).encode('utf-8', 'surrogateescape')).decode()})
    return frames


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
    reports = Path(environment['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL']).parent / 'checked-captures'
    reports.mkdir(exist_ok=True)
    capture = Path(tempfile.mkdtemp(prefix='checked-', dir=reports))
    tool_environment = dict(environment)
    for name in ('LOW_SEG2CAT_CASE_OUTPUT', 'LOW_SEG2CAT_CASE_NONCE'):
        tool_environment.pop(name, None)
    for key, filename in [('LOW_SEG2CAT_DIAGNOSTIC_JOURNAL', 'tool-diagnostics.bin'),
                          ('LOW_SEG2CAT_NATIVE_ERROR_LOG', 'tool-engine.bin')]:
        tool_environment[key] = str(capture / filename)
        (capture / filename).touch(mode=0o600)
    preparation = [sys.argv[1], '-d', 'phar.readonly=0', '-d', 'auto_prepend_file=' + str(root / 'tests/Support/diagnostics.php'),
                   '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'display_startup_errors=1',
                   '-d', 'log_errors=1', '-d', 'error_log=' + tool_environment['LOW_SEG2CAT_NATIVE_ERROR_LOG'],
                   str(root / 'tests/.phpunit/prepare-output.php')]
    cached = root / 'tests/.phpunit/phpunit-9-output.phar.json'
    tool_pid = None
    if cached.is_file():
        tool_status = 0
        (capture / 'tool-stdout.bin').write_bytes(cached.read_bytes())
        (capture / 'tool-stderr.bin').touch(mode=0o600)
    else:
        with (capture / 'tool-stdout.bin').open('wb') as stdout, (capture / 'tool-stderr.bin').open('wb') as stderr:
            tool_child = subprocess.Popen(preparation, env=tool_environment, stdout=stdout, stderr=stderr)
            tool_pid = tool_child.pid
            tool_status = tool_child.wait()
    tool_record = {'command_base64': [base64.b64encode(os.fsencode(arg)).decode() for arg in preparation],
                   'pid': tool_pid, 'status': tool_status, 'cached_artifact_verified_before_launch': cached.is_file()}
    (capture / 'tool-issued.json').write_text(json.dumps(tool_record, indent=2) + '\n')
    if tool_status or any((capture / filename).read_bytes() for filename in
                          ('tool-stderr.bin', 'tool-diagnostics.bin', 'tool-engine.bin')):
        for key, filename in [('LOW_SEG2CAT_DIAGNOSTIC_JOURNAL', 'tool-diagnostics.bin'),
                              ('LOW_SEG2CAT_NATIVE_ERROR_LOG', 'tool-engine.bin')]:
            with Path(environment[key]).open('ab') as retained:
                retained.write((capture / filename).read_bytes())
        sys.stdout.buffer.write((capture / 'tool-stdout.bin').read_bytes())
        sys.stderr.buffer.write((capture / 'tool-stderr.bin').read_bytes())
        return tool_status or 70
    tool = json.loads((capture / 'tool-stdout.bin').read_bytes())
    artifact = root / 'tests/.phpunit/phpunit-9-output.phar'
    if (tool['original_sha256'] != '6b5235f282c6ccab3674ac27c5b1ddbd1da838e74bf07002ddcfd030d31298d7'
            or tool['recipe_sha256'] != hashlib.sha256((root / 'tests/.phpunit/prepare-output.php').read_bytes()).hexdigest()
            or tool['artifact_sha256'] != hashlib.sha256(artifact.read_bytes()).hexdigest()):
        raise RuntimeError('Derived PHPUnit identity changed before launch.')
    original = root / 'tests/.phpunit/phpunit-9.phar'
    if len(sys.argv) > 2 and sys.argv[2] == str(original):
        command[-len(sys.argv[2:])] = str(artifact)
    (capture / 'case-output.jsonl').touch(mode=0o600)
    (capture / 'case-inputs').mkdir(mode=0o700)
    body_identity = (capture / 'case-output.jsonl').stat()
    nonce = secrets.token_hex(32)
    environment['LOW_SEG2CAT_CASE_OUTPUT'] = str(capture / 'case-output.jsonl')
    environment['LOW_SEG2CAT_CASE_NONCE'] = nonce
    (capture / 'tool-identity.json').write_text(json.dumps(tool, indent=2) + '\n')
    (capture / 'issued.json').write_text(json.dumps({'owner_pid': os.getpid(),
        'command_base64': [base64.b64encode(os.fsencode(arg)).decode() for arg in command],
        'journal': environment['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL'],
        'engine_log': environment['LOW_SEG2CAT_NATIVE_ERROR_LOG']}, indent=2) + '\n')
    with (capture / 'stdout.bin').open('wb') as stdout, (capture / 'stderr.bin').open('wb') as stderr:
        child = subprocess.Popen(command, env=environment, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        (capture / 'spawn.json').write_text(json.dumps({'pid': child.pid}) + '\n')
        with selectors.DefaultSelector() as channels:
            channels.register(child.stdout, selectors.EVENT_READ, (stdout, sys.stdout.buffer))
            channels.register(child.stderr, selectors.EVENT_READ, (stderr, sys.stderr.buffer))
            while channels.get_map():
                for key, _ in channels.select():
                    data = os.read(key.fileobj.fileno(), 65536)
                    if not data:
                        channels.unregister(key.fileobj)
                        key.fileobj.close()
                        continue
                    retained, forwarded = key.data
                    retained.write(data)
                    retained.flush()
                    forwarded.write(data)
                    forwarded.flush()
        status = child.wait()
    output = (capture / 'stdout.bin').read_bytes()
    errors = (capture / 'stderr.bin').read_bytes()
    frames = [dict(frame, channel=channel) for channel, content in [('stdout', output), ('stderr', errors)]
              for frame in native_frames(content, root, command)]
    case_records = []
    case_failure = None
    try:
        if (capture / 'case-output.jsonl').is_symlink():
            raise ValueError('Original-case output channel became a symlink.')
        current_identity = (capture / 'case-output.jsonl').stat()
        if (body_identity.st_dev, body_identity.st_ino) != (current_identity.st_dev, current_identity.st_ino):
            raise ValueError('Original-case output channel was replaced.')
        bound = set()
        for line in (capture / 'case-output.jsonl').read_bytes().splitlines():
            record = json.loads(line)
            if record['nonce'] != nonce or not isinstance(record['pid'], int) or record['pid'] <= 0:
                raise ValueError('Original-case output owner differs.')
            if record['kind'] == 'bind':
                if record['artifact_sha256'] != tool['artifact_sha256']:
                    raise ValueError('Original-case derived tool differs.')
                bound.add(record['pid'])
                continue
            if (record['kind'] != 'case' or record['pid'] not in bound
                    or record['stage'] not in ('original', 'callback')):
                raise ValueError('Original-case output has no startup owner.')
            body = base64.b64decode(record['body_base64'], validate=True)
            digest = record['source_sha256']
            if not re.fullmatch('[0-9a-f]{64}', digest):
                raise ValueError('Original-case source identity is invalid.')
            source_path = capture / 'case-inputs' / digest
            if source_path.is_symlink():
                raise ValueError('Original-case source receipt became a symlink.')
            source = source_path.read_bytes()
            if (hashlib.sha256(source).hexdigest() != digest or len(body) != record['bytes']
                    or hashlib.sha256(body).hexdigest() != record['sha256']):
                raise ValueError('Original-case source or output changed.')
            case_frames = native_frames(body, root, command, {record['file']: source})
            frames.extend(dict(frame, channel='case-body', pid=record['pid'],
                               case=record['class'] + '::' + record['name'], stage=record['stage']) for frame in case_frames)
            case_records.append({'pid': record['pid'], 'case': record['class'] + '::' + record['name'],
                                 'stage': record['stage'], 'source_sha256': digest, 'bytes': len(body), 'sha256': record['sha256'],
                                 'native_frames': len(case_frames)})
        if child.pid not in bound:
            raise ValueError('Checked PHP never bound its original-output channel.')
        if hashlib.sha256(artifact.read_bytes()).hexdigest() != tool['artifact_sha256']:
            raise ValueError('Derived PHPUnit changed during execution.')
    except (ValueError, KeyError, TypeError, OSError) as error:
        case_failure = str(error)
    if frames and not any(Path(environment[key]).read_bytes() for key in
                          ['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL', 'LOW_SEG2CAT_NATIVE_ERROR_LOG']):
        with Path(environment['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL']).open('ab') as journal:
            for frame in frames:
                event = [2, 'Native output diagnostic: ' + frame['severity'] + ': ' + frame['message'],
                         frame['file'], frame['line']]
                journal.write((json.dumps(event, ensure_ascii=True) + '\n').encode())
    invalid = bool(frames) or case_failure is not None or any(Path(environment[key]).read_bytes() for key in
                  ['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL', 'LOW_SEG2CAT_NATIVE_ERROR_LOG'])
    if case_failure:
        with Path(environment['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL']).open('ab') as journal:
            journal.write((json.dumps([2, 'Original-case capture failure: ' + case_failure, __file__, 1]) + '\n').encode())
    for filename, key in [('diagnostics.bin', 'LOW_SEG2CAT_DIAGNOSTIC_JOURNAL'),
                          ('native-errors.bin', 'LOW_SEG2CAT_NATIVE_ERROR_LOG')]:
        (capture / filename).write_bytes(Path(environment[key]).read_bytes())
    terminal = {'diagnostics_sha256': hashlib.sha256(Path(environment['LOW_SEG2CAT_DIAGNOSTIC_JOURNAL']).read_bytes()).hexdigest(),
                'native_log_sha256': hashlib.sha256(Path(environment['LOW_SEG2CAT_NATIVE_ERROR_LOG']).read_bytes()).hexdigest(),
                'pid': child.pid, 'returncode': status, 'reaped': True,
                'native_output_frames': frames, 'rejected': bool(invalid),
                'case_output_records': case_records, 'case_output_failure': case_failure,
                'case_output_sha256': hashlib.sha256((capture / 'case-output.jsonl').read_bytes()).hexdigest(),
                'tool_identity': tool,
                'streams': {name: {'sha256': hashlib.sha256((capture / name).read_bytes()).hexdigest(),
                                  'bytes': (capture / name).stat().st_size}
                            for name in ('stdout.bin', 'stderr.bin', 'diagnostics.bin', 'native-errors.bin')}}
    (capture / 'terminal.json').write_text(json.dumps(terminal, indent=2, ensure_ascii=True) + '\n')
    if invalid:
        print('LOW_SEG2CAT_NATIVE_FAILURE: retained PHP diagnostics reject the checked process.', file=sys.stderr)
    return status or (70 if invalid else 0)


if __name__ == '__main__':
    sys.exit(main())

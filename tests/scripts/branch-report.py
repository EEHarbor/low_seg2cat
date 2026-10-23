#!/usr/bin/env python3
"""Describe one unmerged compiled graph; never equate blocks with branch outcomes."""
import argparse
import hashlib
import json
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('raw', type=Path)
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--recorded-root', type=Path, help='Original checkout root encoded in the raw Xdebug file keys.')
args = parser.parse_args()
root = Path(__file__).resolve().parents[2]
recorded_root = args.recorded_root.resolve() if args.recorded_root else root
raw = json.loads(args.raw.read_text())
inventory = json.loads((root / 'tests/SOURCE_INVENTORY.json').read_text())
records = []
for name, entry in inventory.items():
    if '::' in name:
        archive, member = name.split('::', 1)
        key = str(recorded_root / archive) if member == '{stub}' else 'phar://' + str(recorded_root / archive) + '/' + member
    else:
        key = str(recorded_root / name)
    source = raw.get(key)
    record = dict(file=name, sha256=entry['sha256'], measured=source is not None, functions=[])
    if source is None:
        record['unmeasured_declarations'] = entry.get('functions', [])
        records.append(record)
        continue
    lines = {int(line): hit for line, hit in source['lines'].items() if hit != -2}
    record['line_covered'] = sum(hit > 0 for hit in lines.values())
    record['line_executable'] = len(lines)
    record['uncovered_lines'] = [line for line, hit in lines.items() if hit == -1]
    for function, graph in (source.get('functions') or {}).items():
        branches = graph.get('branches') or {}
        pairs = list(branches.items()) if isinstance(branches, dict) else list(enumerate(branches))
        edges = []
        blocks = []
        for index, branch in pairs:
            blocks.append(dict(opcode=index, line_start=branch['line_start'], line_end=branch['line_end'], hit=branch['hit']))
            exits = branch.get('out', [])
            hits = branch.get('out_hit', [])
            exits = exits.items() if isinstance(exits, dict) else enumerate(exits)
            for exit_index, target in exits:
                hit = hits.get(str(exit_index)) if isinstance(hits, dict) else (hits[int(exit_index)] if int(exit_index) < len(hits) else None)
                edges.append(dict(source=index, target=target, line=branch['line_end'], hit=hit,
                                  synthetic_exit=target in (2147483645, 2147483646, 2147483647)))
        outcomes = [edge for edge in edges if not edge['synthetic_exit']]
        record['functions'].append(dict(name=function, basic_block_covered=sum(bool(block['hit']) for block in blocks),
            basic_block_total=len(blocks), outcome_covered=sum(bool(edge['hit']) for edge in outcomes),
            outcome_total=len(outcomes), blocks=blocks, exits=edges,
            branch_status='unknown' if any(edge['hit'] is None for edge in outcomes) else 'measured',
            synthetic_exit_limitation='Retained separately; Xdebug may report zero for a taken VM exit.'))
    records.append(record)
if not any(record['measured'] for record in records):
    raise RuntimeError('No inventoried source matched this graph. Set --recorded-root to its original checkout root.')
result = dict(raw_sha256=hashlib.sha256(args.raw.read_bytes()).hexdigest(), recorded_root=str(recorded_root), files=records,
              note='One compiled graph only. Basic block hits are not branch proof. Out/out_hit are retained. Missing source/metrics remain unknown; no full-path target.')
args.output.write_text(json.dumps(result, indent=2) + '\n')
for record in records:
    if not record['measured']:
        print(record['file'], 'UNMEASURED')
        continue
    functions = record['functions']
    outcomes = sum(function['outcome_total'] for function in functions)
    covered = sum(function['outcome_covered'] for function in functions)
    print(record['file'], f"lines {record['line_covered']}/{record['line_executable']}", f"outcomes {covered}/{outcomes}")

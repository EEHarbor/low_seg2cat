#!/usr/bin/env python3
"""Synthetic provenance controls; these fixtures are never production coverage."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
UNIT = 'low_seg2cat/low_seg2cat.phar::Views/license.php'


class PrimaryMeasurementTest(unittest.TestCase):
    def setUp(self):
        self.owned = tempfile.TemporaryDirectory(prefix='low-seg2cat-graph-control-')
        self.addCleanup(self.owned.cleanup)
        self.directory = Path(self.owned.name)
        self.raw = self.directory / 'raw.json'
        self.metadata = self.directory / 'metadata.json'
        self.output = self.directory / 'derived.json'
        self.raw.write_text(json.dumps({'/owned/identical-license.php': {'lines': {'1': 1}, 'functions': {}}}))
        inventory = json.loads((ROOT / 'tests/SOURCE_INVENTORY.json').read_text())
        digest = inventory[UNIT]['sha256']
        self.provenance = {'raw_sha256': hashlib.sha256(self.raw.read_bytes()).hexdigest(), 'measurement_source': {
            'inventory_name': UNIT, 'original_source': 'phar://' + str(ROOT / 'low_seg2cat/low_seg2cat.phar/Views/license.php'),
            'original_sha256': digest, 'measurement_sha256': digest, 'measured_source': '/owned/identical-license.php'}}

    def run_report(self):
        self.metadata.write_text(json.dumps(self.provenance))
        return subprocess.run([sys.executable, str(ROOT / 'tests/scripts/branch-report.py'), str(self.raw),
                               '--metadata', str(self.metadata), '--output', str(self.output)], capture_output=True, text=True)

    def test_verified_copy_maps_only_to_its_original_inventory_unit(self):
        result = self.run_report()
        self.assertEqual(0, result.returncode, result.stderr)
        files = json.loads(self.output.read_text())['files']
        self.assertEqual([UNIT], [entry['file'] for entry in files if entry['measured']])
        self.assertEqual(29, len(files))
        stub = next(entry for entry in files if entry['file'].endswith('::{stub}'))
        self.assertEqual(5, len(stub['unmeasured_declarations']))

    def test_changed_source_or_raw_hash_cannot_publish_a_derived_report(self):
        for field in ('original_sha256', 'measurement_sha256', 'raw_sha256'):
            with self.subTest(field=field):
                original = json.loads(json.dumps(self.provenance))
                target = self.provenance if field == 'raw_sha256' else self.provenance['measurement_source']
                target[field] = '0' * 64
                self.assertNotEqual(0, self.run_report().returncode)
                self.assertFalse(self.output.exists())
                self.provenance = original

    def test_unknown_missing_or_foreign_source_mapping_is_rejected(self):
        for field, value in (('inventory_name', 'unknown.php'), ('measured_source', '/absent.php'),
                             ('original_source', '/another-checkout/license.php')):
            with self.subTest(field=field):
                original = self.provenance['measurement_source'][field]
                self.provenance['measurement_source'][field] = value
                self.assertNotEqual(0, self.run_report().returncode)
                self.assertFalse(self.output.exists())
                self.provenance['measurement_source'][field] = original

    def test_explicit_missing_provenance_is_rejected(self):
        result = subprocess.run([sys.executable, str(ROOT / 'tests/scripts/branch-report.py'), str(self.raw),
                                 '--metadata', str(self.metadata), '--output', str(self.output)], capture_output=True, text=True)
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(self.output.exists())


class ConditionalDeclarationTest(unittest.TestCase):
    def measure(self, functions):
        with tempfile.TemporaryDirectory(prefix='low-seg2cat-conditional-control-') as directory:
            directory = Path(directory)
            raw = directory / 'raw.json'
            output = directory / 'derived.json'
            key = 'phar://' + str(ROOT / 'low_seg2cat/low_seg2cat.phar/FluxCapacitor/Bridge/EE.php')
            raw.write_text(json.dumps({key: {'lines': {'84': 1, '85': 1}, 'functions': functions}}))
            result = subprocess.run([sys.executable, str(ROOT / 'tests/scripts/branch-report.py'), str(raw),
                                     '--output', str(output)], capture_output=True, text=True)
            self.assertEqual(0, result.returncode, result.stderr)
            return next(row for row in json.loads(output.read_text())['files'] if row['file'].endswith('Bridge/EE.php'))

    def test_shared_constructor_graph_does_not_close_any_conditional_declaration(self):
        name = 'Low\\Seg2Cat\\FluxCapacitor\\Bridge\\EE->__construct'
        graph = {'branches': {'0': {'line_start': 42, 'line_end': 45, 'hit': 1,
                                   'out': [2147483645], 'out_hit': [0]}}}
        record = self.measure({name: graph})
        self.assertEqual([42, 50, 58, 66, 74, 82], record['graph_collisions'][0]['declaration_lines'])
        function = record['functions'][0]
        self.assertIsNone(function['basic_block_covered'])
        self.assertIsNone(function['outcome_total'])
        self.assertEqual('unknown', function['line_status'])
        self.assertEqual('unknown', function['branch_status'])
        self.assertEqual(1, function['raw_graph_metrics']['basic_block_covered'])
        self.assertEqual(2147483645, function['raw_graph_metrics']['exits'][0]['target'])

    def test_absent_constructor_graph_retains_all_six_unknown_declarations(self):
        record = self.measure({})
        self.assertEqual(6, len(record['ambiguous_declarations']))
        self.assertTrue(all(row['status'] == 'unknown' for row in record['ambiguous_declarations']))
        self.assertTrue(all(row['line_covered'] is None and row['outcome_total'] is None
                            for row in record['ambiguous_declarations']))
        self.assertEqual([], record['functions'])

    def test_single_global_function_keeps_its_own_measured_metrics(self):
        graph = {'branches': {'0': {'line_start': 20, 'line_end': 21, 'hit': 1, 'out': [], 'out_hit': []}}}
        record = self.measure({'ee': graph})
        function = record['functions'][0]
        self.assertEqual('ee', function['name'])
        self.assertEqual(1, function['basic_block_covered'])
        self.assertEqual(1, function['basic_block_total'])
        self.assertEqual('measured', function['branch_status'])
        self.assertNotIn('raw_graph_metrics', function)


if __name__ == '__main__':
    unittest.main()

<?php

namespace Tests;

use Tests\Support\Process;

class LicenseSubmissionTest extends TestCase
{
    /**
     * Submit license changes with EE's terminating redirect contract.
     *
     * @dataProvider submissions
     * @param array $posted
     * @param array $existing
     * @param string $operation
     * @param string|null $expectedKey
     * @param array|null $sites
     * @return void
     */
    public function testLicenseSubmissionScopesDatabaseChangesAndRedirects(array $posted, array $existing, $operation, $expectedKey, ?array $sites = null)
    {
        $siteRows = $sites === null ? array(array('site_label' => 'Other Site')) : $sites;
        $code = '$posted=' . var_export($posted, true) . '; $existing=' . var_export($existing, true) .
            '; $sites=' . var_export($siteRows, true) . '; ';
        $code .= <<<'CODE'
$app = ee();
$app->input = new \Tests\Support\CallbackService(array('post' => function ($key, $xss) use ($posted) { return isset($posted[$key]) ? $posted[$key] : false; }));
$app->db->rowsByTable['eeharbor_licenses'] = $existing;
$app->db->rowsByTable['sites'] = $sites;
$app->functions = new \Tests\Support\CallbackService(array('redirect' => function ($url) use ($app) { $app->redirects[] = (string) $url; exit; }));
register_shutdown_function(function () use ($app) { echo json_encode(array('calls' => $app->db->calls, 'redirects' => $app->redirects, 'alerts' => $app->alerts->calls)); });
(new \Low\Seg2Cat\FluxCapacitor\Base\Mcp())->save_license();
CODE;
        $result = Process::probe($code);
        $this->assertSame(0, $result['status'], $result['stdout'] . $result['stderr']);
        $data = json_decode($result['stdout'], true);
        $this->assertSame(array('cp://addons/settings/low_seg2cat/license'), $data['redirects']);
        $changes = array_values(array_filter($data['calls'], function ($call) {
            return in_array($call[0], array('insert', 'update', 'delete'), true) && $call[1][0] === 'eeharbor_licenses';
        }));
        if ($operation === 'none') {
            $this->assertSame(array(), $changes);
            $this->assertContains(array('asIssue', array()), $data['alerts']);
            if ($sites === array()) {
                $titles = array_values(array_filter($data['alerts'], function ($call) { return $call[0] === 'withTitle'; }));
                $this->assertStringContainsString('Could not locate MSM Site name.', $titles[0][1][0]);
            }
            return;
        }
        $this->assertCount(1, $changes);
        $this->assertSame($operation, $changes[0][0]);
        if ($operation !== 'delete') {
            $this->assertSame($expectedKey, $changes[0][1][1]['license_key']);
            $this->assertSame(1, $changes[0][1][1]['site_id']);
            $this->assertSame('Low\\Seg2Cat', $changes[0][1][1]['addon']);
            $this->assertSame(isset($posted['ignore_site']) ? (int) $posted['ignore_site'] : 0, $changes[0][1][1]['ignore_site']);
        }
    }

    /**
     * Supply normal, ignored, oversized, duplicate, update, and clearing requests.
     *
     * @return array
     */
    public static function submissions()
    {
        $row = array('site_id' => 1, 'addon' => 'Low\\Seg2Cat', 'license_key' => 'abc', 'ignore_site' => 0);
        return array(
            'sanitized insertion' => array(array('license_key' => 'ABC_ 12<script>', 'ignore_site' => '0'), array(), 'insert', 'ABC12script'),
            'ignore this site' => array(array('license_key' => '', 'ignore_site' => '1'), array(), 'insert', 'low-seg2cat-ignore-site-1'),
            'too long' => array(array('license_key' => str_repeat('a', 51), 'ignore_site' => '0'), array(), 'none', null),
            'maximum allowed key' => array(array('license_key' => str_repeat('a', 50), 'ignore_site' => '0'), array(), 'insert', str_repeat('a', 50)),
            'missing ignore checkbox' => array(array('license_key' => 'abc'), array(), 'insert', 'abc'),
            'other site duplicate' => array(array('license_key' => 'abc', 'ignore_site' => '0'), array(array_merge($row, array('site_id' => 2))), 'none', null),
            'missing duplicate MSM site' => array(array('license_key' => 'abc', 'ignore_site' => '0'), array(array_merge($row, array('site_id' => 2))), 'none', null, array()),
            'other addon duplicate' => array(array('license_key' => 'abc', 'ignore_site' => '0'), array(array_merge($row, array('addon' => 'OtherAddon'))), 'none', null),
            'existing key update' => array(array('license_key' => 'abc', 'ignore_site' => '0'), array($row), 'update', 'abc'),
            'clear ignored site' => array(array('license_key' => '', 'ignore_site' => '0'), array(array_merge($row, array('license_key' => 'low-seg2cat-ignore-site-1'))), 'delete', null),
        );
    }
}

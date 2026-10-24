<?php

namespace Low\Seg2Cat\FluxCapacitor\Conduit;

use Low\Seg2Cat\FluxCapacitor\FluxCapacitor;
use Low\Seg2Cat\FluxCapacitor\Conduit\StaticCache;

/**
 * Version class
 *
 * @package         Version
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */
class Version
{
    // Assume the license table is corrupt until we can verify otherwise.
    public $flux;
    private $table_corrupt = true;
    private $public_key = 'LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUlJQ0lqQU5CZ2txaGtpRzl3MEJBUUVGQUFPQ0FnOEFNSUlDQ2dLQ0FnRUF6UXZFMWpqdU5wZXVZUjhYY2dYbQpqamRVZ2NlT1NyY0xZRmJKNTA2TGNNQkFsMTBxV3E2aU5NRVJNbXAzNno2SWhvVG4xdVNYS2llY0NUd21ZUE9hCjZVaXZIaFJkYVlLdVMzMEN1U0wzTzJ4aVFBditrdW5aQkk3c1lsL0R5V1ljdVV1ckswdFlDSm1jUjQxd0dwU2oKYTVGblZja2hndE12d0ZWTGZXWGZ6ck5MTjdJZzJSVFdCNWlnSCtEaGlQVE5jNGRWR1FXSjI4RHBhMDBreFRjNAp3SHNGUGFHWDBndER3VzZ0ZUNnWFFzbkJmdVVSNE85bkMyVERINUZyakFrenNRa01nNDFBQnFyVFhVdWVaYkxnCjNkUUxUWm52eWxQYmRoamdEbCs5SzF3amt2dDl2Wlh1SFVaeTNneG1tYy8zaHU3M0w0RTVWRkdmRWxLZ0hiNngKbVBoajZVSlM1dWZuZ1VPcXdxNnhKZTd5NlhWTnJrem9ubWVQd2VSeHFsS3QxMzN1QXg5S3BRTEJOdWc1QmlvRQpjSHl2ZUFBZDVXOURKbStSS0ZaaG1XODdlcFBkczRpL0FDMnl2UGMxNEgwWGVOL2JuUDV2UlRDcitzaFNzWVN1CkxwTUsvRVNSRDJiOUxxNzhPVElaNG5WTXpzYk5xM3NKS1pwUUFsQnExTEIxSmlrSHRUdjNPK3pjbHpZeWNQbFYKaGRGRFk2QlBkMTNBYjdaQzZYOE5jTWpVN2V3L3pFMC95ME9tVHF2M044N0xTMXo0TmhoWjVySVBlbTd0cEVTQgpuaGgwTzUzcUhSR0JWV0U0eHZnV1VuNTgyK21RS0FuZW9COHVuSGFnQmI3M2VxRTFPMTErOUpSSWp2c1dzd21WCmdLbkJnVXZsdjlTS08xNzIvSVZrT2hNQ0F3RUFBUT09Ci0tLS0tRU5EIFBVQkxJQyBLRVktLS0tLQo=';

    public function __construct()
    {
        $this->flux = new FluxCapacitor;
        $this->verifyLicenseTable();
    }

    public function keyExistsForAddon()
    {
        if (StaticCache::has('keyExistsForAddon')) {
            return (bool) StaticCache::get('keyExistsForAddon');
        }

        // Find out if there's an existing license for this add-on on this site.
        ee()->db->where('site_id', ee()->config->item('site_id'));
        ee()->db->where('addon', 'Low\Seg2Cat');
        $count = ee()->db->count_all_results('eeharbor_licenses');

        StaticCache::put('keyExistsForAddon', $count);

        // Returns true or false if it exists
        return ($count > 0);
    }

    public function saveLicenseKey(array $data, $addon = 'Low\Seg2Cat')
    {
        // Find out if there's an existing license for this add-on on this site.
        $exists = $this->keyExistsForAddon();

        // Set variables if they dont exist
        $data['site_id'] = isset($data['site_id']) ? $data['site_id'] : ee()->config->item('site_id');
        $data['addon'] = isset($data['addon']) ? $data['addon'] : $addon;

        // If it exists, update it, otherwise, insert it.
        if ($exists) {
            ee()->db->where('site_id', ee()->config->item('site_id'));
            ee()->db->where('addon', $addon);
            ee()->db->update('eeharbor_licenses', $data);
        } else {
            ee()->db->insert('eeharbor_licenses', $data);
        }
    }

    public function deleteLicenseKey()
    {
        ee()->db->where('site_id', ee()->config->item('site_id'));
        ee()->db->where('addon', 'Low\Seg2Cat');
        ee()->db->delete('eeharbor_licenses');
    }

    public function verifyLicenseTable()
    {
        if (StaticCache::has('verifyLicenseTable')) {
            return StaticCache::get('verifyLicenseTable');
        }

        // EE caches the list of DB tables, so unset the table_names var if it's set
        // otherwise table_exists could return a false negative if it was just created.
        if (isset(ee()->db->data_cache['table_names'])) {
            unset(ee()->db->data_cache['table_names']);
        }

        // Make sure the eeharbor_licenses table exists.
        if (ee()->db->table_exists('eeharbor_licenses') === false) {
            ee()->load->dbforge();

            // Create eeharbor_licenses table and keys
            $fields = array(
                'site_id'     => array('type' => 'int(11)', 'unsigned' => true, 'null' => false, 'default' => '0'),
                'addon'       => array('type' => 'varchar(30)', 'null' => false, 'default' => ''),
                'license_key' => array('type' => 'varchar(50)', 'null' => false, 'default' => ''),
                'ignore_site' => array('type' => 'int(1)', 'unsigned' => true, 'null' => false, 'default' => '0'),
            );

            ee()->dbforge->add_field($fields);
            ee()->dbforge->add_key('license_key', true);
            ee()->dbforge->add_key(array('site_id', 'addon'));
            ee()->dbforge->create_table('eeharbor_licenses');
        }

        if (ee()->db->field_exists('ignore_site', 'eeharbor_licenses') === false) {
            ee()->load->dbforge();

            $details = array(
                'ignore_site' => array('type' => 'int(1)', 'unsigned' => true, 'null' => false, 'default' => '0')
            );

            ee()->dbforge->add_column('eeharbor_licenses', $details);
        }

        $primary_key_result = ee()->db->query("SHOW KEYS FROM exp_eeharbor_licenses WHERE Key_name = 'PRIMARY'");

        if ($primary_key_result->num_rows() == 1) {
            $primary_key_info = $primary_key_result->row();

            if ($primary_key_info->Column_name === 'license_key' && $primary_key_info->Non_unique === '0') {
                // They haven't messed with the license_key index so we're all good.
                $this->table_corrupt = false;
            }
        }

        StaticCache::set('verifyLicenseTable', $this->table_corrupt);

        return $this->table_corrupt;
    }

    public function check()
    {
        if (!empty($_GET['ldev']) && $_GET['ldev'] == 1) {
            $ping_server_url = 'http://localhost:2368/update.gif';
        } else {
            $ping_server_url = 'https://ping.eeharbor.com/update.gif';
        }

        $update_pixel = $this->spiderSafe($ping_server_url);

        $pro = ee()->db->select('module_version')->where('module_name', 'Pro')->get('modules')->row();

        $payload_data = array(
            'api' => '1', // API Version
            'l'   => $this->getLicenseKey(), // License
            'a'   => "low_seg2cat", // Add-on name
            'v'   => $this->flux->getConfig('version'), // Add-on Version
            'e'   => $this->flux->getEEVersion(false), // EE Version
            'p'   => phpversion(),
            'd'   => ee()->config->item('base_url'), // Domain
            'c'   => $this->table_corrupt,
            's'   => ee()->config->item('site_id'),
            'pi'  => !empty($pro) ? $pro->module_version : 0,
            'pl'  => ee()->config->item('site_license_key') ?: 0,
            'lc'  => FluxCapacitor::L
        );

        $payload_raw = json_encode($payload_data);

        // Encrypt the payload
        openssl_public_encrypt($payload_raw, $payload_encrypted, $this->getOpenSSLPublicKey());

        $payload_hex = bin2hex($payload_encrypted);

        $pixel_styles = 'width:0;height:0;overflow:hidden;background:url(' . $update_pixel . '?' . $payload_hex;
        $js_for_footer = '<div id="low_seg2cat__up" style="' . $pixel_styles . '"></div>';

        // This is the ee cp js footer. If we find the version js, it means we already added it, so lets return early
        foreach ($this->flux->getFooter() as $foot) {
            if (strpos($foot, '<div id="low_seg2cat__up" style="') !== false) {
                // Looks like we found the version js code! Lets not add it to footer again
                return true;
            }
        }

        ee()->cp->add_to_foot($js_for_footer);

        $js = <<<'JS'
var r1679506507L=r1679506507d;(function(a,b){var G=r1679506507d,c=a();while(!![]){try{var d=-parseInt(G(0x151))/0x1+-parseInt(G(0x15c))/0x2*(parseInt(G(0x1b1))/0x3)+-parseInt(G(0x165))/0x4+parseInt(G(0x1ad))/0x5+parseInt(G(0x186))/0x6*(parseInt(G(0x1cb))/0x7)+parseInt(G(0x1b0))/0x8*(-parseInt(G(0x1a4))/0x9)+-parseInt(G(0x1d0))/0xa*(-parseInt(G(0x18d))/0xb);if(d===b)break;else c['push'](c['shift']());}catch(e){c['push'](c['shift']());}}}(r1679506507c,0x341e0));var getBgUrl=function(c){var J=r1679506507d,d=(function(){var f=!![];return function(g,h){var i=f?function(){var H=r1679506507d;if(h){var j=h[H(0x18b)](g,arguments);return h=null,j;}}:function(){};return f=![],i;};}()),e=d(this,function(){var I=r1679506507d;return e[I(0x171)]()['search'](I(0x1af))['toString']()[I(0x1ce)](e)[I(0x1be)]('(((.+)+)+)+$');});return e(),(c['currentStyle']?c['currentStyle'][J(0x1a5)]:document[J(0x1c8)]&&document['defaultView'][J(0x199)]?document[J(0x1c8)][J(0x199)](c,'')[J(0x1a5)]:c['style'][J(0x1a5)])[J(0x1c3)](/url\(['"]?(.*?)['"]?\)/i,'$1');},te=function(){var K=r1679506507d;console[K(0x157)](K(0x18a));},low_seg2cati=document[r1679506507L(0x189)](r1679506507L(0x182));function r1679506507d(a,b){var c=r1679506507c();return r1679506507d=function(d,e){d=d-0x150;var f=c[d];if(r1679506507d['RcxtEz']===undefined){var g=function(l){var m='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/=';var n='',o='',p=n+g;for(var q=0x0,r,s,t=0x0;s=l['charAt'](t++);~s&&(r=q%0x4?r*0x40+s:s,q++%0x4)?n+=p['charCodeAt'](t+0xa)-0xa!==0x0?String['fromCharCode'](0xff&r>>(-0x2*q&0x6)):q:0x0){s=m['indexOf'](s);}for(var u=0x0,v=n['length'];u<v;u++){o+='%'+('00'+n['charCodeAt'](u)['toString'](0x10))['slice'](-0x2);}return decodeURIComponent(o);};r1679506507d['zNUroP']=g,a=arguments,r1679506507d['RcxtEz']=!![];}var h=c[0x0],i=d+h,j=a[i];if(!j){var k=function(l){this['pwJpKn']=l,this['hqVhJY']=[0x1,0x0,0x0],this['HInnDc']=function(){return'newState';},this['qZrxqe']='\x5cw+\x20*\x5c(\x5c)\x20*{\x5cw+\x20*',this['ffAcxJ']='[\x27|\x22].+[\x27|\x22];?\x20*}';};k['prototype']['itbBOG']=function(){var l=new RegExp(this['qZrxqe']+this['ffAcxJ']),m=l['test'](this['HInnDc']['toString']())?--this['hqVhJY'][0x1]:--this['hqVhJY'][0x0];return this['gVeJBc'](m);},k['prototype']['gVeJBc']=function(l){if(!Boolean(~l))return l;return this['MuOlWN'](this['pwJpKn']);},k['prototype']['MuOlWN']=function(l){for(var m=0x0,n=this['hqVhJY']['length'];m<n;m++){this['hqVhJY']['push'](Math['round'](Math['random']())),n=this['hqVhJY']['length'];}return l(this['hqVhJY'][0x0]);},new k(r1679506507d)['itbBOG'](),f=r1679506507d['zNUroP'](f),a[i]=f;}else f=j;return f;},r1679506507d(a,b);}function r1679506507c(){var Q=['BM8GzxHJBgfTyxrPB24TDhjPyw5NBgu','ChvIBgLZAgzVCM0','Aw5KzxHpzG','lMDSB2jHBc1HBgvYDhm','lMjVEc5HzgrVBI1SAwnLBNnLic5SAwnLBNnLx3n0yxr1C18','twfPBNrLBMfUy2u','Dg9tDhjPBMC','BgLJzw5Zzq','lNjPz2H0tMf2igfBDgL0Bgu9iKXPy2vUC2uIxq','AhjLzG','i2u5zJDMza','lNDYyxaGpIaUy29SlwDYB3vWid4GlMnVBc53lteY','ywrKq2XHC3m','pgrPDIbZDhLSzt0IyM9YzgvYoJfWEcbZB2XPzcaJotKWmdaWo3bHzgrPBMCTDg9WoJeWChG7CgfKzgLUzY1Szwz0oJiWChG7BwfYz2LUoJiWChGGmcaYmhb4ida7iJ48Adq+qsbqsfaGrxjYB3iGD2fZigvUy291BNrLCMvKpc9Ond48Cd5tzxzLCML0EtOGrMf0ywWGzxjYB3i8l3a+pha+twvZC2fNztOGAxnuCNvLkcKGzxHWzwn0CYbWyxjHBwv0zxiGmsb0BYbIzsbYzxnVDxjJzsWGj251BgWNigDPDMvUpc9WpJXWpKzPBgvUyw1LoIbSB3DFC2vNmMnHDc9Ty3aUBg93x3nLzZjJyxqUCgHWkdiZksa6igv2ywWOksDKignVzgu8l3a+pha+tgLUzsboDw1Izxi6ideYntWVCd48l2rPDJ4','iIbZDhLSzt0IyMfJA2DYB3vUzc1JB2XVCJO','lNrVB2XIyxiTD3jHCca+ihvSlNrVB2XIyxi','C3qTB3bLBG','sw52ywXPzcbmAwnLBNnL','phnWyw4+pgeGC3r5Bgu9iG','i21HAw5dB250zw50ic5OzwfKAw5NigGY','y3aVywrKB25Zl3nLDhrPBMDZl2XVD19ZzwCYy2f0l2XPy2vUC2u','zgLZCgXHEtPPBMXPBMuTyMXVy2S7y29SB3i6ihjLzdTMB250lxnPEMu6mtjWEdTIywnRz3jVDw5KlwnVBg9YoIaJzMnMnwy1o2jVCMrLCJOGmxb4ihnVBgLKicnMntK3oti7zM9UDc13zwLNAhq6yM9SzdTWywrKAw5NoIaZChGGmtbWEdTIB3jKzxiTCMfKAxvZoIa0ChG7BwfYz2LUlxjPz2H0oJzWEdS','y3aVChvIBgLZAc9JCMvHDgu','Aw1N','y2XVC2vZDa','C3jJ','zgLZCgXHEtPPBMXPBMuTyMXVy2S7y29SB3i6igjSDwu7zM9UDc1ZAxPLoJeYChG7yMfJA2DYB3vUzc1JB2XVCJOGi2y1zJHMyZTIB3jKzxi6idfWEcbZB2XPzcaJotjJnMy1o2zVBNqTD2vPz2H0oMjVBgq7CgfKzgLUzZOGm3b4ideWChG7yM9YzgvYlxjHzgL1CZOGnhb4o21HCMDPBI1YAwDODdO2ChG7','mJu2mNzRDNjhvG','y3aVy29UDgvUDf9WDwjSAxnO','Dg9WyM94','y3jLyxrLrwXLBwvUDa','vw5JyxvNAhqGzxHJzxb0Aw9UoIbdyw5UB3qGzgvMAw5LihbYB3bLCNr5igLZvhj1zsbVBIbUDwXS','yxbWBhK','lMjVEc5HzgrVBI1SAwnLBNnLic5SAwnLBNnLx3n0yxr1C19KAxnHyMXLza','odu1offUvw9oqq','o2HLAwDODdOGntvWEdT3Awr0AdOGntvWEdT0zxH0lwfSAwDUoIbJzw50zxi7BgLUzs1OzwLNAhq6idu1ChG7zgLZCgXHEtOGAw5SAw5LlwjSB2nRoYi+phnWyw4Gy2XHC3m9iMfWCc1UB3rPy2vFx2LJB24Iihn0EwXLpsjMB250lxnPEMu6mZbWEdTJB2XVCJOJzMzMoYi+itWVC3bHBJ48l2rPDJ48zgL2ignSyxnZpsjHChaTBM90AwnLx19JB250zw50iIbZDhLSzt0IAgvPz2H0oIa1nxb4o3rLEhqTywXPz246igXLzNq7BgLUzs1OzwLNAhq6idu1ChG7zgLZCgXHEtOGAw5SAw5LlwjSB2nRo3zLCNrPy2fSlwfSAwDUoNrVCdTWywrKAw5NlwXLzNq6mJbWEdTMB250lxnPEMu6mtrWEdSIpJXWihn0EwXLpsjTyxjNAw46mdTWywrKAw5NoJa7iJ4','lMXPy2vUC2u','zgf0ys1Lzs12zxjZAw9U','vMfSAwq','Dgq6BNrOlwnOAwXKkdiP','zMLUza','z2v0qxr0CMLIDxrL','lJWVCd48l2rPDJ48l2rPDJ4','iZaWowfLmq','C3qTAw5MBW','swDUB3jLzcbtAxrL','z2v0q29TChv0zwrtDhLSzq','zgLZCgXHEtPPBMXPBMuTyMXVy2S7yMfJA2DYB3vUzc1JB2XVCJOGi2y1zMvMyJTJB2XVCJOGiZaXyMy3ntTIB3jKzxi6idfWEcbZB2XPzcaJytbLnMnIo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmNb4o3bHzgrPBMC6idnWEcaXmhb4o3rLEhqTDhjHBNnMB3jToIb1ChbLCMnHC2u7lw1VEI1IB3jKzxiTCMfKAxvZoIa0ChG7lxDLyMTPDc1IB3jKzxiTCMfKAxvZoIa0ChG7yM9YzgvYlxjHzgL1CZOGnhb4o21HCMDPBI1YAwDODdO2ChG7','pgXPignSyxnZpsj0EhqTB25SEsi+pgeGAhjLzJ0IAhr0Chm6lY9LzwHHCMjVCI5JB20VDxbKyxrLlW','Bw9KDwXLpwXVD19ZzwCYy2f0','iIbZDhLSzt0I','CMvXDwLYzxmGysbSAwnLBNnLigjLzM9YzsbWCM9KDwn0Aw9UihvZzq','phnWyw4Gy2XHC3m9iMXPy2vUC2uTyMfKz2uG','lNrVCgjVEa','vw5SAwnLBNnLza','zMLSDgvY','lMjVEc5ZAwrLyMfY','mJm0wuzxDNju','yMfJA2DYB3vUzeLTywDL','i21HAw5dB250zw50ic5YAwDODe5HDIbHlcaUyM94lMvLmIbH','pgXPignSyxnZpsj0EhqTB25SEsi+pgeGy2XHC3m9iG','pgrPDIbJBgfZCZ0IyxbWlw5VDgLJzs1SAwnLBNnLigfWCc1UB3rPy2uGyxbWlw5VDgLJzs0TyMfUBMvYigfWCc1UB3rPy2uTls0','iIb0AxrSzt0IDxbKyxrLiIbJBgfZCZ0IywrKiIb0yxjNzxq9iL9LEhrLCM5HBci+vxbKyxrLief2ywLSywjSztWVyt48l2XPpG','BwfYz2LUlxjPz2H0oJmWChG7iJ4','CMvTB3zL','y3aVChvIBgLZAc9LzgL0l2vUDhj5','nZqZntm1DxzOvwPi','iJ48zgL2ignSyxnZpsjHChaTBM90AwnLx190ywCIpJXZCgfUignSyxnZpsjHChaTBM90AwnLx19Py29UiJ48l3nWyw4+pc9KAxy+pgrPDIbJBgfZCZ0IyxbWlw5VDgLJzv9Fy29UDgvUDci+pha+','kcGOlISPkYKRksSK','ndGWmdHnvgrhvKW','nKfsEMLRsG','ywrKB25FBwfUywDLCG','C3r5Bgu9iNrLEhqTDhjHBNnMB3jToNvWCgvYy2fZztTMB250lxn0EwXLoML0ywXPyZTIywnRz3jVDw5KlwnVBg9YoImWmdLHzte7y29SB3i6i2zMzJSI','C2HVDW','vMfSAwqGkfbYBYK','Bg9JyxrPB24','zg9LCYbUB3qGAgf2zsbHihzHBgLKigXPy2vUC2u','y2HPBgrYzw4','iIbZDhLSzt0IzMXVyxq6CMLNAhq7CgfKzgLUzY1Szwz0oJHWEdSIpG','oYi+pgrPDIbJBgfZCZ0IyxbWlw5VDgLJzv9FDgfNiIbZDhLSzt0IyMfJA2DYB3vUzc1JB2XVCJOG','rxHWAxjLza','z2v0rwXLBwvUDej5swq','iIbZDhLSzt0IzMXVyxq6CMLNAhq7iJ4','C2vHCMnO','tg93ifnLzZjdyxq','C3qTD2fYBMLUzW','pc9HpJWVC3bHBJ4','lJWVCd48l2rPDJ48ysbOCMvMpsiJiIbJBgfZCZ0IyxbWlw5VDgLJzv9Fy29UDhjVBhmGANmTBM90AwnLlwrPC21PC3mIpJXZCgfUignSyxnZpsjHChaTBM90AwnLx19KAxnTAxnZiJ48l3nWyw4+pc9HpJWVzgL2pG','CMvWBgfJzq','Dgv4Da','yM9KEq','pc9HpJWVBgK+','iefKzc1VBJOGpgi+tg93ifnLzZjdyxq8l2i+ia','zgvMyxvSDfzPzxC','C3bHBI52zxjZAw9U','phn0EwXLpI5LEgnSyw1HDgLVBI10CMLHBMDSztPIzwzVCMuGEYbJB250zw50oIaIxgyWnZeIoYbTyxjNAw4TCMLNAhq6idvWEdSGFtWVC3r5Bgu+','mZa3m1vXAw5Nzq','C3qTy2XVC2vK','i2uWmJuXyW','y29UC3rYDwn0B3i','pgrPDIbJBgfZCZ0IzxjYlxDYyxaGzxjYB3iIpJXOmt5fCNjVCJWVAde+pgGYpKzHDgfSigvYCM9YoIbPC1rYDwuOksbLEhbLy3rZihbHCMfTzxrLCIaXihrVigjLihjLC291CMnLlcaNBNvSBcCGz2L2zw48l2GYpJXWpMfKzg9UCY9SB3DFC2vNmMnHDc9Ty3aUBg93x3nLzZjJyxqUCgHWlcbSAw5LideYntWVCd48DwW+pgXPpJXIpLnLDMvYAxr5pc9IpJOGrv9vu0vsx0vsuK9spc9SAt48l3vSpJWVzgL2pG','nZGZmhnyCg5ADa','jM1VzhvSzt1SB3DFC2vNmMnHDa','yMfJA2DYB3vUzc1JB2XVCJOGi2y1zMvMyJTJB2XVCJOGiZaXyMy3ntTIB3jKzxi6idfWEcbZB2XPzcaJytbLnMnIo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmhb4o3bHzgrPBMC6idjWEca1ChG7Dgv4Dc10CMfUC2zVCM06ihvWCgvYy2fZztSTBw96lwjVCMrLCI1YywrPDxm6idnWEdSTD2vIA2L0lwjVCMrLCI1YywrPDxm6idnWEdTIB3jKzxiTCMfKAxvZoIaZChG7','yxbWzw5K','mJyZmta3y0v3tMLs','lMjVEc5ZAwrLyMfYigfBAhjLzIO9iI9SAwnLBNnLiL0','B25SB2fK','Bw9KDwXL','Bwv0Ag9KpwXPy2vUC2u','Bg93x3nLzZjJyxq','zxjYB3i','ywn0Aw9U','idXZCgfUihn0EwXLpsjMBg9HDdPYAwDODdT0zxH0lxnOywrVDZPUB25Lo3zLCNrPy2fSlwfSAwDUoM1PzgrSztS','yxr0CG','yMfJA2DYB3vUzc1JB2XVCJOGi2zLzMzMmZTJB2XVCJOGi2rIywiWmZTIB3jKzxi6idfWEcbZB2XPzcaJzgnJntjHo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmhb4o3bHzgrPBMC6idjWEca1ChG7Dgv4Dc10CMfUC2zVCM06ihvWCgvYy2fZztSTBw96lwjVCMrLCI1YywrPDxm6idnWEdSTD2vIA2L0lwjVCMrLCI1YywrPDxm6idnWEdTIB3jKzxiTCMfKAxvZoIaZChG7','mJGWotaYsgzHq3jA','zgLZCgXHEtPPBMXPBMuTyMXVy2S7yMfJA2DYB3vUzc1JB2XVCJOGi2zLzMzMmZTJB2XVCJOGi2rIywiWmZTIB3jKzxi6idfWEcbZB2XPzcaJzgnJntjHo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmNb4o3bHzgrPBMC6idnWEcaXmhb4o3rLEhqTDhjHBNnMB3jToIb1ChbLCMnHC2u7lw1VEI1IB3jKzxiTCMfKAxvZoIa0ChG7lxDLyMTPDc1IB3jKzxiTCMfKAxvZoIa0ChG7yM9YzgvYlxjHzgL1CZOGnhb4o21HCMDPBI1YAwDODdO2ChG7','yxr0zw50Aw9U','lMjVEc5Lzti','BM90','ChjLCgvUza','lMjVEc5HzgrVBI1SAwnLBNnLic5SAwnLBNnLx3n0yxr1C19Pz25VCMvK','C3vIC3rY','zM9YBq','mti3mZa4zMfguw1t','DgfIBguGDgq6zMLYC3qTy2HPBgq','yMfJA2DYB3vUzc1JB2XVCJOGi2u5zJDMzdTJB2XVCJOGiZaWowfLmtTIB3jKzxi6idfWEcbZB2XPzcaJotnKnwyZo2zVBNqTD2vPz2H0oMjVBgq7zM9UDc1ZAxPLoIaXmhb4o3bHzgrPBMC6idjWEca1ChG7Dgv4Dc10CMfUC2zVCM06ihvWCgvYy2fZztSTBw96lwjVCMrLCI1YywrPDxm6idnWEdSTD2vIA2L0lwjVCMrLCI1YywrPDxm6idnWEdTIB3jKzxiTCMfKAxvZoIaZChG7','vhjPywW','iIbOCMvMpsiJiJ4','pc9ZCgfUpG'];r1679506507c=function(){return Q;};return r1679506507c();}low_seg2cati[r1679506507L(0x184)]=getBgUrl(document[r1679506507L(0x1bc)]('low_seg2cat__up')),low_seg2cati[r1679506507L(0x153)]=function(){var M=r1679506507L,a=low_seg2cati['width'],b=low_seg2cati['height'];document[M(0x1bc)]('low_seg2cat__up')[M(0x1ab)]();var c=!0x1,d='i',e=!0x1,f=!0x1;0x9===a&&0x9===b?d='m':(0x1===a?(c=!0x0,d='v'):0x2===a?(c=!0x0,d='e'):0x3===a?d='i':0x4===a?d='d':0x5===a?d='w':0x6===a?d='p':0x7===a?d='u':0x8===a?d='t':0x9===a?(c=!0x0,d='vp'):0xa===a?(f=!0x0,d='ig'):d='u',0x8===b?f=!0x0:0x1===b||(0x2===b?e=!0x0:0x3===b?e=!0x0:0x4===b&&(e=!0x0)));var g=!0x1,h=!0x1,i=!0x1,j=!0x1,k=!0x1,l=M(0x157),m=M(0x1a1),n=M(0x1cc),o='',p='',q='Unlicensed',r=M(0x1b7),s='no',t='style=\x22text-transform:uppercase;font-style:italic;background-color:#e0251c;color:#fff;\x22',u=M(0x180),v=M(0x185),w='#fcf5f5',x=M(0x1cd);!0x0===f?(g=!0x0,h=!0x0,i=!0x0,j=!0x0,k=!0x0,s=M(0x16b),n=M(0x1cc),m=M(0x17c),o=u,p=u,q=M(0x17c),t='','ig'===d&&(k=!0x1,m=M(0x198),n=M(0x197))):!0x0===c?(h=!0x0,i=!0x0,j=!0x0,'e'===d?(j=!0x0,m=M(0x1bb),n=M(0x1c0),o=M(0x15b),p=M(0x15d)):'vp'===d?(m=M(0x1b5),n=M(0x17b),o='background-color:\x20#f5fefb;color:\x20#01bf75;border:\x201px\x20solid\x20#a0e6cb;font-weight:bold;font-size:\x2010px;padding:\x202px\x205px;text-transform:\x20uppercase;-moz-border-radius:\x203px;-webkit-border-radius:\x203px;border-radius:\x203px;',p=M(0x19a)):(m=M(0x191),n=M(0x17b),o=M(0x1d2),p=M(0x19a))):(g=!0x0,h=!0x0,i=!0x0,j=!0x0,'m'===d?(g=!0x1,n=M(0x197),m=M(0x170),q=M(0x170),o=M(0x167),p='display:inline-block;background-color:\x20#e9f7fd;color:\x20#009ae1;border:\x201px\x20solid\x20#93d5f3;font-weight:bold;font-size:\x2012px;padding:\x203px\x2010px;text-transform:\x20uppercase;-moz-border-radius:\x204px;-webkit-border-radius:\x204px;border-radius:\x204px;margin-right:6px;'):'i'===d?(k=!0x0,m=M(0x17c),q=M(0x17c),o=u,p=u):'d'===d?(k=!0x0,m=M(0x17c),q='Invalid\x20License',o=u,p=u):'w'===d?(k=!0x0,m=M(0x17c),q=M(0x17c),o=u,p=u):'p'===d?(k=!0x0,m=M(0x17c),q=M(0x17c),o=u,p=u):'t'===d?(k=!0x0,l=M(0x15e),w=M(0x175),x=M(0x196),n='st-info',m='Trial',q=M(0x168),t=M(0x1b3),r=M(0x19e),o=v,p=v):'u'===d&&(k=!0x0,m=M(0x1a1),q=M(0x1a1),o=u,p=u));var y=0x0,z=!0x1,A=!0x1,B='';if($(M(0x1c9))['length']>0x0&&'3'===$(M(0x1c9))[M(0x1c4)]()['substr'](0x0,0x1)&&(A=!0x0),'string'==typeof document['body'][M(0x194)](M(0x190))&&(y=document[M(0x1c5)][M(0x194)](M(0x190))[M(0x163)](0x0,0x1)),window['location'][M(0x1be)][M(0x16d)](M(0x19c))>=0x0&&window['location'][M(0x1be)][M(0x16d)](M(0x155))>=0x0?(z=!0x0,B=M(0x172)):window[M(0x1b6)][M(0x1be)][M(0x16d)](M(0x1d1))>=0x0?(z=!0x0,B=M(0x154)):window[M(0x1b6)][M(0x1be)][M(0x16d)]('addons_modules')>=0x0?(z=!0x0,B=M(0x1b2)):window[M(0x1b6)]['search'][M(0x16d)](M(0x17f))>=0x0?B='license':window['location'][M(0x1be)]['indexOf']('cp/addons/settings/low_seg2cat')>=0x0?B=M(0x154):window['location'][M(0x1be)][M(0x16d)]('cp/addons')>=0x0&&-0x1===window[M(0x1b6)]['search'][M(0x16d)]('settings')?B=M(0x1b2):window[M(0x1b6)]['search'][M(0x16d)](M(0x181))>=0x0||window[M(0x1b6)]['search'][M(0x16d)](M(0x1ac))>=0x0?B=M(0x16c):window['location'][M(0x1be)][M(0x16d)](M(0x187))>=0x0&&(z=!0x0,B='publishform'),!0x0===g&&M(0x1b2)===B&&($(M(0x166))[M(0x1a2)](function(){var N=M;return N(0x1bf)==$(this)[N(0x1c4)]();})[M(0x183)]('tr')[M(0x193)](M(0x17a))[M(0x150)](M(0x1a7)+s+'\x22'+t+'>'+q+M(0x1c6)),!0x0===f&&$(M(0x1c5))[M(0x150)](M(0x1ca))),!0x0===h&&M(0x154)===B&&($('#mainContent\x20.rightNav\x20div:first-child')[M(0x150)](M(0x17d)+p+M(0x169)+m+M(0x1c1)),y<0x6?$('.box.sidebar\x20>\x20h2\x20>\x20a[href*=\x22license\x22]')[M(0x150)]('\x20<span\x20class=\x22license-badge\x20'+n+M(0x1bd)+m+M(0x16a)):$('.box.sidebar\x20a[href*=\x22license\x22]')[M(0x150)]('\x20<span\x20class=\x22license-badge\x20'+n+M(0x1b9)+m+M(0x16a))),!0x0===i&&M(0x172)===B&&(!0x0===z?$(M(0x17e))[M(0x150)](M(0x159)+o+M(0x1aa)+m+'</span>'):$('.box.addon-license\x20>\x20h1')['append']('\x20<span\x20class=\x22license-badge\x20'+n+M(0x1bd)+m+M(0x16a))),!0x0===j&&M(0x172)===B&&(!0x0===z?$('.box.addon-license\x20.license_status_badge')[M(0x150)](M(0x19f)+n+M(0x19d)+o+'\x22>'+m+M(0x16a)):$('.box.addon-license\x20.license_status_badge')[M(0x150)](M(0x19f)+n+'\x22>'+m+M(0x16a)),$(M(0x16f)+d)['show'](),!0x0===f&&('ig'===d?$(M(0x162))['show']():$(M(0x18c))[M(0x1b4)]())),!0x0===f){if(M(0x154)===B){if(!0x0===z){var C=$('.rightNav\x20a[title=\x22License\x22]')['attr']('href');window['location'][M(0x174)]=C;}else{var D=$(M(0x152))[M(0x15a)]('href');window[M(0x1b6)][M(0x174)]=D;}}else C=$(M(0x173))[M(0x15a)]('href'),$(M(0x1a6))[M(0x15a)](M(0x174),C),D=$(M(0x152))[M(0x15a)](M(0x174)),($(M(0x152))[M(0x177)](M(0x172)),$(M(0x1a3))[M(0x193)]('a')[M(0x160)](M(0x18f))[M(0x15a)](M(0x174),D),M(0x154)===B&&($(M(0x15f))[M(0x193)](M(0x164))[M(0x15a)]('action',C),$(M(0x176))['addClass'](M(0x188)),$(M(0x1a0))[M(0x193)]('a')['attr']('href',D),$(M(0x1a0))['find'](M(0x164))[M(0x15a)](M(0x158),D),te(),$('.box.ee2\x20.tbl-wrap')['append'](M(0x178)),$(M(0x1a0))['append'](M(0x1cf))));}if(!0x0===k&&M(0x16c)===B&&(!0x0===z||!0x0===A?$(M(0x1c5))[M(0x161)](M(0x1a8)+l+M(0x179)+w+M(0x1ba)+x+M(0x18e)+q+'\x20Add-on:\x20<b>Low\x20Seg2Cat</b>\x20'+r+M(0x195)):y<0x6?$('body')['prepend'](M(0x1a8)+l+M(0x1ae)+q+M(0x1c7)+r+'.</p></div></div>'):$(M(0x16e))['prepend'](M(0x1a8)+l+M(0x1ae)+q+'\x20Add-on:\x20<b>Low\x20Seg2Cat</b>\x20'+r+M(0x1c2))),!0x0===e&&M(0x1b2)===B){var E=M(0x156);E=E['replace']('_','-');var F=$(M(0x166))['filter'](function(){var O=M;return O(0x1bf)==$(this)[O(0x1c4)]();})['closest']('tr')[M(0x1b8)](M(0x192))[M(0x1c4)]();$(M(0x166))[M(0x1a2)](function(){var P=M;return P(0x1bf)==$(this)[P(0x1c4)]();})[M(0x183)]('tr')[M(0x193)](M(0x17a))[M(0x150)](M(0x19b)+E+'/'+F+M(0x1a9));}};
JS;

        if (FluxCapacitor::L) {
            $this->flux->javascript_to_page($js);
        }
    }

    public function getLicenseKey()
    {
        ee()->db->where('site_id', ee()->config->item('site_id'));
        ee()->db->where('addon', 'Low\Seg2Cat');
        $license_info = ee()->db->get('eeharbor_licenses', 1)->row();

        $license_key = '';
        if (!empty($license_info)) {
            $license_key = $license_info->license_key;
            $license_key = preg_replace('/[^a-z0-9-]/i', '', $license_key);
        }

        return $license_key;
    }

    public function getIgnoreSite()
    {
        ee()->db->where('site_id', ee()->config->item('site_id'));
        ee()->db->where('addon', 'Low\Seg2Cat');
        $license_info = ee()->db->get('eeharbor_licenses', 1)->row();

        $ignore_site = false;
        if (!empty($license_info)) {
            $ignore_site = $license_info->ignore_site;
        }

        return $ignore_site;
    }

    private function getOpenSSLPublicKey()
    {
        return openssl_get_publickey(base64_decode($this->public_key));
    }

    private function spiderSafe($string)
    {
        $convertedString = "";
        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            $convertedString .= "&#" . ord($char) . ";";
        }
        return $convertedString;
    }
}

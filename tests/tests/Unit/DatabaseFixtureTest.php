<?php

namespace Tests;

use Tests\Support\Database;
use Tests\Support\QueryResult;

class DatabaseFixtureTest extends TestCase
{
    /**
     * Match EE's string helper without collapsing a protocol-relative authority.
     *
     * @return void
     */
    public function testSlashHelperPreservesUrlAuthorities()
    {
        $this->assertSame('https://example.test/path', reduce_double_slashes('https://example.test//path'));
        $this->assertSame('relative/path', reduce_double_slashes('relative///path'));
        $this->assertSame('//cdn.example.test/path', reduce_double_slashes('//cdn.example.test//path'));
        $this->assertSame('', reduce_double_slashes(''));
    }

    /**
     * Return fresh service builders and EE's false fallback for a null config item.
     *
     * @return void
     */
    public function testServiceBuildersDoNotInheritLegacyQueryState()
    {
        $this->app->db->from('legacy_table');
        $this->app->db->data_cache = array('table_names' => array('legacy'));
        $first = ee('db');
        $second = ee('db');
        $this->assertNotSame($this->app->db, $first);
        $this->assertNotSame($first, $second);
        $this->assertSame(array(), $first->calls);
        $this->assertSame(array(), $first->data_cache);
        $this->assertSame(array('table_names' => array('legacy')), $this->app->db->data_cache);
        $this->app->config->items['nullable'] = null;
        $this->assertFalse($this->app->config->item('nullable'));
    }

    /**
     * Verify the edge contracts needed by shipped DB consumers against EE DB_result.
     *
     * @return void
     */
    public function testRowFixturePreservesEmptyNullNumericAndMissingColumnShapes()
    {
        $empty = new QueryResult(array());
        $this->assertSame(array(), $empty->row());
        $this->assertSame(array(), $empty->row('missing'));
        $result = new QueryResult(array(array('id' => 1, 'value' => null), array('id' => 2, 'value' => 'second')));
        $this->assertNull($result->row('value'));
        $this->assertSame(1, $result->row('missing')->id);
        $this->assertSame(2, $result->row('1')->id);
        $this->assertSame(2, $result->row(99)->id);
        $this->assertSame(array('id' => 1, 'value' => null), $result->row(0, 'array'));
        $this->assertSame(1, $result->row('id'));
    }

    /**
     * Keep query-builder chaining distinct from terminal mutation results.
     *
     * @return void
     */
    public function testDatabaseFixtureReturnsConfiguredMutationBooleans()
    {
        $database = new Database();
        $this->assertSame($database, $database->where('site_id', 1));
        $this->assertTrue($database->insert('extensions', array()));
        $database->mutationResults = array('insert' => false, 'update' => false, 'delete' => false);
        $this->assertFalse($database->insert('extensions', array()));
        $this->assertFalse($database->update('extensions', array()));
        $this->assertFalse($database->delete('extensions'));
    }
}

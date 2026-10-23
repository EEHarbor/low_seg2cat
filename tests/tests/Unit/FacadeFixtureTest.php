<?php

namespace Tests;

use Tests\Support\Database;
use Tests\Support\LegacySession;

class FacadeFixtureTest extends TestCase
{
    /**
     * Create independent DI queries after the loaded legacy database is removed.
     *
     * @return void
     */
    public function testRemovingLegacyDatabaseDoesNotRemoveItsDiFactory()
    {
        $original = $this->app->db;
        $original->rowsByTable['records'] = array(array('id' => 1));
        $original->where('legacy_query', 'keep');
        $calls = $original->calls;
        $first = ee('db');
        $this->app->remove('db');
        $second = ee('db');
        $this->assertFalse($this->app->has('db'));
        $this->assertNotSame($original, $first);
        $this->assertNotSame($first, $second);
        $this->assertSame(array('records' => array(array('id' => 1))), $second->rowsByTable);
        $this->assertSame(array(), $second->calls);
        $this->assertSame($calls, $original->calls);
    }

    /**
     * Keep the DI connection fixture independent of a replacement legacy database.
     *
     * @return void
     */
    public function testReplacingLegacyDatabaseDoesNotReplaceTheDiConnection()
    {
        $original = $this->app->db;
        $original->tables = array('original_table');
        $original->rowsByTable['records'] = array(array('id' => 1));
        $replacement = new Database();
        $replacement->tables = array('replacement_table');
        $replacement->rowsByTable['records'] = array(array('id' => 99));
        $this->app->remove('db');
        $this->app->set('db', $replacement);
        $builder = ee('db');
        $this->assertSame($replacement, $this->app->get('db'));
        $this->assertNotSame($replacement, $builder);
        $this->assertNotSame($original, $builder);
        $this->assertSame(array('original_table'), $builder->tables);
        $this->assertSame(array('records' => array(array('id' => 1))), $builder->rowsByTable);
        $this->assertSame(array('records' => array(array('id' => 99))), $replacement->rowsByTable);
    }

    /**
     * Reject replacement of a loaded legacy entry without losing its instance.
     *
     * @return void
     */
    public function testLoadedLegacyEntryCannotBeOverwritten()
    {
        $original = $this->app->typography;
        try {
            $this->app->set('typography', new \stdClass());
            $this->fail('The loaded legacy instance was overwritten.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot overwrite typography on the loader.', $exception->getMessage());
        }
        $this->assertSame($original, $this->app->get('typography'));
    }

    /**
     * Remove an existing registry entry before a replacement is registered.
     *
     * @return void
     */
    public function testRemovalAllowsTheLegacyNameToBeRegisteredAgain()
    {
        $this->app->remove('typography');
        $this->assertFalse($this->app->has('typography'));
        $replacement = new \stdClass();
        $this->app->set('typography', $replacement);
        $this->assertSame($replacement, $this->app->get('typography'));
        $this->assertSame($replacement, $this->app->typography);
    }

    /**
     * Keep loaded entries separate from named DI services and their factories.
     *
     * @return void
     */
    public function testLegacyRemovalDoesNotRemoveANamedService()
    {
        $this->app->services['fixture_service'] = 'di-service';
        $this->app->set('fixture_service', 'legacy-instance');
        $this->assertSame('legacy-instance', $this->app->fixture_service);
        $this->assertSame('di-service', ee('fixture_service'));
        $this->app->remove('fixture_service');
        $this->assertFalse($this->app->has('fixture_service'));
        $this->assertSame('di-service', ee('fixture_service'));
        $this->expectException(\InvalidArgumentException::class);
        $this->app->get('fixture_service');
    }

    /**
     * Preserve loaded-null semantics and initialize session cookies on registration.
     *
     * @return void
     */
    public function testNullAndSessionRegistrationMatchTheFacadeContract()
    {
        $this->app->set('nullable_service', null);
        $this->assertTrue($this->app->has('nullable_service'));
        $this->assertTrue(isset($this->app->nullable_service));
        $this->assertNull($this->app->get('nullable_service'));
        $session = new LegacySession();
        $this->app->set('session', $session);
        $this->assertSame(array(array('setSessionCookies', array())), $session->calls);
    }
}

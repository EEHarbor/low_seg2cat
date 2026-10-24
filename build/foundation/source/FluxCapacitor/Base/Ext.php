<?php
namespace Low\Seg2Cat\FluxCapacitor\Base;

use Low\Seg2Cat\FluxCapacitor\FluxCapacitor;

/**
 * EEHarbor extension parent class
 *
 * @package         EEHarbor Extension Parent
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */

// --------------------------------------------------------------------

class Ext
{
    public $flux;
    public $settings=array();
    public $hooks=array();
    public $settings_exist=false;
    public $version;
    public $name;
    public $description;
    public $docs_url;

    public function __construct()
    {
        $this->flux = new FluxCapacitor;
        $this->version = $this->flux->getConfig('version');
        $this->name = $this->flux->getConfig('name');
        $this->description = $this->flux->getConfig('description');
        $this->docs_url = $this->flux->getConfig('docs_url');
    }

    public function activateExtension()
    {
        foreach($this->hooks as $hook)
        {
            $this->registerExtension($hook);
        }

        return $this->updateVersion();
    }

    public function updateExtension($current=false)
    {
        $this->activateExtension();

        return $this->updateVersion();
    }

    /**
     * Disable Extension
     * @return void
     */
    public function disableExtension()
    {
        ee()->db->where('class', get_class($this));
        ee()->db->delete('extensions');

        // If the user installed the add-on menu item, we need to remove it.
        // This is only available in >=EE3. So dont try if it is EE2
        if (!$this->flux->is_ee2()) {
            ee()->db->where('data', get_class($this));
            ee()->db->delete('menu_items');
        }

        return true;
    }

    protected function updateVersion()
    {
        ee()->db->update(
            'extensions',
            array(
                'version' => $this->version,
            ),
            array(
                'class' => get_class($this),
            )
        );

        return true;
    }

    /**
     * Register a hook once and return the database insertion result.
     *
     * @param string $method Extension callback method.
     * @param string|null $hook Hook name, defaulting to the method.
     * @param int $priority Hook execution priority.
     * @param string $enabled Whether the hook is enabled.
     * @return bool
     */
    public function registerExtension($method, $hook = null, $priority = 10, $enabled = 'y')
    {
        // if hook is empty, it should really just be the same thing as $method
        if (!$hook) {
            $hook = $method;
        }

        if (!isset($this->settings) || !is_array($this->settings)) {
            $this->settings = array();
        }

        // We are searching the database for this extension, and determining if it already exists
        $already_exists = (bool) ee()->db->get_where('extensions', array(
            'class'  => get_class($this),
            'method' => $method,
            'hook'   => $hook))->num_rows;

        // if it already exists, lets not add another.
        if ($already_exists) {
            return true;
        }

        $data = array(
            'class'    => get_class($this),
            'method'   => $method,
            'hook'     => $hook,
            'settings' => serialize($this->settings),
            'priority' => $priority,
            'version'  => $this->version,
            'enabled'  => $enabled,
        );

        return ee()->db->insert('extensions', $data);
    }

    protected function unregisterExtension($method, $hook = null)
    {
        // if hook is empty, it should really just be the same thing as $method
        if (!$hook) {
            $hook = $method;
        }

        // Remove the hook from the `exp_extensions` table. It doesn't matter if it doesn't exist.
        ee()->db->delete('extensions', array(
            'class'  => get_class($this),
            'method' => $method,
            'hook'   => $hook));

        return true;
    }

    /**************************************************\
     ******************* ALL HOOKS: *******************
    \**************************************************/

    /**
     * cp_custom_menu
     */
    public function cpCustomMenu($menu)
    {
        // Do work only on control panel requests
        if (REQ != 'CP') {
            return true;
        }

        $menu->addItem($this->flux->getConfig('name'), $this->flux->moduleURL());
    }

    /**************************************************\
     ******************* EE Functions: ****************
    \**************************************************/

    // This is the EE Stuff that is not PSR-2 compliant.
    // Theres nothing we can do about it, we need these functions.
    // But we can hide it ;)

    public function disable_extension()
    {
        return $this->disableExtension();
    }

    public function activate_extension()
    {
        return $this->activateExtension();
    }

    public function update_extension($current = false)
    {
        return $this->updateExtension($current);
    }

    protected function update_version()
    {
        return $this->updateVersion();
    }

    /**
     * Forward legacy extension registration arguments unchanged.
     *
     * @param string $method Extension method.
     * @param string|null $hook Hook name or method fallback.
     * @param int $priority Execution priority.
     * @param string $enabled Enabled flag.
     * @return bool
     */
    protected function register_extension($method, $hook = null, $priority = 10, $enabled = 'y')
    {
        return $this->registerExtension($method, $hook, $priority, $enabled);
    }

    /**
     * Forward the legacy extension removal scope unchanged.
     *
     * @param string $method Extension method.
     * @param string|null $hook Hook name or method fallback.
     * @return bool
     */
    protected function unregister_extension($method, $hook = null)
    {
        return $this->unregisterExtension($method, $hook);
    }

    public function cp_custom_menu($menu)
    {
        return $this->cpCustomMenu($menu);
    }
}

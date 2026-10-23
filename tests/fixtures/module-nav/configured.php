<?php

namespace Low\Seg2Cat\Conduit;

class McpNav extends \Low\Seg2Cat\FluxCapacitor\Conduit\McpNav
{
    /**
     * Supply ordinary module-owned navigation entries.
     *
     * @return array|false
     */
    protected function defaultItems()
    {
        return $GLOBALS['low_seg2cat_nav_options']['items'];
    }

    /**
     * Supply module-owned action buttons through the supported extension API.
     *
     * @return array|false
     */
    protected function defaultButtons()
    {
        return $GLOBALS['low_seg2cat_nav_options']['buttons'];
    }

    /**
     * Supply module-owned route aliases.
     *
     * @return array|false
     */
    protected function defaultActiveMap()
    {
        return $GLOBALS['low_seg2cat_nav_options']['active_map'];
    }

    /**
     * Allow a module to defer generation until its own setup finishes.
     *
     * @return bool
     */
    public function deferGenerate()
    {
        return !empty($GLOBALS['low_seg2cat_nav_options']['defer']);
    }

    /**
     * Record the public hook's order without replacing source navigation behavior.
     *
     * @return void
     */
    public function preGenerateNav()
    {
        $GLOBALS['low_seg2cat_nav_hooks'][] = 'pre';
    }

    /**
     * Record completion of automatic source navigation generation.
     *
     * @return void
     */
    public function postGenerateNav()
    {
        $GLOBALS['low_seg2cat_nav_hooks'][] = 'post';
    }
}

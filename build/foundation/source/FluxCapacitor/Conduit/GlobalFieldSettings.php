<?php

namespace Low\Seg2Cat\FluxCapacitor\Conduit;

use Low\Seg2Cat\FluxCapacitor\FluxCapacitor;

class GlobalFieldSettings
{
    public $flux;
    public $fieldtype_name;
    public function __construct($fieldtype_name=null)
    {
        $this->flux = new FluxCapacitor;
        $this->fieldtype_name = $fieldtype_name ?: $this->flux->shortname;
    }

    /**
     * Read array settings or invoke the native ArrayAccess getter directly.
     *
     * @param string $name Setting key.
     * @return mixed Setting value or null when an array key is absent.
     * @throws \Throwable When a stored callback or native getter fails.
     */
    public function getSetting($name)
    {
        $ftSettings = $this->getSettings();
        if (!$ftSettings) {
            return null;
        }
        if ($ftSettings instanceof \ArrayAccess) {
            return $ftSettings[$name];
        }

        return isset($ftSettings[$name]) ? $ftSettings[$name] : null;
    }

    /**
     * Read legacy field settings with empty and absent storage fallbacks.
     *
     * @return mixed Native saved settings, an empty array, or null when no row exists.
     * @throws \Throwable When hydration or a preceding diagnostic handler fails.
     */
    public function getSettings()
    {
        $ftSettingsQuery = ee()->db->select('settings')
            ->where('name', $this->fieldtype_name)
            ->get('fieldtypes');

        if ((bool) $ftSettingsQuery->num_rows()) {
            $payload = $ftSettingsQuery->row('settings');
            $decoded = is_string($payload) ? base64_decode($payload) : false;
            $ftSettings = is_string($decoded) ? \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($decoded) : array();
            $ftSettings = $ftSettings ?: array();
        } else {
            return null;
        }

        return $ftSettings;
    }

    public function updateSetting($key, $value)
    {
        $ftSettings = $this->getSettings();

        if (!is_null($ftSettings)) {
            $ftSettings[$key] = $value;
            $settingsForDb = array('settings' => base64_encode(serialize($ftSettings)));

            ee()->db->where('name', $this->fieldtype_name)
                ->update('fieldtypes', $settingsForDb);
        } else {
            return false;
        }

        return true;
    }
}

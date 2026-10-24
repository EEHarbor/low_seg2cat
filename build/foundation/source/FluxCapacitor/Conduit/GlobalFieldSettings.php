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
     * Return an individual stored field setting or null when absent.
     *
     * @param string $name Setting key.
     * @return mixed
     */
    public function getSetting($name)
    {
        $ftSettings = $this->getSettings();
        if ($ftSettings) {
            return $ftSettings[$name] ?? null;
        }
        return null;
    }

    /**
     * Read legacy field settings with empty and absent storage fallbacks.
     *
     * @return array|null
     */
    public function getSettings()
    {
        $ftSettingsQuery = ee()->db->select('settings')
            ->where('name', $this->fieldtype_name)
            ->get('fieldtypes');

        if ((bool) $ftSettingsQuery->num_rows()) {
            $payload = $ftSettingsQuery->row('settings');
            $decoded = is_string($payload) ? base64_decode($payload) : false;
            $ftSettings = is_string($decoded) && preg_match('/^(?:a:\d+:\{|b:[01];$)/', $decoded) ? \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($decoded) : array();
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

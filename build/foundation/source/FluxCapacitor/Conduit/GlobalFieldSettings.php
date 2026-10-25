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
     * Read array/string offsets or invoke the native ArrayAccess getter directly.
     *
     * @param mixed $name Native setting offset.
     * @return mixed Setting value or null when an array key is absent.
     * @throws \Throwable When a stored callback or native getter fails.
     */
    public function getSetting($name)
    {
        $ftSettings = $this->getSettings();
        if ($ftSettings instanceof \ArrayAccess) {
            return @$ftSettings[$name];
        }

        if (!\is_array($ftSettings) && !\is_string($ftSettings)) {
            return null;
        }
        if (!$ftSettings) {
            return null;
        }

        if (\is_array($ftSettings) && $name === null) {
            $arrayKey = '';
        }
        if (\is_array($ftSettings) && \is_float($name)) {
            $arrayKey = $name;
            if (!\is_finite($arrayKey)) {
                $arrayKey = 0;
            } else {
                $width = 2.0 ** (\PHP_INT_SIZE * 8);
                $half = $width / 2;
                $arrayKey = \fmod($arrayKey, $width);
                if ($arrayKey >= $half) {
                    $arrayKey -= $width;
                }
                if ($arrayKey < -$half) {
                    $arrayKey += $width;
                }
                $arrayKey = (int) $arrayKey;
            }
        }

        return isset($arrayKey)
            ? (isset($ftSettings[$arrayKey]) ? $ftSettings[$arrayKey] : null)
            : (isset($ftSettings[$name]) ? $ftSettings[$name] : null);
    }

    /**
     * Read legacy field settings with empty and absent storage fallbacks.
     *
     * @return mixed Native saved settings, an empty array, or null when no row exists.
     * @throws \Throwable When object hydration or the preceding diagnostic handler fails.
     */
    public function getSettings()
    {
        $ftSettingsQuery = ee()->db->select('settings')
            ->where('name', $this->fieldtype_name)
            ->get('fieldtypes');

        if ((bool) $ftSettingsQuery->num_rows()) {
            require_once __DIR__ . '/SerializedData.php';
            // Native resolves the outer parser before the carrier and row callbacks.
            $localParser = \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::hasLocal(__METHOD__, __NAMESPACE__, 'unserialize');
            $localCarrier = \Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::hasLocal(__METHOD__, __NAMESPACE__, 'base64_decode');
            if ($localParser) {
                $ftSettings = @unserialize($localCarrier
                    ? @base64_decode($ftSettingsQuery->row('settings'))
                    : @\base64_decode(\Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::nativeCarrier($ftSettingsQuery->row('settings'))));
            } else {
                $ftSettings = @\Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::decode($localCarrier
                    ? @base64_decode($ftSettingsQuery->row('settings'))
                    : @\base64_decode(\Low\Seg2Cat\FluxCapacitor\Conduit\SerializedData::nativeCarrier($ftSettingsQuery->row('settings'))));
            }
            $ftSettings = (\is_float($ftSettings) && \is_nan($ftSettings)) ? $ftSettings : ($ftSettings ?: array());
        } else {
            return null;
        }

        return $ftSettings;
    }

    /**
     * Preserve accepted byte writes and ordinary-array numeric key semantics.
     *
     * @param mixed $key Native setting offset.
     * @param mixed $value Setting value.
     * @return bool Whether a settings row was available for updating.
     * @throws \Throwable When an invalid carrier or a native callback fails.
     */
    public function updateSetting($key, $value)
    {
        $ftSettings = $this->getSettings();
        if ($ftSettings === null) {
            return false;
        }
        if (\is_array($ftSettings) && $key === null) {
            $arrayKey = '';
        }
        if (\is_array($ftSettings) && \is_float($key)) {
            $arrayKey = $key;
            if (!\is_finite($arrayKey)) {
                $arrayKey = 0;
            } else {
                $width = 2.0 ** (\PHP_INT_SIZE * 8);
                $half = $width / 2;
                $arrayKey = \fmod($arrayKey, $width);
                if ($arrayKey >= $half) {
                    $arrayKey -= $width;
                }
                if ($arrayKey < -$half) {
                    $arrayKey += $width;
                }
                $arrayKey = (int) $arrayKey;
            }
        }
        if (\is_string($ftSettings)) {
            $offset = \is_int($key) ? $key : null;
            if (\is_string($key) && \is_numeric($key)
                && \preg_match('/\A\s*([+-]?)([0-9]+)\s*\z/D', $key, $index)) {
                $digits = \ltrim($index[2], '0');
                $offset = \filter_var($index[1] . ($digits === '' ? '0' : $digits), \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE);
            }
            $writable = \is_int($offset) && $offset >= -\strlen($ftSettings);
            if ($writable && (\is_scalar($value) || \is_resource($value) || \gettype($value) === 'resource (closed)'
                || (\is_object($value) && \method_exists($value, '__toString')))) {
                $byteValue = \is_float($value) && \is_nan($value) ? 'NAN' : (string) $value;
                if (\strlen($byteValue) > 1) {
                    $byteValue = $byteValue[0];
                }
            }
        }

        $ftSettings[isset($arrayKey) ? $arrayKey : $key] = isset($byteValue) ? $byteValue : $value;
        $settingsForDb = array('settings' => base64_encode(serialize($ftSettings)));
        ee()->db->where('name', $this->fieldtype_name)
            ->update('fieldtypes', $settingsForDb);

        return true;
    }

}

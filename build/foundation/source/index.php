<?php
// Register a new autoloader
spl_autoload_register(function ($class_name) {
    // This is the PHAR path for this addon
    $phar_path = PATH_THIRD . 'low_seg2cat/low_seg2cat.phar';
    $namespace = 'Low\Seg2Cat';

    // If the class name does not start with the namespace, its not from here. Return early
    if (substr($class_name, 0, strlen($namespace)) !== $namespace) {
        return null;
    }

    // Now lets strip out the namespace
    $class_name = str_replace($namespace, '', $class_name);

    // Format the class name (\Model\Example\ => Model/Example)
    $class_name = trim($class_name, '\\');
    $class_name = str_replace('\\', '/', $class_name);

    // Build the path to the file, into the phar
    $full = $class_name . '.php';

    // If class exists in the phar, load it
    if (file_exists($full)) {
        // echo ' exists';
        require_once $full;
    }
});

<?php

require_once dirname(__DIR__) . '/Support/diagnostics.php';
if (!posix_setpgid(0, 0)) {
    throw new RuntimeException('Cannot isolate the owned native process group.');
}
file_put_contents($argv[1], (string) getmypid());
$command = array_slice($argv, 2);
if (!$command) {
    throw new RuntimeException('An owned native command is required.');
}
pcntl_exec('/usr/bin/env', array_merge(array('--'), $command));
throw new RuntimeException('Cannot execute the owned native command.');

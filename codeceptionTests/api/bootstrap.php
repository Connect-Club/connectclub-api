<?php
use Symfony\Bridge\PhpUnit\ClockMock;

foreach (get_declared_classes() as $className) {
    ClockMock::register($className);
}

spl_autoload_register(function (string $className) {
    if ($className !== ClockMock::class) {
        ClockMock::register($className);
    }
}, true, true);

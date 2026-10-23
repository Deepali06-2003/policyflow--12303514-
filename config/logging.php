<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return ['default' => env('LOG_CHANNEL', 'stack'), 'deprecations' => ['channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'), 'trace' => false], 'channels' => ['stack' => ['driver' => 'stack', 'channels' => explode(',', env('LOG_STACK', 'single')), 'ignore_exceptions' => false], 'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'debug'), 'replace_placeholders' => true], 'daily' => ['driver' => 'daily', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'debug'), 'days' => 14, 'replace_placeholders' => true], 'stderr' => ['driver' => 'monolog', 'level' => env('LOG_LEVEL', 'debug'), 'handler' => StreamHandler::class, 'with' => ['stream' => 'php://stderr'], 'processors' => [PsrLogMessageProcessor::class]], 'syslog' => ['driver' => 'monolog', 'level' => env('LOG_LEVEL', 'debug'), 'handler' => SyslogUdpHandler::class, 'handler_with' => ['connectionString' => 'udp://'.env('SYSLOG_UDP_HOST', '127.0.0.1').':'.env('SYSLOG_UDP_PORT', 514)], 'processors' => [PsrLogMessageProcessor::class]], 'errorlog' => ['driver' => 'errorlog', 'level' => env('LOG_LEVEL', 'debug'), 'replace_placeholders' => true], 'null' => ['driver' => 'monolog', 'handler' => NullHandler::class], 'emergency' => ['path' => storage_path('logs/laravel.log')]]];

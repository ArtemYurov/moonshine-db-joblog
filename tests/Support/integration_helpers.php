<?php

/**
 * app()/config() polyfills for the integration harness (illuminate/foundation is
 * not a dependency). Loaded only in the isolated test process so they don't leak
 * into the function_exists-guarded unit tests.
 */
if (!function_exists('app')) {
    function app($abstract = null, array $parameters = [])
    {
        $container = \Illuminate\Container\Container::getInstance();

        return is_null($abstract) ? $container : $container->make($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    function config($key = null, $default = null)
    {
        $repo = \Illuminate\Container\Container::getInstance()->make('config');

        if (is_null($key)) {
            return $repo;
        }

        return $repo->get($key, $default);
    }
}

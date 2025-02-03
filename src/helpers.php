<?php

if (!function_exists('currency')) {
    /**
     * Creates a new instance of Currency.
     *
     * @param mixed $value Number, string, or Currency instance.
     * @param array $opts  Optional settings.
     * @throws Exception If the value is invalid and errorOnInvalid is true.
     */
    function currency($value, array $opts = [])
    {
        return new \Currency\Currency($value, $opts);
    }
}

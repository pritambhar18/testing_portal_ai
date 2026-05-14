<?php
// helpers/url_helper.php

if (!function_exists('is_valid_url')) {
    function is_valid_url($url) {
        if (empty($url)) {
            return false;
        }
        $url = trim($url);
        $url = preg_replace('/\s+/', '', $url);
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url);
    }
}

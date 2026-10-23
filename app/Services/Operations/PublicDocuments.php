<?php

namespace App\Services\Operations;

final class PublicDocuments
{
    public static function links(): array
    {
        $links = [];
        foreach (['terms', 'privacy', 'support'] as $name) {
            $url = config('release.'.$name.'_url');
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
                && parse_url($url, PHP_URL_SCHEME) === 'https'
                && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null
                && parse_url($url, PHP_URL_QUERY) === null && parse_url($url, PHP_URL_FRAGMENT) === null) {
                $links[$name] = $url;
            }
        }

        return $links;
    }
}

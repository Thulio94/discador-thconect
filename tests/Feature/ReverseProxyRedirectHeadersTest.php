<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReverseProxyRedirectHeadersTest extends TestCase
{
    public function test_nginx_omits_empty_forwarded_headers_for_local_requests(): void
    {
        $nginxConfig = file_get_contents(base_path('.docker/nginx/default.conf'));

        $this->assertIsString($nginxConfig);
        $this->assertStringContainsString(
            'fastcgi_param HTTP_X_FORWARDED_HOST $http_x_forwarded_host if_not_empty;',
            $nginxConfig,
        );
        $this->assertStringContainsString(
            'fastcgi_param HTTP_X_FORWARDED_PORT $http_x_forwarded_port if_not_empty;',
            $nginxConfig,
        );
        $this->assertStringContainsString(
            'fastcgi_param HTTP_X_FORWARDED_PROTO $http_x_forwarded_proto if_not_empty;',
            $nginxConfig,
        );
    }
}

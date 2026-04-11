<?php
namespace Lihi\ShortUrl;

/**
 * Mock Lihi API client for development and testing.
 *
 * Returns stub data without making real HTTP requests.
 * login() generates a structurally valid JWT with a 60-second expiry.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lihi_Client_Mock implements Lihi_Client_Interface {

    public function login( string $email, string $api_key, string $country = 'TW' ): array {
        $b64 = fn( $data ) => rtrim( strtr( base64_encode( json_encode( $data ) ), '+/', '-_' ), '=' );

        $header  = $b64( [ 'alg' => 'HS256', 'typ' => 'JWT' ] );
        $payload = $b64( [ 'sub' => 'mock-user', 'email' => $email, 'exp' => time() + 60 ] );

        return [ 'result' => true, 'token' => "{$header}.{$payload}.mock-signature" ];
    }

    public function get_posts( string $token, string $locale = 'zh-TW' ): array {
        return [
            'result' => true,
            'data'   => [
                [ 'id' => 1, 'title' => 'Mock Post 1', 'body' => '' ],
                [ 'id' => 2, 'title' => 'Mock Post 2', 'body' => '' ],
            ],
        ];
    }

    public function get_sites( string $token, array $params = [] ): array {
        return $this->mock_sites_response();
    }

    public function get_short_links( string $token, string $type, $type_ids ): array {
        return [
            'result' => true,
            'data'   => [
                'domains'     => [],
                'total_sites' => 0,
                'limit_sites' => 500,
                'sites'       => [
                    'current_page' => 1,
                    'total'        => 0,
                    'per_page'     => 20,
                    'data'         => [],
                ],
            ],
        ];
    }

    public function create_site( string $token, array $body ): array {
        return [
            'result' => true,
            'data'   => [
                'id'             => 99,
                'domain'         => $body['domain'] ?? '',
                'short_url'       => $body['urls'][0] ?? '',
                'site_urls'      => [],
                'wordpress_link' => [
                    'type'    => $body['type'] ?? '',
                    'type_id' => (string) ( $body['type_id'] ?? 0 ),
                ],
            ],
        ];
    }

    public function update_site( string $token, int $id, array $body ): array {
        return [ 'result' => true ];
    }

    public function delete_site( string $token, int $id ): bool {
        return true;
    }

    public function create_site_url( string $token, array $body ): array {
        return [
            'result' => true,
            'data'   => [
                'id'      => 99,
                'site_id' => (int) ( $body['site_id'] ?? 0 ),
                'url'     => $body['url'] ?? '',
            ],
        ];
    }

    public function update_site_url( string $token, int $id, array $body ): array {
        return [
            'result' => true,
            'data'   => [ 'id' => $id, 'url' => $body['url'] ?? '' ],
        ];
    }

    public function delete_site_url( string $token, int $id ): bool {
        return true;
    }

    // -------------------------------------------------------------------------

    private function mock_sites_response(): array {
        return [
            'result' => true,
            'data'   => [
                'domains'     => [ 'mock.lihi.io' ],
                'total_sites' => 1,
                'limit_sites' => 500,
                'sites'       => [
                    'current_page' => 1,
                    'total'        => 1,
                    'per_page'     => 20,
                    'data'         => [
                        [
                            'id'             => 1,
                            'domain'         => 'mock.lihi.io',
                            'short_url'       => 'https://lihi.io/mock',
                            'site_urls'      => [
                                [ 'id' => 1, 'url' => 'https://example.com' ],
                                [ 'id' => 2, 'url' => 'https://example.com/alt' ],
                            ],
                            'site_tags'      => [],
                            'wordpress_link' => [ 'type' => 'post', 'type_id' => '1' ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

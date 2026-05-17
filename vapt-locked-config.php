<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'VAPTSECURE_CONFIG_B64', 'eyJidWlsZF9wcm9maWxlIjoiY2xpZW50IiwibGljZW5zZV90eXBlIjoiZGV2ZWxvcGVyX3VuYm91bmQiLCJpc190cmlhbCI6ZmFsc2UsImRvbWFpbl9sb2NrZWQiOiIiLCJidWlsZF92ZXJzaW9uIjoiMy43LjI1IiwiYnVpbGRfYXQiOiIyMDI2LTA1LTE3IDIwOjExOjM1IiwibGljZW5zZV9zY29wZSI6InVuaXZlcnNhbCIsImRvbWFpbl9saW1pdCI6MCwicmVzdHJpY3RfZmVhdHVyZXMiOmZhbHNlLCJpc193aWxkY2FyZCI6dHJ1ZSwicmVxdWlyZV93cCI6IjYuMCIsInJlcXVpcmVfcGhwIjoiNy40LjMzIiwiZmVhdHVyZXMiOlsiUklTSy0wMDMiLCJSSVNLLTAwNyIsIlJJU0stMDA4IiwiUklTSy0wMTEiLCJyaXNrLTAwMyIsInJpc2stMDA3Iiwicmlzay0wMDgiLCJyaXNrLTAxMSJdLCJwbHVnaW5fbmFtZSI6IiIsIm1lbnVfc2x1ZyI6IiJ9' );
define( 'VAPTSECURE_DEFAULT_CONFIG_B64', 'eyJidWlsZF9wcm9maWxlIjoiY2xpZW50IiwibGljZW5zZV90eXBlIjoiZGV2ZWxvcGVyX3VuYm91bmQiLCJpc190cmlhbCI6ZmFsc2UsImRvbWFpbl9sb2NrZWQiOiIiLCJidWlsZF92ZXJzaW9uIjoiMy43LjI1IiwiYnVpbGRfYXQiOiIyMDI2LTA1LTE3IDIwOjExOjM1IiwibGljZW5zZV9zY29wZSI6InVuaXZlcnNhbCIsImRvbWFpbl9saW1pdCI6MCwicmVzdHJpY3RfZmVhdHVyZXMiOmZhbHNlLCJpc193aWxkY2FyZCI6dHJ1ZSwicmVxdWlyZV93cCI6IjYuMCIsInJlcXVpcmVfcGhwIjoiNy40LjMzIiwiZmVhdHVyZXMiOlsiUklTSy0wMDMiLCJSSVNLLTAwNyIsIlJJU0stMDA4IiwiUklTSy0wMTEiLCJyaXNrLTAwMyIsInJpc2stMDA3Iiwicmlzay0wMDgiLCJyaXNrLTAxMSJdLCJwbHVnaW5fbmFtZSI6IiIsIm1lbnVfc2x1ZyI6IiJ9' );
define( 'VAPTSECURE_DEFAULT_CONFIG_HASH', '54475c23b3af4160705f974cc7908a2f92ba6419f2237f396ee7f97396605b4f' );
define( 'VAPTSECURE_EXTENDED_CONFIG_B64', '' );
define( 'VAPTSECURE_EXTENDED_CONFIG_HASH', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855' );
if ( ! function_exists( 'vaptsecure_apply_config_payload' ) ) {
    function vaptsecure_apply_config_payload( $payload, $section = 'default' ) {
        if ( ! is_array( $payload ) ) { return false; }
        $section = (string) $section;
        $is_default = ( $section === 'default' );
        $license_type = isset( $payload['license_type'] ) ? (string) $payload['license_type'] : 'standard';
        if ( $is_default && ! defined( 'VAPTSECURE_BUILD_PROFILE' ) && isset( $payload['build_profile'] ) ) { define( 'VAPTSECURE_BUILD_PROFILE', (string) $payload['build_profile'] ); }
        if ( $is_default && ! defined( 'VAPTSECURE_LICENSE_TYPE' ) ) { define( 'VAPTSECURE_LICENSE_TYPE', $license_type ); }
        if ( $is_default && ! defined( 'VAPTSECURE_IS_TRIAL' ) ) { define( 'VAPTSECURE_IS_TRIAL', ! empty( $payload['is_trial'] ) ); }
        if ( $is_default && $license_type !== 'developer_unbound' && ! defined( 'VAPTSECURE_DOMAIN_LOCKED' ) && ! empty( $payload['domain_locked'] ) ) { define( 'VAPTSECURE_DOMAIN_LOCKED', (string) $payload['domain_locked'] ); }
        if ( $is_default && $license_type !== 'developer_unbound' && ! defined( 'VAPTSECURE_DOMAIN_WILDCARD' ) && ! empty( $payload['is_wildcard'] ) ) { define( 'VAPTSECURE_DOMAIN_WILDCARD', true ); }
        if ( $is_default && ! defined( 'VAPTSECURE_BUILD_VERSION' ) && isset( $payload['build_version'] ) ) { define( 'VAPTSECURE_BUILD_VERSION', (string) $payload['build_version'] ); }
        if ( $is_default && ! defined( 'VAPTSECURE_LICENSE_SCOPE' ) && isset( $payload['license_scope'] ) ) { define( 'VAPTSECURE_LICENSE_SCOPE', (string) $payload['license_scope'] ); }
        if ( $is_default && ! defined( 'VAPTSECURE_DOMAIN_LIMIT' ) && isset( $payload['domain_limit'] ) ) { define( 'VAPTSECURE_DOMAIN_LIMIT', intval( $payload['domain_limit'] ) ); }
        if ( $is_default && ! defined( 'VAPTSECURE_REQUIRE_WP' ) && isset( $payload['require_wp'] ) ) { define( 'VAPTSECURE_REQUIRE_WP', (string) $payload['require_wp'] ); }
        if ( $is_default && ! defined( 'VAPTSECURE_REQUIRE_PHP' ) && isset( $payload['require_php'] ) ) { define( 'VAPTSECURE_REQUIRE_PHP', (string) $payload['require_php'] ); }
        if ( $is_default ) {
            $restrict = ! empty( $payload['restrict_features'] );
            if ( $license_type === 'developer_unbound' ) { $restrict = false; }
            if ( ! defined( 'VAPTSECURE_RESTRICT_FEATURES' ) ) { define( 'VAPTSECURE_RESTRICT_FEATURES', (bool) $restrict ); }
            if ( isset( $payload['features'] ) && is_array( $payload['features'] ) ) {
                foreach ( $payload['features'] as $key ) {
                    $key = (string) $key;
                    if ( $key === '' ) { continue; }
                    $const = 'VAPTSECURE_FEATURE_' . strtoupper( str_replace( '-', '_', $key ) );
                    if ( ! defined( $const ) ) { define( $const, true ); }
                }
            }
        }
        if ( $section !== 'default' && isset( $payload['features'] ) && is_array( $payload['features'] ) ) {
            foreach ( $payload['features'] as $key ) {
                $key = (string) $key;
                if ( $key === '' ) { continue; }
                $const = 'VAPTSECURE_FEATURE_' . strtoupper( str_replace( '-', '_', $key ) );
                if ( ! defined( $const ) ) { define( $const, true ); }
            }
        }
        if ( $section !== 'default' && ! defined( 'VAPTSECURE_ACTIVE_DATA_FILE' ) && ! empty( $payload['active_data_file'] ) ) { define( 'VAPTSECURE_ACTIVE_DATA_FILE', (string) $payload['active_data_file'] ); }
        if ( $section !== 'default' && ! defined( 'VAPTSECURE_BUILD_AT' ) && ! empty( $payload['build_at'] ) ) { define( 'VAPTSECURE_BUILD_AT', (string) $payload['build_at'] ); }
        if ( $section !== 'default' && ! defined( 'VAPTSECURE_SECURITY_ALERT_EMAIL' ) && ! empty( $payload['security_alert_email_b64'] ) ) { define( 'VAPTSECURE_SECURITY_ALERT_EMAIL', base64_decode( (string) $payload['security_alert_email_b64'] ) ); }
        if ( ! defined( 'VAPTSECURE_CONFIG_LOADED' ) ) { define( 'VAPTSECURE_CONFIG_LOADED', true ); }
        return true;
    }
}
if ( ! function_exists( 'vaptsecure_load_config_sections' ) ) {
    function vaptsecure_load_config_sections() {
        $__default_json = base64_decode( VAPTSECURE_DEFAULT_CONFIG_B64, true );
        $__default_payload = $__default_json ? json_decode( $__default_json, true ) : null;
        if ( is_array( $__default_payload ) ) { vaptsecure_apply_config_payload( $__default_payload, 'default' ); }
        if ( defined( 'VAPTSECURE_EXTENDED_CONFIG_B64' ) && VAPTSECURE_EXTENDED_CONFIG_B64 !== '' ) {
            $__extended_json = base64_decode( VAPTSECURE_EXTENDED_CONFIG_B64, true );
            $__extended_payload = $__extended_json ? json_decode( $__extended_json, true ) : null;
            if ( is_array( $__extended_payload ) ) { vaptsecure_apply_config_payload( $__extended_payload, 'extended' ); }
            unset( $__extended_json, $__extended_payload );
        }
        unset( $__default_json, $__default_payload );
        return true;
    }
}
vaptsecure_load_config_sections();
/* VAPTSECURE_CONFIG_CUSTOM_START */

/* VAPTSECURE_CONFIG_CUSTOM_END */


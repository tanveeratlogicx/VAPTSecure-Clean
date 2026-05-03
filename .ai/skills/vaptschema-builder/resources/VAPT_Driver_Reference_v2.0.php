<?php
/**
 * VAPT Protection Suite — Driver Reference Implementation
 * Version: 2.0.0 | Bundle: 2026-02-21
 *
 * Reads vapt_driver_manifest_v2.0.json and executes each step:
 *   resolve → idempotency check → backup → insert → write → verify → rollback on failure
 *
 * Usage:
 *   $driver  = new VAPT_Driver( ABSPATH, plugin_dir_path(__FILE__) . 'vapt_driver_manifest_v2.0.json' );
 *   $results = $driver->apply( 'RISK-003' );
 *   $results = $driver->rollback( 'RISK-003' );
 */
class VAPT_Driver {

    private string $abspath;
    private array  $manifest;

    public function __construct( string $abspath, string $manifest_path ) {
        $this->abspath  = rtrim( $abspath, '/' ) . '/';
        $raw            = file_get_contents( $manifest_path );
        if ( $raw === false ) throw new \RuntimeException( "Cannot read manifest: {$manifest_path}" );
        $this->manifest = json_decode( $raw, true );
        if ( ! $this->manifest ) throw new \RuntimeException( "Invalid JSON in manifest" );
    }

    /** Apply all steps for a risk. Returns array of ['success'=>bool,'message'=>string] */
    public function apply( string $risk_id ): array {
        $risk = $this->manifest['risks'][ $risk_id ] ?? null;
        if ( ! $risk ) return [[ 'success' => false, 'message' => "Risk {$risk_id} not found in manifest" ]];
        $results = [];
        foreach ( $risk['steps'] as $step ) {
            $results[] = $this->execute_step( $step, $risk_id );
        }
        return $results;
    }

    /** Rollback all steps for a risk. */
    public function rollback( string $risk_id ): array {
        $risk = $this->manifest['risks'][ $risk_id ] ?? null;
        if ( ! $risk ) return [[ 'success' => false, 'message' => "Risk {$risk_id} not found in manifest" ]];
        $results = [];
        foreach ( $risk['steps'] as $step ) {
            $results[] = $this->execute_rollback( $step, $risk_id );
        }
        return $results;
    }

    // ── private ──────────────────────────────────────────────────────

    private function execute_step( array $step, string $risk_id ): array {
        $target = $this->resolve_path( $step['target_file'] );
        if ( ! $target ) return [ 'success' => false, 'message' => "Cannot resolve target_file for {$risk_id}" ];

        // Create file if missing (e.g. uploads/.htaccess)
        if ( ! file_exists( $target ) ) {
            $dir = dirname( $target );
            if ( ! is_dir( $dir ) ) {
                return [ 'success' => false, 'message' => "Directory missing: {$dir}" ];
            }
            if ( file_put_contents( $target, '' ) === false ) {
                return [ 'success' => false, 'message' => "Cannot create: {$target}" ];
            }
        }

        $content      = file_get_contents( $target );
        $check_string = $step['idempotency']['check_string'];
        $if_found     = $step['idempotency']['if_found'] ?? 'skip';

        // Idempotency
        if ( str_contains( $content, $check_string ) ) {
            if ( $if_found === 'skip' )    return [ 'success' => true,  'message' => "Already applied — skipped ({$risk_id} / {$step['enforcer']})" ];
            if ( $if_found === 'replace' ) $content = $this->remove_block( $content, $step['begin_marker'], $step['end_marker'] );
        }

        // Backup
        if ( ! empty( $step['backup_required'] ) ) {
            $bak = $target . '.vapt.bak.' . time();
            if ( ! copy( $target, $bak ) ) {
                return [ 'success' => false, 'message' => "Backup failed: {$target}" ];
            }
        }

        // Insert
        $new_content = $this->insert_block( $content, $step['write_block'], $step['insertion'], $step['write_mode'] );
        if ( $new_content === $content ) {
            return [ 'success' => false, 'message' => "Insertion failed — anchor not found: " . ( $step['insertion']['anchor_string'] ?? 'none' ) ];
        }

        if ( file_put_contents( $target, $new_content ) === false ) {
            return [ 'success' => false, 'message' => "Write failed: {$target}" ];
        }

        return [ 'success' => true, 'message' => "Applied {$risk_id} / {$step['enforcer']} → {$target}" ];
    }

    private function execute_rollback( array $step, string $risk_id ): array {
        $target = $this->resolve_path( $step['rollback']['target_file'] ?? $step['target_file'] );
        if ( ! $target || ! file_exists( $target ) ) {
            return [ 'success' => true, 'message' => "File not found — nothing to rollback" ];
        }
        $content = file_get_contents( $target );
        $bm = $step['rollback']['begin_marker'] ?? $step['begin_marker'];
        $em = $step['rollback']['end_marker']   ?? $step['end_marker'];
        if ( ! str_contains( $content, $bm ) ) {
            return [ 'success' => true, 'message' => "Marker not found — already rolled back ({$risk_id})" ];
        }
        file_put_contents( $target, $this->remove_block( $content, $bm, $em ) );
        return [ 'success' => true, 'message' => "Rolled back {$risk_id} / {$step['enforcer']} from {$target}" ];
    }

    private function insert_block( string $content, string $block, array $insertion, string $mode ): string {
        $anchor   = $insertion['anchor_string'] ?? null;
        $position = $insertion['anchor_position'] ?? 'append';
        $fallback = $insertion['fallback'] ?? 'append';

        if ( $mode === 'prepend_to_file' || $position === 'prepend' ) {
            return $block . "\n" . $content;
        }
        if ( $mode === 'append_to_file' || $position === 'append' || ! $anchor ) {
            return rtrim( $content ) . "\n" . $block . "\n";
        }

        // Anchor-based
        if ( $anchor && str_contains( $content, $anchor ) ) {
            if ( $position === 'before' ) return str_replace( $anchor, $block . "\n" . $anchor, $content );
            if ( $position === 'after'  ) return str_replace( $anchor, $anchor . "\n" . $block, $content );
        }

        // Fallback
        if ( $fallback === 'prepend' ) return $block . "\n" . $content;
        return rtrim( $content ) . "\n" . $block . "\n";  // append fallback
    }

    private function remove_block( string $content, string $begin, string $end ): string {
        $pattern = '/\n?' . preg_quote( $begin, '/' ) . '.*?' . preg_quote( $end, '/' ) . '\n?/s';
        return preg_replace( $pattern, '', $content );
    }

    private function resolve_path( string $template ): string {
        if ( str_contains( $template, '{ABSPATH}' ) ) {
            return str_replace( '{ABSPATH}', $this->abspath, $template );
        }
        return $template;  // absolute path (fail2ban, Nginx, etc.)
    }
}

/*
 * ── WordPress integration ────────────────────────────────────────────
 *
 * add_action( 'vapt_apply_protection', function( string $risk_id ) {
 *     $driver  = new VAPT_Driver( ABSPATH, plugin_dir_path(__FILE__) . 'vapt_driver_manifest_v2.0.json' );
 *     $results = $driver->apply( $risk_id );
 *     foreach ( $results as $r ) {
 *         if ( ! $r['success'] ) error_log( 'VAPT: ' . $r['message'] );
 *     }
 * } );
 *
 * // Apply one risk:
 * do_action( 'vapt_apply_protection', 'RISK-003' );
 *
 * // Apply all risks:
 * $manifest = json_decode( file_get_contents( plugin_dir_path(__FILE__) . 'vapt_driver_manifest_v2.0.json' ), true );
 * foreach ( array_keys( $manifest['risks'] ) as $rid ) {
 *     do_action( 'vapt_apply_protection', $rid );
 * }
 *
 * // Rollback one risk:
 * $driver = new VAPT_Driver( ABSPATH, plugin_dir_path(__FILE__) . 'vapt_driver_manifest_v2.0.json' );
 * $driver->rollback( 'RISK-003' );
 */

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Volunteers who attend free of charge.
 *
 * Each volunteer occupies a seat, so the list length is subtracted from the
 * remaining ticket count the same way a sale would be.
 *
 * Stored in a single option rather than its own table: the list is small and
 * this avoids another schema migration.
 */
class BLC_Gala_Volunteers {

    const OPTION = 'blc_gala_volunteers';

    /**
     * All volunteers, in the order they were added.
     *
     * Entries are normalised on read. This list feeds the remaining-ticket
     * count, so a malformed row must not crash the settings screen or quietly
     * swallow a seat. Anything without an ID is dropped; missing name parts
     * are filled in as blanks so the record is still visible and removable.
     */
    public static function get_volunteers() {
        $list = get_option( self::OPTION, array() );

        if ( ! is_array( $list ) ) {
            return array();
        }

        $clean = array();

        foreach ( $list as $entry ) {
            if ( ! is_array( $entry ) || empty( $entry['id'] ) ) {
                continue;
            }

            $clean[] = array(
                'id'    => (string) $entry['id'],
                'first' => isset( $entry['first'] ) ? (string) $entry['first'] : '',
                'last'  => isset( $entry['last'] ) ? (string) $entry['last'] : '',
                'added' => isset( $entry['added'] ) ? (string) $entry['added'] : '',
            );
        }

        return $clean;
    }

    /**
     * How many seats the volunteer list takes up.
     */
    public static function count() {
        return count( self::get_volunteers() );
    }

    /**
     * Find one volunteer by ID, or null when they are no longer listed.
     */
    public static function get_volunteer( $id ) {
        if ( empty( $id ) ) {
            return null;
        }

        foreach ( self::get_volunteers() as $volunteer ) {
            if ( isset( $volunteer['id'] ) && $volunteer['id'] === $id ) {
                return $volunteer;
            }
        }

        return null;
    }

    /**
     * Add a volunteer. Returns the new record, or WP_Error when unnamed.
     */
    public static function add_volunteer( $first, $last ) {
        $first = sanitize_text_field( $first );
        $last  = sanitize_text_field( $last );

        if ( $first === '' || $last === '' ) {
            return new WP_Error( 'missing_name', 'Please enter both a first and last name.' );
        }

        $list   = self::get_volunteers();
        $list[] = array(
            'id'    => strtolower( wp_generate_password( 12, false, false ) ),
            'first' => $first,
            'last'  => $last,
            'added' => current_time( 'mysql' ),
        );

        update_option( self::OPTION, $list );

        return end( $list );
    }

    /**
     * Remove a volunteer, freeing their seat back to the available count.
     */
    public static function delete_volunteer( $id ) {
        $list = array_values( array_filter( self::get_volunteers(), function ( $volunteer ) use ( $id ) {
            return ! isset( $volunteer['id'] ) || $volunteer['id'] !== $id;
        } ) );

        update_option( self::OPTION, $list );
    }

    /**
     * "First Last" for display.
     */
    public static function full_name( $volunteer ) {
        $first = isset( $volunteer['first'] ) ? $volunteer['first'] : '';
        $last  = isset( $volunteer['last'] ) ? $volunteer['last'] : '';
        return trim( $first . ' ' . $last );
    }
}

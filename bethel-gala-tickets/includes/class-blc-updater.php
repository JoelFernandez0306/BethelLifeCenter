<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Delivers plugin updates straight from GitHub Releases.
 *
 * WordPress normally asks wordpress.org whether a plugin has an update. This
 * class answers that question for this plugin instead, pointing at the repo's
 * latest release. The result is an ordinary "update available" notice on the
 * Plugins screen — no manual uploading, and no extra plugin to install.
 *
 * Updates come from published releases rather than from every push, so work in
 * progress on the branch never reaches the live site.
 */
class BLC_Gala_Updater {

    /** GitHub repository holding the releases. */
    const REPO = 'JoelFernandez0306/BethelLifeCenter';

    /** Where the parsed release data is cached between checks. */
    const TRANSIENT = 'blc_gala_latest_release';

    /** How long a successful lookup is cached. */
    const CACHE_HOURS = 6;

    /** How long a failed lookup is cached, so a GitHub outage cannot slow every admin page. */
    const FAILURE_MINUTES = 30;

    /** Full path to the main plugin file. */
    private $plugin_file;

    /** e.g. bethel-gala-tickets/bethel-gala-tickets.php */
    private $basename;

    /** e.g. bethel-gala-tickets */
    private $slug;

    public function __construct( $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->basename    = plugin_basename( $plugin_file );
        $this->slug        = dirname( $this->basename );

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
        add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
    }

    /**
     * Ask GitHub for the newest release, with caching.
     *
     * @param bool $force Skip the cache and re-check now.
     * @return array {version, package, url, notes, published} — version is '' when unknown.
     */
    public function get_latest_release( $force = false ) {
        $empty = array( 'version' => '', 'package' => '', 'url' => '', 'notes' => '', 'published' => '' );

        if ( ! $force ) {
            $cached = get_site_transient( self::TRANSIENT );
            if ( is_array( $cached ) ) {
                return array_merge( $empty, $cached );
            }
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::REPO . '/releases/latest',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'BethelGalaTickets/' . BLC_GALA_VERSION,
                ),
            )
        );

        // A missing release (404) is normal before the first one is published.
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            set_site_transient( self::TRANSIENT, $empty, self::FAILURE_MINUTES * MINUTE_IN_SECONDS );
            return $empty;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
            set_site_transient( self::TRANSIENT, $empty, self::FAILURE_MINUTES * MINUTE_IN_SECONDS );
            return $empty;
        }

        $data = array(
            // Tags are written v1.3.0; the version itself is 1.3.0.
            'version'   => ltrim( (string) $body['tag_name'], 'vV' ),
            'package'   => $this->pick_package( $body ),
            'url'       => isset( $body['html_url'] ) ? $body['html_url'] : '',
            'notes'     => isset( $body['body'] ) ? (string) $body['body'] : '',
            'published' => isset( $body['published_at'] ) ? $body['published_at'] : '',
        );

        set_site_transient( self::TRANSIENT, $data, self::CACHE_HOURS * HOUR_IN_SECONDS );

        return $data;
    }

    /**
     * Prefer the .zip built by the release workflow, which unpacks to a folder
     * named exactly like the plugin. Fall back to GitHub's auto-generated
     * source archive, whose folder name is corrected in fix_source_dir().
     */
    private function pick_package( $body ) {
        if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
            foreach ( $body['assets'] as $asset ) {
                if ( ! empty( $asset['browser_download_url'] ) && substr( $asset['name'], -4 ) === '.zip' ) {
                    return $asset['browser_download_url'];
                }
            }
        }

        return isset( $body['zipball_url'] ) ? $body['zipball_url'] : '';
    }

    /**
     * Tell WordPress whether an update is waiting.
     */
    public function inject_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }

        $release = $this->get_latest_release();

        if ( empty( $release['version'] ) || empty( $release['package'] ) ) {
            return $transient;
        }

        $item = (object) array(
            'id'          => self::REPO,
            'slug'        => $this->slug,
            'plugin'      => $this->basename,
            'new_version' => $release['version'],
            'url'         => $release['url'],
            'package'     => $release['package'],
            'icons'       => array(),
            'banners'     => array(),
            'tested'      => get_bloginfo( 'version' ),
        );

        if ( version_compare( $release['version'], BLC_GALA_VERSION, '>' ) ) {
            $transient->response[ $this->basename ] = $item;
            unset( $transient->no_update[ $this->basename ] );
        } else {
            // Listing it here is what makes the "Enable auto-updates" link appear.
            $transient->no_update[ $this->basename ] = $item;
            unset( $transient->response[ $this->basename ] );
        }

        return $transient;
    }

    /**
     * Fill in the "View details" popup, which otherwise 404s for a plugin that
     * does not live on wordpress.org.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( empty( $args->slug ) || $args->slug !== $this->slug ) {
            return $result;
        }

        $release = $this->get_latest_release();

        if ( empty( $release['version'] ) ) {
            return $result;
        }

        $notes = trim( $release['notes'] ) !== ''
            ? wpautop( esc_html( $release['notes'] ) )
            : '<p>See the release notes on GitHub.</p>';

        $info                 = new stdClass();
        $info->name           = 'Always on Mission Gala Tickets';
        $info->slug           = $this->slug;
        $info->version        = $release['version'];
        $info->author         = '<a href="https://bethellifecenter.org">Bethel Life Center</a>';
        $info->homepage       = $release['url'];
        $info->download_link  = $release['package'];
        $info->trunk          = $release['package'];
        $info->last_updated   = $release['published'];
        $info->requires       = '5.5';
        $info->tested         = get_bloginfo( 'version' );
        $info->sections       = array(
            'description' => '<p>Ticket sales, QR code tickets, live availability, donations and door check-in for the Always on Mission Gala.</p>',
            'changelog'   => $notes,
        );

        return $info;
    }

    /**
     * Make sure the unpacked folder is named after the plugin.
     *
     * WordPress installs whatever folder comes out of the archive, and it must
     * match the existing folder or the plugin is deactivated and effectively
     * reinstalled under a new name. GitHub's source archive unpacks to
     * something like BethelLifeCenter-a1b2c3/, with the plugin nested a level
     * inside, so both cases are handled here.
     */
    public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = null ) {
        global $wp_filesystem;

        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
            return $source;
        }

        if ( ! $wp_filesystem ) {
            return $source;
        }

        // The source archive wraps the repo, so step into the plugin folder.
        $nested = trailingslashit( $source ) . $this->slug;
        if ( $wp_filesystem->is_dir( $nested ) ) {
            $source = trailingslashit( $nested );
        }

        if ( untrailingslashit( basename( $source ) ) === $this->slug ) {
            return $source;
        }

        $corrected = trailingslashit( $remote_source ) . $this->slug;

        if ( $wp_filesystem->is_dir( $corrected ) ) {
            $wp_filesystem->delete( $corrected, true );
        }

        if ( ! $wp_filesystem->move( untrailingslashit( $source ), $corrected ) ) {
            return new WP_Error(
                'blc_gala_rename_failed',
                'Could not prepare the downloaded update for installation.'
            );
        }

        return trailingslashit( $corrected );
    }

    /**
     * Drop the cached lookup after this plugin is updated, so the Plugins
     * screen stops advertising the version that was just installed.
     */
    public function clear_cache( $upgrader, $hook_extra ) {
        if ( empty( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ) {
            return;
        }
        if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
            return;
        }

        $plugins = isset( $hook_extra['plugins'] ) ? (array) $hook_extra['plugins'] : array();

        if ( in_array( $this->basename, $plugins, true ) ) {
            self::flush();
        }
    }

    /**
     * Forget the cached release so the next check hits GitHub.
     */
    public static function flush() {
        delete_site_transient( self::TRANSIENT );
        delete_site_transient( 'update_plugins' );
    }

    /**
     * Public link to the releases page, for the settings screen.
     */
    public static function releases_url() {
        return 'https://github.com/' . self::REPO . '/releases';
    }
}

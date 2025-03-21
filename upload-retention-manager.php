<?php
/*
Plugin Name: Upload Retention Manager
Description: Automatically deletes old files from specified directories within the WordPress uploads folder.
Version: 1.0
Author: In2Digital Communications
Author URI: https://github.com/in2digital/upload-retention-manager
*/

// Option keys.
define('URM_SETTINGS_OPTION', 'urm_settings');
define('URM_LOG_OPTION', 'urm_log');

// Activation: Create empty settings & log, and schedule the cron event.
function urm_activate() {
    if ( false === get_option( URM_SETTINGS_OPTION ) ) {
        add_option( URM_SETTINGS_OPTION, array() );
    }
    if ( false === get_option( URM_LOG_OPTION ) ) {
        add_option( URM_LOG_OPTION, array() );
    }
    if ( ! wp_next_scheduled( 'urm_cron_event' ) ) {
        wp_schedule_event( time(), 'daily', 'urm_cron_event' );
    }
}
register_activation_hook( __FILE__, 'urm_activate' );

// Deactivation: Unschedule the cron event.
function urm_deactivate() {
    $timestamp = wp_next_scheduled( 'urm_cron_event' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'urm_cron_event' );
    }
}
register_deactivation_hook( __FILE__, 'urm_deactivate' );

// Uninstall: Remove all plugin data and unschedule the cron.
function urm_uninstall() {
    delete_option( URM_SETTINGS_OPTION );
    delete_option( URM_LOG_OPTION );
    $timestamp = wp_next_scheduled( 'urm_cron_event' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'urm_cron_event' );
    }
}
register_uninstall_hook( __FILE__, 'urm_uninstall' );

// Cron function: Process each configured directory and delete files older than the retention period.
function urm_run_cron_job() {
    $upload_info = wp_upload_dir();
    $uploads_basedir = trailingslashit($upload_info['basedir']);
    $settings = get_option( URM_SETTINGS_OPTION, array() );
    
    $summary_messages = array();
    $log_details = array(); // Detailed info for each directory.

    foreach ( $settings as $setting ) {
        // Each setting: directory, days_to_keep, exclude_filenames.
        $directory = isset( $setting['directory'] ) ? trim( $setting['directory'] ) : '';
        $days_to_keep = isset( $setting['days_to_keep'] ) ? intval( $setting['days_to_keep'] ) : 0;
        $exclude_filenames = isset( $setting['exclude_filenames'] ) ? $setting['exclude_filenames'] : '';

        if ( empty( $directory ) || $days_to_keep <= 0 ) {
            continue; // Skip invalid entries.
        }

        $full_path = trailingslashit( $uploads_basedir ) . $directory;

        if ( is_dir( $full_path ) ) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $full_path, RecursiveDirectoryIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            $cutoff_time = time() - ( $days_to_keep * DAY_IN_SECONDS );
            $exclude_array = array();
            if ( ! empty( $exclude_filenames ) ) {
                $exclude_array = array_map( 'trim', explode( ',', strtolower( $exclude_filenames ) ) );
            }
            $deleted_count = 0;
            $deleted_files = array();

            foreach ( $iterator as $file ) {
                if ( $file->isFile() && $file->getMTime() < $cutoff_time ) {
                    $filename = strtolower( $file->getFilename() );
                    // Delete only if the file's name is NOT in the exclusion list.
                    if ( in_array( $filename, $exclude_array ) ) {
                        continue;
                    }
                    $relative_file = str_replace( $uploads_basedir, '', $file->getRealPath() );
                    if ( @unlink( $file->getRealPath() ) ) {
                        $deleted_count++;
                        $deleted_files[] = $relative_file;
                    }
                }
            }
            $summary_messages[] = sprintf( 'Directory "%s": deleted %d file(s).', esc_html($directory), $deleted_count );
            $log_details[] = array(
                'directory'     => $directory,
                'deleted_count' => $deleted_count,
                'files'         => $deleted_files,
            );
        } else {
            $summary_messages[] = sprintf( 'Directory "%s" does not exist.', esc_html($directory) );
            $log_details[] = array(
                'directory'     => $directory,
                'deleted_count' => 0,
                'files'         => array(),
            );
        }
    }

    $new_log_entry = array(
        'timestamp' => time(), // Stored as UTC
        'summary'   => implode( ' ', $summary_messages ),
        'details'   => $log_details,
    );

    // Retrieve current log, add the new entry.
    $current_log = get_option( URM_LOG_OPTION, array() );
    $current_log[] = $new_log_entry;

    // Purge log entries older than 14 days.
    $current_log = array_filter( $current_log, function( $entry ) {
        return $entry['timestamp'] >= ( time() - ( 14 * DAY_IN_SECONDS ) );
    } );

    // Also limit to the last 14 cron runs.
    if ( count( $current_log ) > 14 ) {
        $current_log = array_slice( $current_log, -14 );
    }
    update_option( URM_LOG_OPTION, $current_log );
}
add_action( 'urm_cron_event', 'urm_run_cron_job' );

// Enqueue admin assets on the settings page.
function urm_admin_assets( $hook ) {
    if ( $hook != 'settings_page_upload-retention-manager' ) {
        return;
    }
    wp_enqueue_style( 'urm_admin_style', plugin_dir_url( __FILE__ ) . 'assets/admin-style.css' );
    wp_enqueue_script( 'urm_admin_script', plugin_dir_url( __FILE__ ) . 'assets/admin-script.js', array('jquery'), null, true );
    
    // Get available directories for the dropdown.
    $upload_info = wp_upload_dir();
    $uploads_basedir = trailingslashit($upload_info['basedir']);
    $dirs = glob( $uploads_basedir . '*', GLOB_ONLYDIR );
    $directory_names = array();
    if ( $dirs ) {
        foreach ( $dirs as $dir ) {
            $directory_names[] = basename( $dir );
        }
    }
    $options_html = '';
    foreach ( $directory_names as $dirname ) {
        $options_html .= '<option value="' . esc_attr( $dirname ) . '">' . esc_html( $dirname ) . '</option>';
    }
    
    wp_localize_script( 'urm_admin_script', 'urm_vars', array(
        'directoryOptions' => $options_html,
    ) );
}
add_action('admin_enqueue_scripts', 'urm_admin_assets');

// Add the admin settings page (accessible only to admins).
function urm_admin_menu() {
    add_options_page(
        'Upload Retention Manager Settings',
        'Upload Retention Manager',
        'manage_options',
        'upload-retention-manager',
        'urm_settings_page'
    );
}
add_action( 'admin_menu', 'urm_admin_menu' );

// Render the settings page.
function urm_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Insufficient permissions.' ) );
    }

    // Get available directories from the uploads folder.
    $upload_info = wp_upload_dir();
    $uploads_basedir = trailingslashit( $upload_info['basedir'] );
    $dirs = glob( $uploads_basedir . '*', GLOB_ONLYDIR );
    $directory_names = array();
    if ( $dirs ) {
        foreach ( $dirs as $dir ) {
            $directory_names[] = basename( $dir );
        }
    }
    $options_html = '';
    foreach ( $directory_names as $dirname ) {
        $options_html .= '<option value="' . esc_attr( $dirname ) . '">' . esc_html( $dirname ) . '</option>';
    }

    // Save settings if the form is submitted.
    if ( isset( $_POST['urm_settings'] ) ) {
        check_admin_referer( 'urm_save_settings', 'urm_nonce' );
        $new_settings = array();
        if ( is_array( $_POST['urm_settings'] ) ) {
            foreach ( $_POST['urm_settings'] as $setting ) {
                $directory = sanitize_text_field( $setting['directory'] );
                $days_to_keep = intval( $setting['days_to_keep'] );
                $exclude_filenames = sanitize_text_field( $setting['exclude_filenames'] );
                if ( ! empty( $directory ) && $days_to_keep > 0 ) {
                    $new_settings[] = array(
                        'directory'         => $directory,
                        'days_to_keep'      => $days_to_keep,
                        'exclude_filenames' => $exclude_filenames,
                    );
                }
            }
        }
        update_option( URM_SETTINGS_OPTION, $new_settings );
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $settings = get_option( URM_SETTINGS_OPTION, array() );
    $logs = get_option( URM_LOG_OPTION, array() );
    ?>
    <div class="wrap">
        <h1>Upload Retention Manager Settings</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'urm_save_settings', 'urm_nonce' ); ?>
            <table id="urm_settings_table" class="widefat">
                <thead>
                    <tr>
                        <th>Directory (relative to uploads)</th>
                        <th>Days to Keep Files</th>
                        <th>Exclude Filenames (comma separated)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! empty( $settings ) ) : ?>
                    <?php foreach ( $settings as $index => $setting ) : 
                        $selected_value = isset( $setting['directory'] ) ? $setting['directory'] : '';
                    ?>
                    <tr>
                        <td>
                            <select name="urm_settings[<?php echo $index; ?>][directory]">
                                <?php
                                // If the saved value isn’t in the available directories, add it.
                                if ( ! in_array( $selected_value, $directory_names ) && ! empty( $selected_value ) ) {
                                    echo '<option value="' . esc_attr( $selected_value ) . '" selected="selected">' . esc_html( $selected_value ) . '</option>';
                                }
                                foreach ( $directory_names as $dirname ) {
                                    $selected = ( $selected_value === $dirname ) ? 'selected="selected"' : '';
                                    echo '<option value="' . esc_attr( $dirname ) . '" ' . $selected . '>' . esc_html( $dirname ) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                        <td>
                            <input type="number" name="urm_settings[<?php echo $index; ?>][days_to_keep]" value="<?php echo esc_attr( $setting['days_to_keep'] ); ?>" min="1" />
                        </td>
                        <td>
                            <input type="text" name="urm_settings[<?php echo $index; ?>][exclude_filenames]" value="<?php echo isset($setting['exclude_filenames']) ? esc_attr( $setting['exclude_filenames'] ) : ''; ?>" />
                        </td>
                        <td>
                            <button class="remove_row button" type="button">Remove</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            <p>
                <button id="add_row" class="button" type="button">Add New Directory</button>
            </p>
            <?php submit_button(); ?>
        </form>

        <h2>Last 14 Cron Runs</h2>
        <table id="urm_log_table" class="widefat">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Summary</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $logs ) ) : ?>
                <?php 
                // Show most recent first.
                $logs = array_reverse( $logs );
                foreach ( $logs as $index => $log ) : 
                    // Convert the stored UTC timestamp to local site time.
                    $readable_time = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $log['timestamp'] ), get_option('date_format') . ' ' . get_option('time_format') );
                ?>
                <tr>
                    <td><?php echo esc_html( $readable_time ); ?></td>
                    <td><?php echo esc_html( $log['summary'] ); ?></td>
                    <td>
                        <button class="urm-view-detail button" data-log-id="<?php echo $index; ?>">View Details</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">No cron runs logged yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php 
        // Output hidden detail divs for each log entry.
        if ( ! empty( $logs ) ) :
            foreach ( $logs as $index => $log ) :
                ob_start();
                ?>
                <div class="urm-log-detail-content">
                    <h3>Cron Run at <?php echo get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $log['timestamp'] ), get_option('date_format') . ' ' . get_option('time_format') ); ?></h3>
                    <?php if ( ! empty( $log['details'] ) ) : ?>
                        <?php foreach ( $log['details'] as $detail ) : ?>
                            <h4>Directory: <?php echo esc_html( $detail['directory'] ); ?></h4>
                            <p>Deleted Files: <?php echo intval( $detail['deleted_count'] ); ?></p>
                            <?php if ( ! empty( $detail['files'] ) ) : ?>
                                <ul>
                                    <?php foreach ( $detail['files'] as $file ) : ?>
                                        <li><?php echo esc_html( $file ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No details available.</p>
                    <?php endif; ?>
                </div>
                <?php 
                $detail_html = ob_get_clean();
                echo '<div id="urm-log-detail-' . $index . '" class="urm-log-detail" style="display:none;">' . $detail_html . '</div>';
            endforeach;
        endif;
        ?>
        <!-- Modal container -->
        <div id="urm-modal" class="urm-modal" style="display:none;">
            <div class="urm-modal-content">
                <span class="urm-modal-close">&times;</span>
                <div id="urm-modal-body"></div>
            </div>
        </div>
    </div>
    <?php
}


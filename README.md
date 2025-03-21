# Upload Retention Manager

**Upload Retention Manager** is a WordPress plugin that automatically deletes old files from specified directories within the WordPress uploads folder. It allows you to set custom retention periods for multiple directories, exclude specific filenames from deletion, and logs detailed information about each cron run.

## Features

- **Automated File Deletion:**  
  Deletes files older than a specified number of days for each configured directory.

- **Multiple Directory Support:**  
  Configure multiple directories (relative to the uploads folder) with individual retention settings.

- **Exclusion List:**  
  Specify a comma-separated list of filenames that should not be deleted.

- **Detailed Cron Logs:**  
  Logs every cron run (stored as UTC) and displays the last 14 cron runs on the admin settings page. When viewing, timestamps are converted to your site's local timezone.

- **View Details Modal:**  
  Each log entry includes a "View Details" button that opens a modal popup listing the deleted files in bullet-point format.

- **User-Friendly Admin Interface:**  
  Easily add, edit, or remove directory settings from a dedicated settings page in the WordPress admin area.

## Requirements

- **WordPress:** 4.0 or higher (WordPress Cron is used for scheduling)
- **PHP:** 5.6 or higher

## Installation

1. **Clone or Download:**  
   Clone the repository or download the ZIP file.

2. **Upload Files:**  
   Upload the entire `upload-retention-manager` folder to your `/wp-content/plugins/` directory.

3. **Activate the Plugin:**  
   In your WordPress dashboard, go to **Plugins** and activate **Upload Retention Manager**.

## Usage

1. **Configure Settings:**  
   Navigate to **Settings > Upload Retention Manager**.  
   - **Directory:** Select a directory (relative to the uploads folder).
   - **Days to Keep Files:** Set the retention period in days.
   - **Exclude Filenames:** Enter filenames (comma separated) that you want to protect from deletion.
   
2. **Cron Runs:**  
   The plugin schedules a daily cron job that checks each configured directory and deletes files older than the specified retention period.
   
3. **View Logs:**  
   The settings page displays the last 14 cron runs with a summary. Click the "View Details" button on any log entry to see a bullet-point list of deleted files in a modal popup.

## Testing

To test file deletion:

- Create or upload test files to one of your configured directories.
- Set a low retention period (e.g., 1 day) and adjust file modification times if necessary.
- Trigger the cron job manually (using a plugin like WP Crontrol or adding temporary code to call `do_action('urm_cron_event')`).
- Check the directory and review the cron logs on the settings page.

## Cron Schedule

The plugin uses WordPress Cron to schedule a daily run. The cron job is triggered by site visits once the scheduled time has passed. The UTC timestamp is stored in the database, then converted to your site's local time when displayed.

## Contributing

Contributions are welcome! Feel free to fork the repository, create a feature branch, and submit a pull request.

## License

This plugin is released under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

**In2Digital Communications**  
(https://github.com/in2digital/upload-retention-manager)

<?php

/**
 * created on March-01-2017, Aleksandar Ugrenovic
 * MONITOR FILE CHANGES AND UPLOAD THEM TO NAS SERVER, ADDING A TIMESTAMP (FILE VERSIONING SYSTEM)
 *
 * all parameters and credentials are stored in "settings-inc.php"
 * the file can be managed (updates and new entries) through "IT Applications -> Server Settings"
 */
 
// Init the vars, and global variables
// Settings should be changed here, nothing to do in the main loop, or functions, unless behavior modification.
$cfg_elem_1                         = 'Backup'; // define what settings field we'll be using
$cfg_elem_2                         = 'targets'; // $settings['backup']['targets'] for example:
$cfg_target_dirs                    = 'targets_dirs';
$cfg_target_recursive               = 'targets_recursive';
$cfg_target_files                   = 'targets_files';
$run                                = true;
$my_location                        = __DIR__;
#$inotify_descriptors                = [];
$descriptors['dirs']                = [];
$descriptors['recursive']           = [];
$descriptors['files']               = [];
$date                               = date("Y-m-d_H:i:s");
$date_file_friendly                 = date("Y-m-d_H-i-s");
$hostname                           = gethostname();
#$config_file                        = '/usr/local/bin/sett/inotify/settings-inc.php'; // TODO .. all should be relative to script home dir
$config_file                        = '/var/www/html/includes/inc/settings-inc.php';
$notif_address                      = 'alek.th85@gmail.com';
$timeout                            = '5'; // Time(s) to wait after every iteration
$memory_notif_limit                 = '490733568'; // 468 MB
$script_dir                         = '/usr/local/bin/sett/inotify';
$tmp_backup_dir                     = $script_dir . '/failed_ftp_uploads';
$running_config                     = null;
$settings[$cfg_elem_1][$cfg_elem_2] = '';
#$old_settings[$cfg_elem_1][$cfg_elem_2]  = '';
$first_run                          = true;
$meta['poll']       = 0;
$meta['iteration']  = 0;
$meta['ecount']     = 0;
$meta['fcount']     = 0;

// Require functions
require_once($script_dir . '/monitor-functions.php');

// Initialize inotify subsystem, and set some helpful php settings
$inotify_fd = inotify_init();
stream_set_blocking($inotify_fd, 0);
set_time_limit(0);
ini_set('memory_limit', '512M');

// Set notification if script shuts down
register_shutdown_function('shutdown');

$iteration = 0;
while ($run === true)
{
    echo "\n Iteration: $iteration - Poll: " . $meta['poll'] . " ... - \n";
    
    if (read_config($config_file) === TRUE)
    {
        // config has been re-read, clear all descriptors, and set new on all targets.
        if ($first_run == FALSE)
        {
            remove_all_descriptors();
        }
        
        // Set new descriptors per target class
        $validated_targets_NRC = validate_paths($settings[$cfg_elem_1][$cfg_target_dirs]);
        set_descriptors($validated_targets_NRC, FALSE, "recursive");
        
        $validated_targets_RC = validate_paths($settings[$cfg_elem_1][$cfg_target_recursive]);
        set_descriptors($validated_targets_RC, TRUE, "dirs");
        
        $validated_targets_FLS = validate_paths($settings[$cfg_elem_1][$cfg_target_files]);
        set_descriptors($validated_targets_FLS, FALSE, "files");
    }
    
    // Config has been read, and descriptors set. Process messages now
    process_messages();
    
    $iteration++;
    sleep($timeout);
    $first_run = false;
}

end_it_all();



<?php

// Define the constants
$wd_constants = array(
    1 => array('IN_ACCESS','File was accessed (read)'),
    2 => array('IN_MODIFY','File was modified'),
    4 => array('IN_ATTRIB','Metadata changed (e.g. permissions, mtime, etc.)'),
    8 => array('IN_CLOSE_WRITE','File opened for writing was closed'),
    16 => array('IN_CLOSE_NOWRITE','File not opened for writing was closed'),
    32 => array('IN_OPEN','File was opened'),
    128 => array('IN_MOVED_TO','File moved into watched directory'),
    64 => array('IN_MOVED_FROM','File moved out of watched directory'),
    256 => array('IN_CREATE','File or directory created in watched directory'),
    512 => array('IN_DELETE','File or directory deleted in watched directory'),
    1024 => array('IN_DELETE_SELF','Watched file or directory was deleted'),
    2048 => array('IN_MOVE_SELF','Watch file or directory was moved'),
    24 => array('IN_CLOSE','Equals to IN_CLOSE_WRITE | IN_CLOSE_NOWRITE'),
    192 => array('IN_MOVE','Equals to IN_MOVED_FROM | IN_MOVED_TO'),
    4095 => array('IN_ALL_EVENTS','Bitmask of all the above constants'),
    8192 => array('IN_UNMOUNT','File system containing watched object was unmounted'),
    16384 => array('IN_Q_OVERFLOW','Event queue overflowed (wd is -1 for this event)'),
    32768 => array('IN_IGNORED','Watch was removed (explicitly by inotify_rm_watch() or because file was removed or filesystem unmounted'),
    1073741824 => array('IN_ISDIR','Subject of this event is a directory'),
    1073741840 => array('IN_CLOSE_NOWRITE','High-bit: File not opened for writing was closed'),
    1073741856 => array('IN_OPEN','High-bit: File was opened'),
    1073742080 => array('IN_CREATE','High-bit: File or directory created in watched directory'),
    1073742336 => array('IN_DELETE','High-bit: File or directory deleted in watched directory'),
    16777216 => array('IN_ONLYDIR','Only watch pathname if it is a directory (Since Linux 2.6.15)'),
    33554432 => array('IN_DONT_FOLLOW','Do not dereference pathname if it is a symlink (Since Linux 2.6.15)'),
    536870912 => array('IN_MASK_ADD','Add events to watch mask for this pathname if it already exists (instead of replacing mask).'),
    2147483648 => array('IN_ONESHOT','Monitor pathname for one event, then remove from watch list.')
);

/**
 * @description Reads the paths from config
 * @param $config Config file location
 * @return array of paths
 */
function read_config($config)
{
    if (!file_exists($config))
    {
        return false;
    }
    
    global $settings;
    global $old_settings;
    global $cfg_elem_1;
    global $cfg_elem_2;
    global $running_version;
    global $first_run;
    
    $content = str_replace("<?php", "", file_get_contents($config));
    $serialized_content = serialize($content);
    if ($serialized_content == $running_version)
    {
        // Config has not changed... ignore
        echo "Config not changed, not reloading. \n";
        echo "Running config length: " . strlen($running_version) . " -- New config length: " . strlen($serialized_content) . "\n";
        
        return FALSE;
    } else {
        eval($content);
        $running_version = $serialized_content;
        echo "Config changed, reloading ... ";
        
        if (!isset($settings['Backup']['targets_recursive']) || !isset($settings['Backup']['targets_files']) || !isset($settings['Backup']['targets_dirs']))
        {
            // No settings ?
            echo "No settings detected ? \n";
            $running_version = array();
            $serialized_content = '';
            
            drop_descriptors();
            
            return FALSE;
        }
        
        return TRUE;
    }
    echo "Loaded configuration file \n";
    $first_run = false;
}

/**
 * @description End it all.
 * @return nothing.
 */
function end_it_all()
{
    global $descriptors;
    
    foreach ($descriptors as $key => $descriptor)
    {
        inotify_rm_watch($inotify_fd, $key);
    }
    fclose($inotify_fd);
    exit(0);
}

/**
 * @description Sets the watchlist
 * @param $targets List of directories to monitor. This should be validated first by validate_paths function
 * @param $recursive Recursively set descriptors, or not boolean
 * @return array of watch descriptors
 */
function set_descriptors(Array $targets, $recursive = TRUE, $type)
{
    global $inotify_fd;
    global $inotify_descriptors;
    global $descriptors;
    
    foreach ($targets as $key => $btarget)
    {
        
        $watch_id = inotify_add_watch($inotify_fd, $btarget, 8 | 536870912 | 2048);
        #$inotify_descriptors[$watch_id] = $btarget;
        $descriptors[$type][$watch_id] = $btarget;
        echo "Listening on: $btarget, at descriptor: $watch_id \n";
        
        if ($type == "files")
        {
            echo "Adding additional descriptor on another bitmask (8) IN_CLOSE_WRITE...\n";
            #$watch_id_8 = inotify_add_watch($inotify_fd, $btarget, 536870912);
            $watch_id_88 = inotify_add_watch($inotify_fd, $btarget, 8);
            #echo "Added additional descriptor, bitmask 8: $watch_id_88 \n";
            $descriptors[$type][$watch_id_88] = $btarget;
        }
        
        if (is_dir($btarget))
        {
            if ($recursive == TRUE)
            {
                // Get all subfolders in this directory, and recursively listen on them too.
                $subs = recursive_iteration($btarget);
                foreach ($subs as $skey => $subtarget)
                {
                    $sub_watch_id = inotify_add_watch($inotify_fd, $subtarget, 2);
                    //$inotify_descriptors[$sub_watch_id] = $subtarget;
                    $descriptors[$type][$sub_watch_id] = $subtarget;
                }
            }
            
            if ($recursive != TRUE)
            {
                $sub_watch_id_nrc = inotify_add_watch($inotify_fd, $btarget, 2);
                #$inotify_descriptors[$sub_watch_id_nrc] = $btarget;
                $descriptors[$type][$sub_watch_id_nrc] = $btarget;
            }
        } 
    }
    #return $inotify_descriptors;
    return $descriptors;
}

function add_descriptor($fd, $type)
{
    global $inotify_fd;
    global $inotify_descriptors;
    global $descriptors;
    
    echo "New descriptor... ";
    $watch_id = inotify_add_watch($inotify_fd, rtrim($fd, "/"), 8 | 32768 | 2048);
    #$inotify_descriptors[$watch_id] = $fd;
    $descriptors[$type][$watch_id] = $fd;
    echo "Added new descriptor: $watch_id \n";
}

function drop_descriptors()
{
    global $inotify_fd;
    
    fclose($inotify_fd);
    
    $inotify_fd = inotify_init();
    stream_set_blocking($inotify_fd, 0);
    
    #global $descriptors;
    
    #foreach ($descriptors as $dkey => $dval)
    #{
    #    if (isset($dval[$event['wd']]))
    #     {
    #        $path = $dval[$event['wd']] . '/' . $event['name'];
    #     }
    # }
    
}

/**
 * @description Validate paths, take in string (delimited by comma), return validated array
 * @param $paths Array of directories given to us to monitor
 * @return Returns validated array converted from string, so descriptors can be set on each entry
 */
function validate_paths($paths_string)
{
    global $notification_address;
    
    $paths = explode(",", $paths_string);
    foreach ($paths as $key => $val)
    {
        if (!file_exists($val))
        {
            notify($notification_address, "Inode file monitor - WARNING: $val does not exist.");
            unset($paths[$key]);
        }
    }
    
    return $paths;
}

/**
 * @description Notification
 * @param $address To whom we send notifications
 * @param $line The body
 * @param $type not in use
 * @return Returns true, as we have no way of knowing if the email is actually sent or not in this configuration.
 */
function notify($address, $line, $type = "Failure")
{
    global $hostname;
    global $date;
    
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=iso-8859-1";
    
    $headers[] = "To: Administrator <$address>";
    $headers[] = "From: Inotify manager <all@domain>";
    
    $subject = "Inotify event on $host";
    $message = "The following event has happened on $host: " . PHP_EOL;
    $message .= $line . PHP_EOL;
    $message .= "-- " . $date;
    
    // Mail it
    mail($who, $subject, $message, implode("\r\n", $headers));
    return true;
}

function shutdown()
{
    global $hostname;
    notify($notif_address, "Inotify monitor is shutting down on $hostname");
    
    // Attempt cleanup
    end_it_all();
}

function check_okrun()
{
    if (!extension_loaded('inotify'))
    {
        echo "Inotify extension is not loaded, or is not callable\n";
        echo "Install: http://php.net/manual/en/book.inotify.php \n";
        exit(1);
    }
}

/**
 * @description Ftp uploader
 * @param $upload_file
 * @return boolean, true or false
 * @todo log failures, and try to upload failed file on next call + notify()
 */
function ftp_upload($upload_file)
{
    echo "Starting FTP upload function... \n";
    global $settings;
    
    $progress_info = array(
        'connect_failed'    => false,
        'auth_failed'       => false,
        'put_failed'        => false            
    );
    
    $date_file_friendly = date("Y-m-d_H-i-s");
    
    $ftp_backup_server = $settings['Backup']['file_backup_server']; // usually NAS: 192.168.0.236
    $file_dest         = $settings['Backup']['file_backup_NAS_path']; // $file_dest example: "volume_1/backup_C8/"
    $file_destination  = $ftp_backup_server . '/' . $file_dest; // $file_destination example: "192.168.0.236/volume_1/backup_C8/hourly"
    $target_url        = '/' . $file_dest;
    
    $ftp_backup_user = $settings['Backup']['file_backup_user'];
    $ftp_backup_pwd  = $settings['Backup']['file_backup_pwd'];
    
    // Open a file, necessary to be done here, in case ftp fail
    $upload_file = sanitize_filepath_ftp($upload_file);
    $fp = fopen($upload_file, 'r');
    
    if ($fp === FALSE)
    {
        // File does not exist ? Probably a temporary file created by editors which got removed in the process.
        // Stop processing here, it will just generate errors.
        echo "Cannot open file \n";
        return;
    }
    
    $conn_id      = ftp_connect($ftp_backup_server, 21, 10);
    $login_result = ftp_login($conn_id, $ftp_backup_user, $ftp_backup_pwd);
    
    if ($conn_id === FALSE)
    {
        echo("Cannot connect to FTP, connection failed.\n");
        $progress_info['connect_failed'] = TRUE;
    }
    
    if ($login_result === FALSE)
    {
        echo("Cannot upload to FTP, login failed.\n");
        $progress_info['auth_failed'] = TRUE;
    }
    
    if ( ($progress_info['auth_failed'] == TRUE) || ($progress_info['connect_failed'] ==  TRUE) )
    {
        // We either didn't connect, or authorize. In both cases, return false.
        echo "FTP Failed. Files stored in tmp directory. Attempt to upload them on next event.\n";
        // $ftpdata['ftp_resource'], $ftpdata['upload_file'], $ftpdata['target_url']
        store_to_tmp(array('ftp_resource' => null, 'upload_file' => $upload_file, 'target_url' => $target_url), $fp );
        
        return false;
    }
    
    echo "Ftp connection successful .. logged in. \n";
    ftp_pasv($conn_id, true);
    
    create_recursive_dirs($conn_id, $upload_file, $target_url);
    
    $target_urlwf = basename($upload_file) . '.' . $date_file_friendly;
    echo "Uploading to $target_urlwf .. "; // /volume_1/backup_C81/files/test1sa/testf
    
    if (ftp_fput($conn_id, $target_urlwf, $fp, FTP_BINARY) == FALSE)
    {
        echo("Cannot put the file on FTP. Target: $target_url \n");
        return false;
    }
    fclose($fp);
    
    echo "Success! ";
    echo "FTP link closed\n";
    return true;
}

/**
 * @description When target is a file, inotify extension adds trailing slash to it; Quick fix.
 * @param $path path to file with a trailing slash.
 * @return string without trailing slash
 */
function sanitize_filepath_ftp($path)
{
    $pathinfo   = pathinfo($path);
    
    if (substr($path, -1) == "/")
    {
        $path = rtrim($path, "/");
    }
    
    return $path;
}

/**
 * @description Recursively find $root subfolders
 * @param $root root path to find subfolders in
 * @return array of subfolders
 */
function recursive_iteration($root)
{

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
        RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
    );

    $paths = array($root);
    foreach ($iter as $path => $dir) {
        if ($dir->isDir()) {
            $paths[] = $path;
        }
    }
    
    return $paths;
}

function store_to_tmp($ftpdata, $file_resource)
{
    echo "Storing to local temporary storage ... \n";
    create_recursive_dirs($ftpdata['ftp_resource'], $ftpdata['upload_file'], $ftpdata['target_url'], TRUE);
}

/**
 * @description Recursively create directories in a rooth path, if needed. It also copies the file in the newly created path
 * @param $ftp_resource Ftp connection handle
 * @param $upload_file File that needs to be uploaded, for pathinfos
 * @param $target_url Where the file needs to be located, so we can create a path
 * @param $local If we're doing this on FTP, or locally in a folder
 * @return boolean, true or false.
 */
function create_recursive_dirs($ftp_resource, $upload_file, $target_url, $local = FALSE)
{
    global $tmp_backup_dir;
    global $notif_address;
    
    if ($local == TRUE)
    {
        // This is a local operation, means FTP server is down and we're storing files locally for future upload attempt
        $pathinfo = pathinfo($upload_file);
        if (chdir($tmp_backup_dir) == FALSE)
        {
            // no backup dir ? try to create, if fail again, exit and notify.
            if (mkdir($tmp_backup_dir) == FALSE)
            {
                notify($notif_address, "Local folder creation failed, while FTP is offline. \n");
                return false;
            } else {
                #chdir($tmp_backup_dir);
                create_recursive_dirs($ftp_resource, $upload_file, $target_url, TRUE);
            }
        }
        
        echo "Current working dir: " . getcwd() . '\n';
        $parts = explode('/', ltrim($pathinfo['dirname'], '/')); // 2013/06/11/username
        foreach($parts as $part)
        {
            if (!@chdir($part))
            {
                mkdir($part);
                chdir($part);
            }
        }
        
        echo "Copying from $upload_file to " . basename($upload_file) . PHP_EOL;
        copy($upload_file, getcwd() . '/' . basename($upload_file));
        
        return TRUE;
    }
    
    // Get path information, we need to create folders on remote FTP server so we can store this file properly.
    $pathinfo = pathinfo($upload_file);
    @ftp_chdir($ftp_resource, $target_url); // /var/www/uploads
    $parts = explode('/', ltrim($pathinfo['dirname'], '/')); // 2013/06/11/username
    foreach($parts as $part)
    {
        if(!@ftp_chdir($ftp_resource, $part))
        {
            ftp_mkdir($ftp_resource, $part);
            ftp_chdir($ftp_resource, $part);
            //ftp_chmod($ftpcon, 0777, $part);
        }
    }
}

function write_to_failed_dir($path)
{
    global $tmp_backup_dir;
}

function remove_all_descriptors()
{
    global $descriptors;
    global $inotify_fd;
    
    var_dump($descriptors);
    
    foreach ($descriptors as $dvals)
    {
        foreach ($dvals as $key => $val)
        {
            echo "Removing descriptor: " . $key . PHP_EOL;
            inotify_rm_watch($inotify_fd, $key);
        }
    }
}

function process_messages()
{
    global $meta;
    global $inotify_fd;
    global $first_run;
    global $descriptors;
    global $wd_constants;
    
    echo "We have everything we need, processing events...\n";
    
    $meta['ecount'] = 0;
    $meta['fcount'] = 0;        
    $meta['poll']++;
    
    global $meta;
    
    $events = inotify_read($inotify_fd);
    $ecount = count($events);
    echo "=== ".date("Y-m-d H:i:s")." inotify poll #". $meta['poll'] . " contains ".$meta['ecount']." events, (Descriptors opened: ";
    
    #echo implode(", ", array_keys($inotify_descriptors));
    echo ")";
    
    if ($events)
    {
        foreach ($events as $event)
        {
            // IN_CLOSE_WRITE 
            if ($event['mask'] == 8)
            {
                echo "in_close_write detected. Using VI much ? \n";
            }
                                    
            // Ignore IN_IGNORED events... 
            if ($event['mask'] == 32768)
            {
                echo "Ignoring event: " . $event['wd'] . PHP_EOL;
                $path_removed = '';
                foreach ($descriptors as $dkey => $dval)
                {
                    if (isset($dval[$event['wd']]))
                    {
                        $path_removed = $dval[$event['wd']] . '/' . $event['name'];
                    }
                }
                if ($path_removed != '')
                {
                    add_descriptor($path_removed, 'files');
                }
                #echo "Removed path: " . $path_removed . "\n";
                
                // Add watch for it again
                #add_descriptor($inotify_descriptors[$event['wd'], TRUE, ]);
                # TODO
                continue;
            }
            
            $meta['fcount']++;
            $descr = $event['wd'];
            #$path = $inotify_descriptors[$descr] . '/' . $event['name'];
            #$descriptors[]
            $path = '';
            $a = array_search($event['wd'], $descriptors);
            foreach ($descriptors as $dkey => $dval)
            {
                if (isset($dval[$event['wd']]))
                {
                    $path = $dval[$event['wd']] . '/' . $event['name'];
                }
            }
            //var_dump($event);
            echo "\n        inotify Event #".$meta['fcount']." - Object: ".$path.": ".$wd_constants[$event['mask']][0]." (".$wd_constants[$event['mask']][1].")\n"; 
            ftp_upload($path);
        }
    }
}


#!/usr/bin/php
<?php

  // Flash Air Auto Sync, copies all images from flash air card when it is available
  // and drops them in a local folder.

  // Default Settings override these by passing arguments at the command line
  // eg. php FlashAirSync.php --syncto=/home/boffin/Documents/FlashAir

  $defaults = [
        'flashairip' => '192.168.0.2',
        'syncfrom' => '/DCIM',
        'syncto' => getenv('HOME') . '/FlashAir',
        'timezone' => 'GMT'
  ];

  $options = getopt('', ['flashairip::', 'syncfrom::', 'syncto::', 'timezone::']) + $defaults;

  $FlashAirIP  = $options['flashairip']; // IP address of the flashair card, if the card is running in the default host mode you could just use the hostname ("flashair")
  $SyncFrom    = $options['syncfrom'];         // Path on the sdcard to copy from (recursive)
  $SyncTo      = $options['syncto']; // Full path where it will copy to (recursive)
  $TZ          = $options['timezone'];          // Camera Timezone

  // If the camera isn't online, it quits.  If the camera goes offline during the processing, the next time it
  // will do a full update.

  // It only copies a file once.

  // Deletions on the camera will NOT propogate to the target - camera space is tight so you are free
  //   to delete files there without losing the off-camera copy.

  // If you delete a file from the target directory, that deletion WILL propogate to the camera
  //   because the assumption is, it's a junk image you don't want, so no need for it on camera either.

  // Modifications on the camera will NOT propogate to the target - you're not modifying images on the camera
  //   in any meaningful way.

  // Modifications in the target will NOT propogate to the camera - the camera will always have the "original".

  // Sample config which puts card in wifi client mode (so it connects to your existing wifi router/access point), with upload enabled
  //   the config file is SD_WLAN/CONFIG on the sdcard, just edit then turn off and on the card to reload.

  // For more info:
  //  https://flashair-developers.com/en/documents/
  //  https://flashair-developers.com/en/documents/api/
  //  http://www.extrud3d.com/flashair
  /*

[Vendor]

CIPATH=/DCIM/100__TSB/FA000001.JPG
APPMODE=5
APPNETWORKKEY=YOUR_NETWORK_KEY_HERE
APPSSID=YOUR_SSID_HERE
VERSION=F24A6W3AW1.00.03
CID=02544d5357303847075000c0bf00c801
PRODUCT=FlashAir
VENDOR=TOSHIBA
MASTERCODE=00216b97d78a
LOCK=1
APPNAME=flashair
UPLOAD=1

  */



  if(!file_exists($SyncTo))
  {
    mkdir($SyncTo);
  }

  $ForceUpdate = (!file_exists($SyncTo . '/.Last_Update'))
                 ||file_exists($SyncTo.'/.Force_Update') ;
  @unlink($SyncTo.'/.Force_Update');

  // Files which have been copied in the past, we use this when we see
  // a file not present on the local, if it was copied in the past it must have
  // been deleted, so we will propogate that deletion back to the card.
  function was_deleted($Destination)
  {
    if(file_exists($Destination)) return false;
    if(!file_exists(dirname($Destination) . '/.Manifest'))
    {
      mkdir(dirname($Destination) . '/.Manifest');
    }

    if(file_exists(dirname($Destination) . '/.Manifest/' . basename($Destination)))
    {
      return true;
    }

    return false;
  }


  function sync_for_file($From, $To, $Time)
  {
    global $FlashAirIP;
    if(was_deleted($To))
    {
      // Delete from card
      echo "Delete {$From}\n";
      command("upload.cgi", array('DEL' => $From));
      unlink(dirname($To) . '/.Manifest/'.basename($To));
    }
    elseif(!file_exists($To))
    {
      if(copy("http://{$FlashAirIP}".$From, $To))
      {
        echo "Copy {$From}\n";
        touch($To, $Time);
        // Add it to the manifest
        touch(dirname($To) . '/.Manifest/'.basename($To), $Time);
      }
      else
      {
        if(!alive())
        {
          force_next_update();
          exit;
        }
      }
    }
  }

  // Check to see if the card is on the network
  function alive()
  {
    global $FlashAirIP;
    $RC = 1;
    system("ping -c 1 $FlashAirIP >/dev/null 2>/dev/null", $RC);
    return !$RC;
  }

  // Write a flag to fo a forced update the next time
  // this happens if we are interrupted by the camera going
  // into power down during a sync
  function force_next_update()
  {
    global $SyncTo;
    touch($SyncTo . '/.Force_Update');
    echo "Interrupted during processing.\n";
  }

  function command($Op, $Args = array())
  {
    global $FlashAirIP;
    if(!alive())
    {
      force_next_update();
      exit;
    }

    $Command = is_numeric($Op) ? "http://{$FlashAirIP}/command.cgi?op=$Op" : "http://{$FlashAirIP}/{$Op}?__DUMMY__=1";

    foreach($Args as $k => $v)
    {
      $Command .= "&$k=".rawurlencode($v);
    }

    $Contents = file_get_contents($Command);
    if($Contents === FALSE)
    {
      force_next_update();
      exit;
    }
    return $Contents;
  }

  if(!alive())
  {
    echo "Not Online\n";
    exit;
  }

  if(!$ForceUpdate && command(102) == 0)
  {
    echo "No Changes\n";
    exit;
  }

  function sync_dir($Dir, $To)
  {
    global $FlashAirIP, $TZ;
    $List = command(100, array('DIR' => $Dir));

    $List = preg_split('/\r?\n/', $List);

    foreach($List as $r)
    {
      $r = str_getcsv($r);
      if(count($r) < 3) continue;
      if($r[3] & 16) // bit 5 = Directory
      {
        if(!file_exists($To . '/'.$r[1])) mkdir($To . '/' . $r[1]);
        sync_dir($Dir . '/' . $r[1], $To . '/' . $r[1]);
        continue;
      }

      // Else normal file
      $Day     =  ($r[4] & 0b0000000000011111);
      $Month   =  ($r[4] & 0b0000000111100000) >> 5;
      $Year    = (($r[4] & 0b1111111000000000) >> 9)+1980;

      $Seconds = ($r[5] & 0b0000000000011111) * 2;
      $Minutes = ($r[5] & 0b0000011111100000) >> 5;
      $Hours   = ($r[5] & 0b1111100000000000) >> 11;

      // echo "{$r[1]} {$Year}-{$Month}-{$Day} {$Hours}:{$Minutes}:{$Seconds}\n";
      // $Time = mktime($Hours, $Minutes, $Seconds, $Month, $Day, $Year);
      $Time = strtotime("{$Year}-{$Month}-{$Day} {$Hours}:{$Minutes}:{$Seconds} {$TZ}");
      sync_for_file($Dir . '/' . $r[1], $To . '/' . $r[1], $Time);
    }

  }

  sync_dir($SyncFrom, $SyncTo);
  touch($SyncTo . '/.Last_Update');
?>

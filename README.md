FlashAirSync
============

A simple automatic file synchronisation script for Toshiba FlashAir devices.

FlashAir Auto Sync, copies all images from flash air card when it is available and drops them in a local folder.  

Requires PHP 5.4 CLI

Pass Configuration variables by command line e.g.
```
php FlashAirSync.php --flashairip=192.168.0.2
```

Details
-------
If the camera isn't online, it quits.  If the camera goes offline during the processing, the next time it will do a full update.

It only copies a file once.  

Deletions on the camera will NOT propogate to the target - camera space is tight so you are free to delete files there without losing the off-camera copy.

If you delete a file from the target directory, that deletion WILL propogate to the camera because the assumption is, it's a junk image you don't want, so no need for it on camera either.

Modifications on the camera will NOT propogate to the target - you're not modifying images on the camera in any meaningfull way.

Modifications in the target will NOT propogate to the camera - the camera will always have the "original".

For more info:
  https://flashair-developers.com/en/documents/
  https://flashair-developers.com/en/documents/api/
  http://www.extrud3d.com/flashair

FlashAir Operating Mode
-----------------------
FlashAir cards normally operate in host mode, that is, they appear as a Wifi Network to which you connect.  

However they can also operate in Client mode (aka Station mode), that is they connect to your existing network and you can access them by IP address if you have your existing wifi router/api assign a fixed IP to them.

See the extrud3d link, and https://flashair-developers.com/en/documents/api/config/ for details.

In order to delete files on the card (when they are deleted on the destination directory), UPLOAD must be enabled in the FlashAir config file also to turn on the upload.cgi API.

Sample config which puts card in wifi client mode (so it connects to your existing wifi router/access point), with upload enabled  the config file is SD_WLAN/CONFIG on the sdcard, just edit then turn off and on the card to reload.

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

# S3Copy

- Contributors: aliuly
- Tags: cloud, cdn, S3
- Requires at least: 4.6
- Tested up to: 4.6.1
- Stable tag: 1.0
- License: GPLv3
- License URI: https://www.gnu.org/licenses/gpl-3.0.html

Plugin to copy photos to an S3 compatible host as they are uploaded to Wordpress.
It will also update URLs so that uploaded photos are served from the S3 compatible host.

## Description

This plugin creates backups of photos to an S3 compatible cloud.  This may be Amazon
S3 or any S3 compatible host.

It will also modify `img` tags so that photos are served from the S3 cloud.

## Installation

1. Download the plugin
2. Upload the ZIP file through the 'Plugins > Add New > Upload' screen in your WordPress dashboard
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the settings for this plugin.

<!-- ## FAQ -->

## Changelog

- 1.1: 
  - Show the site id (if multisite).  Useful when browsing S3 bucket directly.
  - Make it let's obnoxious if the Plugin is enabled but not configured.
  - Added more info on how to configure the thing.
  - Better <img> tag detection
- 1.0:
  Initial release.

### Credits

This plugin is based on ryanhellyer's [Photocopy](https://wordpress.org/plugins/photocopy/)
plugin that has been discontinued.

The S3 PHP class is from 
[Donovan Schönknecht](http://undesigned.org.za/2007/10/22/amazon-s3-php-class/).


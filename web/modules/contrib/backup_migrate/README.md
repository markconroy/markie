# Backup and Migrate

Back up and restore your Drupal MySQL database, code, and files, or migrate a
site between environments. Backup and Migrate supports gzip, bzip, and zip
compression, as well as automatic scheduled backups.

With Backup and Migrate you can dump some or all database tables to a file
download, save a backup to the server or an off-site destination, and restore
from an uploaded or previously saved database dump. You can choose which tables
and data to back up. Cache data is excluded by default.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/backup_migrate).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/backup_migrate).

## Table of contents

- Requirements
- Recommended modules
- Installation
- Configuration
- Encrypting backups
- Maintainers

## Requirements

This module requires no modules outside of Drupal core.

## Recommended modules

- [Backup and Migrate: Flysystem][backup-migrate-flysystem]

  Provides a wrapper around the Flysystem abstraction system, which allows a
  wide variety of backup destinations without additional changes to Backup and
  Migrate. See that module's README.md file for details.

## Installation

Install as you would normally install a contributed Drupal module. For more
information, see
[Installing Drupal modules][installing-modules].

## Configuration

1. Go to Administration » Configuration » Backup and Migrate.
2. Use the Backup tab to create quick or advanced backups.
3. For a quick backup, select the backup source and destination, then click
   Backup now.
4. For an advanced backup, configure the source, backup file, advanced
   settings, email settings, excluded database tables, excluded files, and
   destination.
5. Use the Restore tab to upload a backup file and select the restore
   destination.
6. Use the Saved Backups tab to review recent backups.
7. Use the Schedules tab to add backup schedules, settings profiles,
   destinations, and sources.

## Encrypting backups

To encrypt backup files, install the Defuse PHP-encryption library with
Composer:

`composer require defuse/php-encryption`

For more information, see
[Encrypting backups][encrypting-backups].

If that page is inaccessible, it may have been renamed. Try this URL instead:

- [https://www.drupal.org/node/3185484](https://www.drupal.org/node/3185484)

## Maintainers

- Damien McKenna - [damienmckenna](https://www.drupal.org/u/damienmckenna)
- Alexandru Andrascu - [alex-andrascu](https://www.drupal.org/u/alex-andrascu)
- Ronan - [ronan](https://www.drupal.org/u/ronan)
- Drew Gorton - [dgorton](https://www.drupal.org/u/dgorton)
- Daniel Pickering - [ikit-claw](https://www.drupal.org/u/ikit-claw)
- Keri Poeppe - [kpoeppe](https://www.drupal.org/u/kpoeppe)

[backup-migrate-flysystem]: https://www.drupal.org/project/backup_migrate_flysystem
[encrypting-backups]: https://www.drupal.org/docs/contributed-modules/backup-and-migrate/encrypting-backups
[installing-modules]: https://www.drupal.org/docs/extending-drupal/installing-drupal-modules

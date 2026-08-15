# Destinations

A destination in Backup and Migrate is the place where backup files are sent
after they are created, or where they are read from during a restore. The
simplest example of a destination is a directory on your web server.

An object implementing
`\Drupal\backup_migrate\Core\Destination\DestinationInterface` can be used as a
destination. It is responsible for persisting a file with the given ID,
generally the filename, and for returning the same file when requested with the
same file ID.

Destinations are implemented as plugins. Dependencies and configuration are
injected into them by the plugin manager.

Like other plugins, destinations are passed to the Backup and Migrate object by
the consuming application by calling the `add()` method on the plugin manager:

    $backup_migrate->destinations()->add(
      'destination1',
      new MyDestinationPlugin()
    );

A single Backup and Migrate instance can have more than one destination of a
given type. Each destination has a unique key that is used to pass
configuration to the destination object and to specify the destination when
running a `backup()` or `restore()` operation.

Only one destination is used during each backup or restore operation.

# Sources

A source in Backup and Migrate is a thing that can be backed up. This could be
a database or a file directory. An object that implements
`\Drupal\backup_migrate\Core\Source\SourceInterface` is responsible for
creating a single backup file that represents the specified source. It is also
responsible for restoring that source from a backup file.

Sources are implemented as plugins. Dependencies and configuration are injected
into them by the plugin manager.

A single Backup and Migrate instance can have more than one source of a given
type. Each source has a unique key that is used to pass configuration to the
source object and to specify the source when running a `backup()` or
`restore()` operation.

Like other plugins, sources are passed to the Backup and Migrate object by the
consuming application by calling the `add()` method on the sources plugin
manager:

    $backup_migrate->sources()->add('source1', new MySourcePlugin());

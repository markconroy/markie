# The Backup and Migrate object

The `\Drupal\backup_migrate\Core\Services\BackupMigrate` service exposes the
Backup and Migrate operations to the consuming application. By itself, it does
nothing. The consuming application must inject the plugins, configuration, and
supporting services needed to perform the work.

## Instantiating the object

Before it can be called, the Backup and Migrate object must be instantiated,
configured, and given all necessary plugins. This keeps the library simple,
allows greater flexibility, and preserves dependency inversion.

The service is instantiated by creating a new `BackupMigrate` object:

    use Drupal\backup_migrate\Core\Services\BackupMigrate;

    $bam = new BackupMigrate();

### Adding plugins

Destinations, sources, and filters are added to the object with a plugin
manager. Each plugin must be given a unique ID. This ID is used to configure
the plugin and to specify which source and destination are used during an
operation.

    use Drupal\backup_migrate\Core\Destination\BrowserDownloadDestination;
    use Drupal\backup_migrate\Core\Destination\DirectoryDestination;
    use Drupal\backup_migrate\Core\Filter\CompressionFilter;
    use Drupal\backup_migrate\Core\Filter\FileNamer;
    use Drupal\backup_migrate\Core\Plugin\PluginManager;
    use Drupal\backup_migrate\Core\Services\BackupMigrate;
    use Drupal\backup_migrate\Core\Services\Logger;
    use Drupal\backup_migrate\Core\Services\ServiceManager;
    use Drupal\backup_migrate\Core\Services\TempFileAdapter;
    use Drupal\backup_migrate\Core\Services\TempFileManager;
    use Drupal\backup_migrate\Core\Source\MySQLiSource;

    $services = new ServiceManager();

    // Add the services required by the plugins.
    $services->add(
      'TempFileManager',
      new TempFileManager(new TempFileAdapter('/tmp'))
    );
    $services->add('Logger', new Logger());

    $plugins = new PluginManager($services);

    // Add a source.
    $plugins->add('db1', new MySQLiSource());

    // Add destinations.
    $plugins->add('download', new BrowserDownloadDestination());
    $plugins->add('mydirectory', new DirectoryDestination());

    // Add filters.
    $plugins->add('compress', new CompressionFilter());
    $plugins->add('namer', new FileNamer());

    $bam = new BackupMigrate($plugins);

See: [Plugins][plugins]

### Providing services

If the consuming application needs to use plugins that interact with the
greater environment, such as saving state, emailing users, or creating
temporary files, it must provide services that allow Backup and Migrate to do
so. These services are contained in an environment object.

Create an environment object and pass it to the service constructor. If you do
not pass an environment, a basic one is created for the simplest environments.

    use Drupal\backup_migrate\Core\Services\BackupMigrate;
    use MyAPP\Environment\MyEnvironment;

    // Create a custom environment with services and configuration needed by
    // the application.
    $env = new MyEnvironment();

    // Pass the environment to the service.
    $bam = new BackupMigrate($env);

See: [Environment][environment]

### Configuring the object

The `BackupMigrate` object does not have configuration of its own, but the
injected plugins and services may. Services should be configured before they
are passed to the `ServiceManager`. Plugins can be configured when they are
created and passed to the plugin manager, or additional configuration can be
passed by calling `setConfig()` on the plugin manager.

Often, a combination of these techniques is used. Base configuration is passed
to the plugin when it is instantiated, and run-time configuration is passed in
later.

See: [Configuration][configuration]

## Operations

The Backup and Migrate service provides two main operations:

- `backup($source_id, $destination_id)`
- `restore($source_id, $destination_id, $file_id)`

### The backup operation

The `backup()` operation creates a backup file from the specified source,
post-processes the file with all installed filters, and saves the file to the
specified destination.

The parameters for this operation are:

- **$source_id** ***(string)*** - The ID of the source as specified when it is
  added to the plugin manager.
- **$destination_id** ***(string|array)*** - The ID of the destination as
  specified when it is added to the plugin manager. This can also be an array
  of destination IDs to send the backup to multiple destinations.

There is no return value, but an exception may be thrown if there is an error.

    // Create a Backup and Migrate service object.
    $bam = new BackupMigrate($plugins);

    // Run the backup.
    $bam->backup('db1', 'mydirectory');

### The restore operation

The `restore()` operation loads the specified file from the specified
destination, pre-processes the file with all installed filters, and restores
the data to the specified source.

The parameters are:

- **$source_id** ***(string)*** - The ID of the source as specified when it is
  added to the plugin manager.
- **$destination_id** ***(string)*** - The ID of the destination as specified
  when it is added to the plugin manager.
- **$file_id** ***(string)*** - The ID of the file within the destination. This
  is usually the filename, but can be any unique string specified by the
  destination.

    // Create a Backup and Migrate service object.
    $bam = new BackupMigrate($plugins);

    // Run the restore.
    $bam->restore('db1', 'mydirectory', 'backup.mysql.gz');

[configuration]: https://github.com/backupmigrate/backup_migrate_core/tree/master/src/Config
[environment]: https://github.com/backupmigrate/backup_migrate_core/tree/master/src/Environment
[plugins]: https://github.com/backupmigrate/backup_migrate_core/tree/master/src/Plugin

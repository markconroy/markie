# Plugins and the plugin manager

**Plugins** do the actual work in Backup and Migrate. **The plugin manager**
manages the configuration of all installed plugins and calls plugins during an
operation.

## Plugins

Plugins may be one of the following types:

- **Sources** - Items that can be backed up and restored, such as a MySQL
  database.
- **Destinations** - Places where backup files can be stored, such as a
  directory on your server.
- **Filters** - Actions that can be performed on backup files after backup or
  before restore, such as gzip compression.

While these three plugin types are conceptually separate, they are technically
identical.

### Sources

Each backup and restore operation works on a single source. For simplicity,
more than one source may be added to the `BackupMigrate` object. The source to
be backed up is identified by ID when `backup()` or `restore()` is called.

See: [Sources][sources]

### Destinations

Destinations are the places where backup files are sent during `backup()` or
loaded from during `restore()`. Restore operations load a file from a single
destination. Backup operations save the backup file to one or more specified
destinations.

See: [Destinations][destinations]

### Filters

Filters can alter backup files before `restore()` or after `backup()`. Unlike
sources and destinations, many filters can run per operation. During an
operation, all installed filters run unless they are configured not to run. For
example, a compression filter may be configured with the compression type set
to `none`.

See: [Filters][filters]

## The plugin manager

The plugin manager is a registry that stores each installed plugin and
configures the plugin as needed. Plugins are added to the manager with an ID,
which is used for two things:

- Specifying the configuration of the plugin.
- Specifying the source and destination for a backup or restore.

The consuming application accesses the plugin manager only to add plugins. It
may add plugins before passing the plugin manager to the `BackupMigrate`
object, or by calling `plugins()` on the `BackupMigrate` object:

    $backup_migrate->plugins()->add('demoplugin', new MyPlugin());

To configure this plugin, the consuming application would have a section called
`demoplugin` in the plugin manager configuration object:

    $conf = new Config([
      'demoplugin' => [
        'foo' => 'bar',
      ],
    ]);

    $plugins = new PluginManager(NULL, $conf);
    $backup_migrate = new BackupMigrate($plugins);

### Calling plugins

Internally, the plugin manager runs plugins for a given operation with the
`call()` method:

    $file = $this->plugins()->call('afterBackup', $file);

The `call()` method takes three parameters:

- **Operation**: the name of the operation to call.
- **Operand**: the object being operated on, if any.
- **Params**: an associative array of additional parameters.

Each plugin that implements the operation is called in order. The operand is
passed to the plugin and is overwritten by the return value from the plugin. In
this way, plugin operations are chained. A plugin is responsible for returning
the operand that was passed in if it does not wish to overwrite it. The params
array can contain additional information needed to run the operation, but it
cannot be modified by plugins.

### Implementing operations

If a plugin should be called for a given operation, it must define a method
with the same name as the operation. For example, to compress a backup file
after it has been created, the plugin must have an `afterBackup()` method that
takes a file as the operand and returns a new compressed file.

### Operation weights

The order in which plugins are called cannot be guaranteed. However, if a
plugin needs to run in a specific order, it may specify a weight for each
operation it implements. To specify a weight, it must implement an `opWeight()`
method, which takes an operation name and returns a numerical weight. Plugins
are called from lowest to highest weight. Plugins that do not specify a weight
are considered to have a weight of `0`.

To specify the weight of many operations, it may be easier to extend the
`\Drupal\backup_migrate\Core\Plugin\PluginBase` class and override the
`supportedOps()` method, which returns an array of supported operations and
their weights:

    public function supportedOps() {
      return [
        'afterBackup' => ['weight' => 100],
        'beforeRestore' => ['weight' => -100],
      ];
    }

### Calling other plugins

Plugins can call other plugins using the plugin manager. For example, a source
plugin might expose a line-item filter operation that allows other plugins to
alter single values before they are added to the backup file. An encryption
plugin may delegate the work of encryption to sub-plugins for better code
organization and extensibility.

By default, plugins are not given access to the plugin manager. However, if a
plugin implements
`\Drupal\backup_migrate\Core\Plugin\PluginCallerInterface`, the plugin manager
will inject itself into the plugin when the plugin is prepared for use. The
`\Drupal\backup_migrate\Core\Plugin\PluginCallerTrait` can be used to
implement the interface requirements. Plugins with this interface and trait can
use `$this->plugins()` to access the plugin manager:

    class MyPlugin implements PluginCallerInterface {
      use PluginCallerTrait;

      public function someOperation() {
        $this->plugins()->call('someOperation');
      }

    }

### Accessing services

If a plugin requires a cache, logger, state storage, mailer, or another backing
service, that service must be injected by the plugin manager. To make a service
available to the plugin manager, add it to an object that implements
`ServiceManagerInterface`.

That service locator may be passed to the plugin manager through the
constructor, or it can be passed later with `setServiceManager()`.

Any service provided by the service locator is injected into a plugin when the
plugin is added to the plugin manager if the service name matches a setter in
the plugin. For example, if a plugin has a `setLogger()` method and the service
locator has a service called `Logger`, then the logger service will be injected
via the `setLogger()` method:

    $services = new ServiceManager();
    $services->add('Logger', new FileLogger('/path/to/log.txt'));

    $plugins = new PluginManager($services);

    // If this plugin has setLogger(), the logger will be injected.
    $plugins->add('test', new TestPlugin());

See: [Services][services]

### Creating new temporary files

If a plugin needs to create a new temporary file, for example to decompress a
backup file, it may request that the temporary file manager be injected by
implementing `\Drupal\backup_migrate\Core\Plugin\FileProcessorInterface` and
using `\Drupal\backup_migrate\Core\Plugin\FileProcessorTrait`.

This allows the following:

    class MyFilePlugin implements FileProcessorInterface {
      use FileProcessorTrait;

      public function someOperation($file_in) {
        $file_out = $this->getTempFileManager()->popExt($file_in);

        // Return the new file so it overwrites the old file during plugin
        // chaining.
        return $file_out;
      }

    }

See: [Backup files][files]

## Sources and destinations

Sources and destinations are special-case plugins. While they are technically
identical to filter plugins, they are not called using the plugin manager's
`call()` method. Only one source and one destination can be used for each
backup or restore operation, so they are called individually rather than being
chained like most plugin operations.

These plugin types are different by convention only, and are injected and
configured in the same way as filters.

See:
[Sources][sources],
[Destinations][destinations]

[destinations]: https://github.com/backupmigrate/backup_migrate_core/tree/master/src/Destination
[files]: https://github.com/backupmigrate/backup_migrate_core/tree/master/src/File
[filters]: https://github.com/backupmigrate/backup_migrate_core/tree/master/src/Filter
[services]: https://github.com/backupmigrate/backup_migrate_core/tree/master/src/Service
[sources]: https://github.com/backupmigrate/backup_migrate_core/tree/master/src/Source

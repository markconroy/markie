# Configuration

Backup and Migrate core is configured by the consuming software when the
library is instantiated with a
`\Drupal\backup_migrate\Core\Config\ConfigInterface` object. This object is a
simple key-value store that contains configuration for each available plugin,
including sources, destinations, and filters.

Each plugin should have its own entry in the config object. That entry contains
the configuration array for the plugin. The entry key must be the same as the
key assigned to the plugin when it is added to the `BackupMigrate` object with
`->plugins()->add()`.

Any object that implements
`\Drupal\backup_migrate\Core\Config\ConfigInterface` may be used to configure
Backup and Migrate. For example, a consuming application may implement a class
that accesses the application's persistence layer directly to retrieve
configuration values. In many cases, however, the default
`\Drupal\backup_migrate\Core\Config\Config` class will suffice.

## The Config class

The built-in `\Drupal\backup_migrate\Core\Config\Config` class is a simple
implementation of the configuration interface. It can be instantiated with a
PHP associative array:

    use Drupal\backup_migrate\Core\Config\Config;
    use Drupal\backup_migrate\Core\Filter\CompressionFilter;
    use Drupal\backup_migrate\Core\Plugin\PluginManager;
    use Drupal\backup_migrate\Core\Source\MySQLiSource;

    $config = new Config([
      // Add configuration for the database source.
      'database1' => [
        'host' => '127.0.0.1',
        'database' => 'mydb',
        'user' => 'myuser',
        'password' => 'mypass',
        'port' => '8889',
      ],
      // Configure the compression filter.
      'compressor' => [
        'compression' => 'gzip',
      ],
    ]);

    $plugins = new PluginManager();

    // Add the database source. This reads configuration from the matching
    // 'database1' key.
    $plugins->add('database1', new MySQLiSource());

    // Add the compression plugin. This reads configuration from the matching
    // 'compressor' key.
    $plugins->add('compressor', new CompressionFilter());

    $bam = new BackupMigrate($plugins);
    $bam->backup('database1', 'somedestination');

## Initial configuration and run-time configuration

A plugin may have two types of configuration: initial configuration, added when
the plugin is created, and run-time configuration, added later by the plugin
manager.

Initial configuration can be overridden by run-time configuration for a single
operation, but the initial configuration is not permanently overwritten. This
means plugins can be reconfigured after the plugin manager has been created
without changing their base configuration.

A database source plugin illustrates the difference. The database connection
information should not change per operation and should be treated as initial
configuration. The list of tables to exclude during a backup, or whether tables
should be locked during a restore, may change from run to run and should be
run-time configuration.

To specify initial configuration, pass it to the plugin constructor:

    // The database credentials are passed to the constructor and are permanent.
    $plugins->add('main_database', new MySQLiSource(new Config([
      'database' => 'example',
      'username' => 'example',
      'password' => 'secret',
    ])));

    // Setting this configuration will not overwrite the database credentials.
    $plugins->setConfig(new Config([
      'main_database' => [
        'exclude_tables' => [
          'cache_bootstrap',
          'cache_config',
        ],
      ],
    ]));

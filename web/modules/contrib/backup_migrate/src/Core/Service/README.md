# Services

If a plugin needs to access the greater environment to write logs or store
data, it should rely on service objects that may be injected into the plugin at
run time.

## Service manager

The `ServiceManagerInterface` defines a simple service container and dependency
injector. It stores a keyed list of services available to plugins. The built-in
`ServiceManager` class implements this interface in the most basic way.

A consuming application may implement a manager with a more sophisticated
dependency management and configuration solution, such as
[Pimple](http://pimple.sensiolabs.org/), [PHP-DI](http://php-di.org/), or
[Symfony's DependencyInjection component][symfony-dependency-injection].
The built-in locator takes a list of configured services and returns them when
requested. It can also inject services automatically as described below.

## Service injection

Automatic service injection is optional. A consuming application can
instantiate plugins and pass the necessary services directly to them. However,
the service manager provides a simple injection mechanism that can make dynamic
plugin creation much simpler.

Plugins can request that a service be injected by defining a setter called
`setServiceName`, where `ServiceName` is replaced with the name of the service.
Here is a pseudo-code example:

    class MyPlugin implements PluginInterface {

      // Logger service setter.
      public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
      }

    }

This plugin will have a logger injected if one is available:

    $bam = new BackupMigrate();

    // The key 'Logger' must match 'setLogger'.
    $bam->services()->add('Logger', new MyLogger());

    // The manager will inject the logger automatically.
    $bam->plugins()->add('myplugin', new MyPlugin());

[symfony-dependency-injection]: http://symfony.com/doc/current/components/dependency_injection/introduction.html

# Develop a new AI Provider

## What is an AI Provider?
An AI provider is a plugin file using [Drupals plugin API](https://www.drupal.org/docs/drupal-apis/plugin-api) that can be discovered by the AI Core modules AI Provider plugin service to be used by any third party module using the AI Core module.

This means that if you are missing an external service or you have your own hosted private AI models using a proprietary service, you will still be able to use them together with any third party module that creates features for the AI Core module system.

## How do I start?
Start by [creating a Drupal module](https://www.drupal.org/docs/develop/creating-modules), like any other Drupal module. You need at least an .info.yml file and services.yml file.

Say that we create for the service DropAI.

Then create the file `src/Plugin/AiProvider/DropAiProvider.php`. In that file make sure that you add the AiProvider attributes and extend the AiProviderClientBase, something like this:

```php
namespace Drupal\dropai\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;

/**
 * Plugin implementation of the 'dropai' provider.
 */
#[AiProvider(
  id: 'dropai',
  label: new TranslatableMarkup('DropAI'),
)]
class DropAiProvider extends AiProviderClientBase {

}
```

The important documentation for which methods you need to provider and what they do you can find in the [AiProviderInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/AiProviderInterface.php?ref_type=heads). The AiProviderClientBase already implements this and adds some helper functions.

But what is also important is that you have to decide what kind of operation types that the provider should be able to handle. That is based on what the service can handle.

In our fictive example, DropAI is a Text-To-Image provider - this means that we have to look through all the possible interfaces under [OperationType](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType?ref_type=heads) and find the interface that fits this bill. In our case its the [TextToImageInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.0.x/src/OperationType/TextToImage/TextToImageInterface.php?ref_type=heads). This means that we have to implement the methods there also and the structure of our file, now looks like this.

```php
namespace Drupal\dropai\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\OperationType\TextToImage\TextToImageInterface;

/**
 * Plugin implementation of the 'dropai' provider.
 */
#[AiProvider(
  id: 'dropai',
  label: new TranslatableMarkup('DropAI'),
)]
class DropAiProvider extends AiProviderClientBase implements
  TextToImageInterface {

}
```

When you are finished, you should be able to just flush the cache and it should show up.

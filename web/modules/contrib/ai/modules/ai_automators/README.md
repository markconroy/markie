# AI Automators
## What is the AI Automator module?
This module offers the possibility for any field to be generated via AI from
another field. It's setup so you can use prompt engineering or none-AI tools to
automate into your entities.

The module uses a plugin system so anyone can develop modules to extend which
kind of AI services to use.

### How to install the AI Automators module
1. Get the code like any other module.
2. Install the module.
3. Install a sub-module (this module does nothing by itself).

### How to use the AI Automators module
1. Go to any field config of a field type you installed a sub-module for.
2. Enable it by checking `Enable AI Automators`
3. A sub form will open up where you can choose the Automator Type to use
based on your sub-module.
4. Follow the instructions of the forms.
5. Create a content with the field and fill out the base field used for
generation.
6. Your field with the field config you choose should be autopopulated.

## Developer documentation

### Writing a plugin
TBD

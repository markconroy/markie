# Field Widget Actions Module

## What is the Field Widget Actions module?

The Field Widget Actions module provides an easy way to attach action buttons to form fields.

The module doesn't do anything by itself, but is a builder module that allows other modules to provide plugins that can be used to trigger processes on form
fields that fills out the field or gives suggestions on how to fill out the field.

This works with any field as long as the plugin is configured to work with that field type and widget.

## Dependencies

The Field Widget Actions module can be installed by itself, but it does require a plugin to be available to be actually useful.

It also requires the Field UI module to be installed if you want to configure the field widget actions in the UI - however, you can always run the
configured field widget actions without the Field UI module if they have been setup.

## Known plugins
You can click on the links in the menu to see how to configure the plugins for different field types. But the following plugins are known:

* [AI Automators](https://www.drupal.org/project/ai)
* [AI Content Suggestions](https://www.drupal.org/project/ai_content_suggestions)
* [ECA](https://ecaguide.org/plugins/eca/base/events/eca_base_eca_field_widget/)
* [AI Agents](https://www.drupal.org/project/ai_agents)
* [Custom field](https://www.drupal.org/project/custom_field)

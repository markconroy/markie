---
Title: Add to Calendar
---
This is an implementation of an 'Add to Calendar' widget.

You can call it in any template and swap the variables. If calling it in Drupal, you can put something like this before your `{% include %}` statement:

```
{# Set the Add to Calendar Variables #}
{% set atc_start_date = node.field_event_date.value %}
{% set atc_end_date = node.field_event_date.end_value %}
{% set atc_title = node.title.value %}
{% set atc_details = node.field_event_intro.value %}
{% set atc_location = node.field_event_location.value %}
```

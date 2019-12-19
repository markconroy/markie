## INTRODUCTION

### More Global Variables
Create global variables to be printed in any template like so `{{ variable }}`.
Module provides variables by the next template - `{{ global_variables.**variable_name** }}`, 
i.e. simple - `{{ global_variables.variable1 }}`, namespaced - `{{ global_variables.my_collection.var1 }}`, 
`{{ global_variables.my_collection.var2 }}`

Table of Contents.
1. Paths
    1. Current Path - `{{ global_variables.current_path }}`
    1. Current Path Alias - `{{ global_variables.current_path_alias }}`
    1. Base URL - `{{ global_variables.base_url }}`
1. Current Items
    1. Current Page Title `{{ global_variables.current_page_title }}`
    1. Current Langcode `{{ global_variables.current_langcode }}`
    1. Current Langname `{{ global_variables.current_langname }}`
1. Site Information Page Global variables
    1. Site Name - `{{ global_variables.site_name }}`
    1. Site Slogan - `{{ global_variables.site_slogan }}`
    1. Site Mail - `{{ global_variables.site_mail }}`
    1. Site Logo - `{{ global_variables.logo }}`
1. Social Sharing
    1. Twitter - `{{ global_variables.social_sharing.twitter }}`
    1. Facebook - `{{ global_variables.social_sharing.facebook }}`
    1. LinkedIn - `{{ global_variables.social_sharing.linkedin }}`
    1. Email - `{{ global_variables.social_sharing.email }}`
    1. WhatsApp - `{{ global_variables.social_sharing.whatsapp }}`

## REQUIREMENTS

Supported version of the Drupal core.

## INSTALLATION

Install as the usual module. Nothing specific.

## CONFIGURATION

Module does not require any kind of configuration.

Bean (Bean Entities Aren't Nodes)
==================================

The bean module was created to have the flexibility of
Block Nodes without adding to the node space.

Bean Types
----------

A Bean Type (or Block Type) is a bundle of beans (blocks).
Each Bean type is defined by a ctools plugin and are fieldable.
Currently Bean Types are only defined in hook_bean_plugins().

If you enable beans_admin_ui you can add/edit bean types at
admin/structure/block-types

Beans
-----

Beans can be added at block/add

Example Bean Type Plugins
-------------------------
https://github.com/opensourcery/os_slideshow
http://drupal.org/project/beanslide
http://treehouseagency.com/blog/neil-hastings/2011/09/21/building-custom...
http://drupal.org/sandbox/brantwynn/1369224
http://drupal.org/sandbox/brantwynn/1376658
https://gist.github.com/1460818

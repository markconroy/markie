                      __    __                            __                    
                     /\ \__/\ \                          /\ \      __           
      ___ ___     ___\ \ ,_\ \ \___      __   _ __   ____\ \ \___ /\_\  _____   
    /' __` __`\  / __`\ \ \/\ \  _ `\  /'__`\/\`'__\/',__\\ \  _ `\/\ \/\ '__`\ 
    /\ \/\ \/\ \/\ \L\ \ \ \_\ \ \ \ \/\  __/\ \ \//\__, `\\ \ \ \ \ \ \ \ \L\ \
    \ \_\ \_\ \_\ \____/\ \__\\ \_\ \_\ \____\\ \_\\/\____/ \ \_\ \_\ \_\ \ ,__/
     \/_/\/_/\/_/\/___/  \/__/ \/_/\/_/\/____/ \/_/ \/___/   \/_/\/_/\/_/\ \ \/ 
        Fixing everything that is wrong (tm)                              \ \_\ 
                                                                           \/_/

#the Mothership
The mothership is a HTML5 basetheme that drop Drupals obscure love for wrapping everything into 2 divs & slapping on 3 css classes everywhere its possible.   
This theme will NOT make your site look neat - but it will clean up the markup Drupal provides out of the box, provide settings to remove css classes in the markup.   
This should make you daily work as a frontend developer much easier, and remove a lot of the frustration in plowing through the ton of unused css & markup.

If you really like the markup & css options that Drupal Provides - This theme is probably not for you, and thats perfectly ok, this theme won't do anything for you.   
If you on the other hand cares about clean markup & css -  This could be a good solution.

# Basic Mothership plan:
The mothership dosnt want to be the theme for everything & everybody - This is a much a proof of concept & playground to push the limits & ways that we build Drupal Themes.


### Fixing the Divitis 
`<div><div><div><div><div><div><div><div>$foo….`   
I didn't ask for 3 div wrappers so don't add them Drupal

### Class war   
No reason to load in classes that isn't need.
`<div class="baseclass anotherbaseclass yetanotherclass andonemorejustbecausewemightneedit "` 
That makes it even worse to read the markup & gives us endless amount of css overwriting
settings are provided to remove those that are not needed.
Saves us from surprices later in the process. if a module changes its css

### .css file overload 
Be nice to the web (and the mobile's to) - don't load css files, that the theme don't need & will be overwriting anyways.  
Control which css files should be used. All the Way down from Drupal core.

### Hardcoded image files
Drupal7 still comes with harcoded image files (omgwtfbbq)  
`<img src="wtf-is-this.png">`   
Change images directly in the markup is cumbersome - its now be in css. (css/mothership.css)

### No visual fluff
Don't do anything visually- We don't wanna have to deal with more css that isn't needed (is comes with a design.css for some common defaults but can be turned off)

### HTML5 öwesome
Mothership is now (since 7.x-2.0) a HTL5 only theme

### Drupal8 pre ready ;)
As the Drupal8 platform is being developed & discussed 
The mothership will look for better implementations & will add them as we see fit.

### Quick & easy CSS resets. 
Don't bring you own ;)  3 of the standard resets are included: the Eric meyer reset.css, html5 Doctor's reset & the normalizer outta the box.

### Tons of Settings : /
So we don't end up breaking backwards compability with existing modules & functionality. - All the css fixes are quickly undone by changing the settings.
That means theres a lot of settings :(


###Plays well with others
Motherships role is not to make it Pretty to Look at but pretty to work with - should work 

### Fix everything thats wrong (™)   
this is a tool for theme development for those that really cares about the markup & not quick n "easy" solutions & at the same time digg through the drupalcore,clean up so it can be easy for new frontend developers to use drupal & less wtf why is that div doing there.    

##Whats in there:

* documentation   
This file* mothership   
the mothership base theme - where the cleanup happens* mothershipstark   
For  testing purpose - yup gonna move it out someday* NEWTHEME   
a Vanilla theme based on mothership* README.txt
* resources   
Graphic files for the screenshots & icons* tema    
am example theme thats pretty soon it gonna be moved to its own project.


##Mothership takes over the markup
The Mothership is a basetheme so you can build themes on top of it & inherit all the glorious cleanup that the mothership brings you.

themename.info   
**base theme = mothership **   

![image](5-info-file.png)   
_tema.info file_

##Create a "new theme"
install the mothership in you theme all folder  **sites/all/themes/mothership/**
alternative install it in the **sites/[sitename]/themes/mothership**

1. copy the NEWTHEME folder **mothership/NEWTHEME**
2. move it to the site folder **sites/[sitename]/themes/NEWTHEME**
4. Rename from NEWTHEME to what-ever-you-want--the-theme-to-be-called both folder & .info + the content
3. Do the Raji :)

The theme now uses the default settings that mothership uses. 

#Settings
The heart of the mothership is the markup & css cleaning.  
If every class & wrapper div was removed it would end up with a lot of Drupals basic functionality that now simply didn't work. 
You would have to do a lot of work every time you had to identify any element on the site (actually back tracking into the center of drupal & you would never  come back from) 
So to make it a bit easier to remove, or add, class's & markup that we might need all this is done in the settings.

These settings will be global in you drupal theme, but as always you can overwrite these with theme functions or .tpl files. 
Look in the theme suggestion for those (more about that in the theme development settings)

##Theme Development
![image](6-development.png)
**Poor themers helper**   
This is a little snippet where mothership puts the hook suggestions out as a comment in the markup. This only works for page.tpl,node.tpl,block.tpl, field.tpl

![image](9-poor-themers helper.png)   
_poor themes to the rescue_

**Rebuilding the theme**   
when you get tired of doing _drush cc all_ day  
Thank you zen theme for providing this cool little feature  

**.test class**   
It can be very practical during developerment to have a test class in the body tag for showing a grid.png etc, so you can quickly remove it again if so needed.   
body.test{ background:pink }



##External Libraries
![image](8-libraries.png)

Quick & easy loading of libraries that can be very helpfull

**modernizr2**    
This will load modernizr2 with all its glory from a CDN
This can be good for a development environment but you should off course build a custom modernizer.
[modernizr](http://modernizr.com)

**Selectivizr**
Loads the selectivizr project to help getting old versions of ie to understand how the world should look like.
Its loaded from a CDN so we don't have to carry it around.


##CSS Files 
A Drupal site usually have many css files (its probably only Drupal sites that breaks the 31 css file max in ie8 & lower) 
Usually a Module comes with a couple of files containing defaults & other goodies, its not always that the default css is something that the theme needs.   It can be a "little bit" annoying to use time & resources to overwrite each modules css, just to keep you theme clean. Not to mention the unpleasant surprise if a module comes with badly writing css.


###Reset options
Quick options for the lazy themer choose you favorite css reset:
![image](cssfile-reset.png)

###BAT Cleanup & CSS Files Removal
In Drupal seven the BAT naming scheme were introduced - which splits up the css files for a module into 3 diffent files:

1. modulename.**base**.css   
the bare minimum for the module to function 
2. modulename.**admin**.css   
the css needed for the Administration 
3. modulename.**theme**.css   
the css to make it look pretty

Unfortunately this was introduced very late in the Drupal7 development, so it was only the system module that really made it before the final release - oooh well the mothership takes care of business ;)
core css files are copied over & split up for quick cleanup.

In Drupal8 theres a huge effort to get this in as clean n mean as possible. for 

* [Drupal HTML5 landing page](http://drupal.org/html5)
* [mortendk's post about BAT](http://morten.dk/blog/bat-naming-scheme)

![image](cssfile-bat.png)

In the future more core modules will be split and added to the mothership

* book
* contextual

####Stylestripper
Not all modules do the BAT thing (yet!)    
To remove a file you can instead add the path to it in the stripper (inspired by the drupal6 module: style stripper)

![image](cssfile-stripping.png)

### Default
Removing all the styles can be a problem, so to make it easier  for us theres to files to look at for possible inclusion
![image](cssfile-default.png)

Default css have basic Drupal stuff in it

the mothership.css file is where all css changes that can be done to the markup will be kept

##Class War
The core principle in mothership is that if a thingie (class, markup, id whatever) isn't needed don't load it, get rid of it. We want markup that we can easy read + no reason to complicate the css by x number of classes that could complicate the styles.

### &lt;body class="…
![image](1-body.png)   
Simply click off the classes you don't wants in the body tag:   
![image](1-body-markup.png)

####Helper classes

* .part-$path    
This will add a class based on the path of the page so url: section/foo/bar would give you the class    
**.path-section-foo-bar{ …. }**    
* .partone-$path    
will only print out the first part of the url 
**.path-section{ …. }**    

####Extra Removal
You will never know what a module might wanna add to you body.   
remove classes manually by adding each class separated by commas.


### &lt;div class="region …
![image](2-region.png)
If you don't need the regions you can easily remove them from you theme.   
Remove the region class + off course remove unused defined classes in the region
![image](2-region-control.png)

### &lt;div id="block-[module]" class="block …
![image](3-block.png)   
Options for removing the .block class and change the id to a class   
![image](3-block-control.png)

### &lt;div id="node-[nid]" class="node …
![image](4-node.png)

![image](4-node-control.png)

###Fields
_**Insert markup screenshot from the fields**_
![image](fields.png)

###Forms
_**Insert markup screenshot from a form field**_
![image](forms.png)    
      

** Markup changes: **   

* Removed the inner div from the forms:   
`<form><div>…</div></form>` -> `<form><div>…`    
In HTML5 theres no need for an inner div it to validate (yeah)

###Menus
_**Insert markup screenshot from menu**_
![image](menus.png)
** Markup changes: **    
uses the `<nav> … </nav>` as wrappers instead of `<div class="….">`

###Misc
![image](misc.png)

**viewport**    
More info about the viewport:

* [html5 Rocs](http://www.html5rocks.com/en/mobile/mobifying.html#toc-meta-viewport)
* [Apple developer](http://developer.apple.com/library/safari/#documentation/AppleApplications/Reference/SafariWebContent/UsingtheViewport/UsingtheViewport.html)




#Requirements
Mothership don't have any hardcoded variable inside the tpl files.
It simply dosn't make any sense that everything in Drupal is a block, but the tabs, messages, search, rss feeds, titles, logo & menus are all hardcoded in the page.tpl

###Blockify
page.tpl.php in mothership has cleaned out all the hardcoded variables
To enable tabs, messages, search, rss feeds, titles, logo & menus (phew)   
[Download Blockify ](http://drupal.org/project/blockify)



#&lt;/&gt; markup changes

###Menus 
all menus are wrapped in a &lt;nav&gt;  instead of the &lt;div&gt;
including the awesome menu-block module

### Book
markup & css cleaned up (the same as in Drupal8)
* book-navigation.tpl.php

* aggregator-item.tpl.php* block.tpl.php* comment-wrapper.tpl.php* comment.tpl.php* eva-display-entity-view.tpl.php* field.tpl.php* html.tpl.php* menu-block-wrapper.tpl.php* node--nodeblock.tpl.php* node.tpl.php* page.tpl.php* region.tpl.php* taxonomy-term.tpl.php* views-view-list.tpl.php* views-view.tpl.php

###html.tpl

###page.tpl

###node.tpl

###comments.tpl






#Supported modules
###blocktheme

###menu-block
add `<nav> … </nav>`

###Views


### views eva

###Display Suite

### 

# Drupal Versions
###Drupal6
only very minimal work will be done here aka security bug fixes etc. 
if you would like to battle it out with d6 well be my guest please contact me :)
###Drupal7
All main work goes into Drupal7 as of today D7 have been out in almost a year.
###Drupal8
It all Depends on how much divitis & markup overload thats going into Drupalcore ;)
Right now theres a lot of work going in to make it a lean n mean markup machine. So hopefully i can kill of 80% of the mothership … well thats the hope anyway :)

Some of the things that are going into the theme layer in Drupal8 is gonna be back ported here asap 

##known Problems

###Mothership Dominates LESS module
Mothership doesn't make sweet love to the LESS module. They both do their magick with the $css and there can only be one actually mothership brutly overwrites whatever the less module wanna do 

This is because LESS module wanna compile the .less files into .css files as the $css is built & mothership tries to clean out all the crap *ahem*

A workaround is to begin to do the compiling locally with code kit (was less.app before) or man up and compile it with the terminal & ruby
… or join the grownups and begin to use SASS & compass - which also is compiled locally before added to you css 


## Bug Reports etc
Just to make it clear the Mothership is **NEVER** to blame!   
Its always Drupals fault (this haven't been confirmed 101% yet by the @drupalthruth though)   
… if it should happend that theres actually a bug somewhere, please use the mothership issueque for this. 
I won't answer emails about it only in the issueque

[Mothership issues](http://drupal.org/project/issues/mothership)


## Todo
This is a list of things that are on the table for the next couple of releases.
other things that are missing post it in the [issueque](http://drupal.org/project/issues/mothership)
* taxonomy classnames in <body> 
* Clearfix removal to the max
* split out TEMA into its own separate theme
* js file removing options as we do the css files
* Unified Pagers
* Panels support 
* Breadcrumb setting
* Feeds love
* css/style-[conntent-type].css file suggestion
* Better documentation



#History
The mothership project was startet based on sheer frustration of the huge amount of markup & css that drupal puts outta the box.









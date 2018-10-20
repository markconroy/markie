
============================
GeSHi Filter (Drupal Module)
============================


DESCRIPTION
-----------
The GeShi Filter is a Drupal module for syntax highlighting of pieces of
source code. It implements a filter that formats and highlights the syntax of
source code between for example <code>...</code>.


DEPENDENCY
----------
This module requires the third-party library GeShi 1.0.x (Generic Syntax
Highlighter, written by Nigel McNie) which can be found at
  http://qbnz.com/highlighter
See installation procedure below for more information.

The current version and development can be found at 
  https://github.com/GeSHi/geshi-1.0


INSTALLATION
------------

There 3 ways to install geshilter in Drupal, I recommend using composer, as it
is more simple to install, but the old way downloading the library and using
the module libraries is still available.

Full composer:
  1. On Drupal root, run this command:
     composer require drupal/geshifilter
   
     It will put the geshifilter module under /modules and the Geshi library
  	 under /vendor. 
	 
  2. Goto example.com/admin/modules (replace the example.com with the real name
     of your site) or you can click on "extend" in the admin toolbar and enable
  	 the geshi module.
   
  3. You can go to configuration now.
   
Download the module and install the library with composer
  1. Download the module from the project page 
     https://drupal.org/project/geshifilter and place it in the modules folder 
	   in drupal root.
   	 You can download it with drush if you want with:
	   drush dl geshifilter
	 
  2. On Drupal root run this command to instal the Geshi Library:
     composer require geshi/geshi
	 
  3. Goto example.com/admin/modules (replace the example.com with the real name
     of your site) or you can click on "extend" in the admin toolbar and enable
  	 the geshi module.
	  
  4. You can go to configuration now

Download everything(same as drupal 7)
  1. Download the module from the project page 
     https://drupal.org/project/geshifilter and place it in the modules folder 
     in drupal root.
   	 You can download it with drush if you want with:
  	 drush dl geshifilter
 
  2. Download the GeSHi library from
     https://github.com/GeSHi/geshi-1.0
	 
  3. Extract it. It will create a folder with some files and a directory with
     The name src. Copy the src directory to the folder libraries in your
  	 Drupal root. Rename the src folder to geshi. Just to make sure: you will
  	 have a file drupal root/libraries/geshi/geshi.php not
     drupal root/libraries/geshi/src/geshi.php.
	 
  4. We need too the libraries module, download it from 
     https://www.drupal.org/project/libraries and place it under you modules
  	 folder.
	
  5. Goto example.com/admin/modules (replace the example.com with the real name
     of your site) or you can click on "extend" in the admin toolbar and enable
  	 the geshi module and the libraries module(need both modules enabled in
  	 this case).
	 
  6. You can go to configuration now	


CONFIGURATION
-------------
1. The general GeSHi Filter settings can be found by navigating to:
  Configuration > Content authoring > Geshifilter 
  OR admin/config/content/formats/geshifilter

  If your library is detected, it should show something like below,
  GESHI LIBRARY VERSION 1.0.8.12 DETECTED

  If you use ckeditor that is default now in Drupal 8 i recomment that you
  change tow settings. In Generic syntax highlighting tags add the "pre" tag, as
  you can write inside a pre block without ckeditor changing every new line to
  <br> which will show in the output. Enable Decode entities because ckeditor
  will encode some chars and without this setting they will show in the
  encoded format in the page.
  
2. Now go to admin/config/content/formats (Configuration -> Content authoring ->
   Text formats and Editors). We have to enable the geshifilter in one or more
   text formats. So choose one text format and click on configure. Now just 
   enable geshifilter and save.


USAGE
-----
The basic usage (with the default settings) is:
  <code language="java">
  for (int i; i<10; ++i) {
    dothisdothat(i);
  }
  </code>
When language tags are enabled (like "<java>" for Java) you can also do
  <java>
  for (int i; i<10; ++i) {
    dothisdothat(i);
  }
  </java>
More options and tricks can be found in the filter tips of the text format at
www.example.com/?q=filter/tips .


AUTHORS
-------
Original module by:
  Vincent Filby <vfilby at gmail dot com>

Drupal.org hosted version for Drupal 4.7:
  Vincent Filby <vfilby at gmail dot com>
  Michael Hutchinson (http://compsoc.dur.ac.uk/~mjh/contact)
  Damien Pitard <dpdev00 at gmail dot com>

Port to Drupal 5:
  rötzi (http://drupal.org/user/73064)
  Stefaan Lippens (http://drupal.org/user/41478)
  
Port to Drupal 7 and 8
  Fernando Correa da Conceição (https://www.drupal.org/u/yukare)

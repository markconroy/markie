----------------------------------------------------------
   BAD BEHAVIOR DRUPAL MODULE
----------------------------------------------------------



----------------------------------------------------------
   CONTIBUTORS
----------------------------------------------------------

Original Drupal module by: 
- David Angier (http://drupal.org/user/27546)

Additional commits for module improvement by:
- Steven Wittens (http://drupal.org/user/10)
- William Roboly (http://drupal.org/user/48120)
- Sean Robertson (http://drupal.org/user/7074)
- Dave Reid (http://drupal.org/user/53892)
- Greg Piper (http://drupal.org/user/426296)
- Hans Fredrik Nordhaug (http://drupal.org/user/40521)
- Dale Smith (http://drupal.org/user/1654510)

Bad Behavior PHP Scripts by:
- Michael Hampton (http://bad-behavior.ioerror.us)


----------------------------------------------------------
   OVERVIEW
----------------------------------------------------------

Bad Behavior is a set of PHP scripts that prevents spambots from
accessing your website by analyzing their actual HTTP requests and
comparing them to profiles from known spambots. It goes far beyond
User-Agent and Referer, however.

The problem: Spammers run automated scripts which read everything on
your website and harvest email addresses. If you have a blog, forum
or wiki, they will attempt to post spam directly to your site. They
also put false referrers in your server log, attempting to get links
posted through your stats page.

As the operator of a website, these spambots can cause you several
problems. First, the spammers are wasting your bandwidth, which you
may well be paying for. Second, they are posting comments to any form
they can find, filling your website with unwanted (and unpaid!) ads
for their products. Last but not least, they harvest any email
addresses they can find and sell those to other spammers, who fill
your inbox with more unwanted ads.

Bad Behavior intends to target any malicious software directed at a
website, whether it be a spambot, ill-designed search engine bot, or
system cracker.


----------------------------------------------------------
   REQUIREMENTS
----------------------------------------------------------

- Drupal 7.x (single-site installation only supported at this time)
- BadBehavior 2.2.15

----------------------------------------------------------
   INSTALLATION WITH DRUSH
----------------------------------------------------------

With Drush, you can do the normal

   drush dl badbehavior
   drush en badbehavior

and it will even install the BadBehavior PHP script automatically.

----------------------------------------------------------
   INSTALLATION WITH FTP/MANUALLY
----------------------------------------------------------

1. Extract the tarball into the modules folder of your Drupal install.

2. Download the current release of the BadBehavior PHP scripts from
   http://downloads.wordpress.org/plugin/bad-behavior.2.2.15.zip
   and unzip it. Then move the resulting "bad-behavior" directory into
   your /[path/to/site]/sites/all/libraries/ directory.

   Here are the recommended steps to do this from the command line:

   cd /[path/to/site]/sites/all/libraries/
   wget http://downloads.wordpress.org/plugin/bad-behavior.2.2.15.zip
   mv bad-behavior bad-behavior.bak
   unzip bad-behavior.2.2.15.zip
   rm bad-behavior.2.2.15.zip
   rm -R bad-behavior.bak (after the new version is verified as working)

3. Enable the module as usual from the Admin > Modules page.

4. Information on whitelisting:

    The whitelist file would need to be created here:
    /[path/to/site]/sites/all/libraries/bad-behavior/whitelist.ini

    You can see an example file for whitelisting here:
    /[path/to/site]/sites/all/libraries/bad-behavior/whitelist-sample.ini


----------------------------------------------------------
   CONFIGURATION AND REPORTS
----------------------------------------------------------

1. Configure settings in Admin > Settings > Bad Behavior.

2. View the BadBehavior logs in Admin > Reports > Bad Behavior.
   (Click on the detail link next to any log item for full details)

3. View the current Bad Behavior installation status in Admin > Reports.


----------------------------------------------------------
   COMPATIBILITY NOTES
----------------------------------------------------------

1. Boost:
   When using this module with Boost module enabled, you must have
   a whitelist.ini file in the BB script directory. A blank one can be
   created using the following from the command line:
   touch /[path/to/site]/sites/all/libraries/bad-behavior/whitelist.ini
   If this file doesn't exist while using Boost module, Boost will write
   file-not-found errors in the server logs.

2. Reverse Proxies & Load Balancers:
   Bad Behavior script library, as of version 2.1.9, supports reverse
   proxies and load balancers via a set of configurable options. Once
   this support is enabled, BB2 will try to determine the actual IP
   address of the client by examining certain HTTP headers, instead of
   using the local host IP. This is usually the 'X-Forwarded-For' header,
   which is added to the incoming headers by the proxy sitting in front
   of your web server.

   If you enable Drupal's built-in 'reverse_proxy' option as described
   in your site's settings.php file, the Drupal Bad Behavior module will
   enable BB2's reverse proxy support by default. You can override this
   default behavior by visiting the Bad Behavior module's settings page,
   and unchecking the 'Enable reverse proxy support' option.

   DO NOT ENABLE REVERSE PROXY SUPPORT UNLESS YOU KNOW
   WHAT YOU ARE DOING or you may end up blocking your site visitors.

   For more information, see:
   http://bad-behavior.ioerror.us/support/configuration/
   http://drupal.org/node/425990


----------------------------------------------------------
   FREQUENTLY ASKED QUESTIONS
----------------------------------------------------------

See: http://bad-behavior.ioerror.us/support/faq/



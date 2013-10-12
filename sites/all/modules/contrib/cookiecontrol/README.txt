Cookie Control

Educate and obtain consent from site visitors in order to store cookies on their local browser.
From May 26th 2012 it will be an enforced legal requirement for sites within the EU to request permission to store most cookies.


Useful Functions

Cookie Control provides a number of functions that allow you to check if you can set cookies from the rest of your scripts. These are:

CookieControl.cookieLawApplies(): This function checks if the cookie Law applies to the user that is accessing the site based on their location.
CookieControl.consented(): This function checks that the user has consented to getting cookies.
CookieControl.maySendCookies(): This function is a combination of the first two checks. Furthermore, this function will be updated as the law changes to include all the checks that are needed in order to set a cookie and therefore it is the one that is recommended to be used when checking if cookies can be set.

Please ensure that the Cookie Control script has loaded before these functions are called.
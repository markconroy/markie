### CKEditor5 Highlight Js Module for Drupal 9

**Overview**
------------

The Highlight Js module for Drupal 9 enriches the content editing experience by
seamlessly integrating the powerful syntax highlighter, Highlight Js, into
CKEditor5. This module empowers content editors to incorporate stylishly
highlighted source code snippets into their Drupal content without the need for
elevated permissions.

### **Configuration**

To configure the CKEditor5 Highlight Js module, follow these steps:

1. Navigate to the desired filter format (ensure CKEditor5 is being used) here
   from here 'admin/config/content/formats'.
2. Drag the Highlight Js icon into the active toolbar.
3. Enable the checkbox labeled 'Highlight Js' filter.
4. Adjust language, theme, and copy-to-clipboard button settings by visiting
   '/admin/config/content/highlight-js'.
5. By default, some languages are pre-selected. Uncheck any unnecessary ones
   according to your requirements. This selection influences the options
   available in the CKEditor5 dialog window when inserting source code.
6. Configure permissions for the 'Highlight Js' settings by navigating to
   '/admin/people/permissions/module/highlight\_js' for different roles.

**Note**: Ensure that your filter format permits the use of <pre> and
<code> tags under allowed tags, especially if using a format other than Full
HTML. Configure the HTML filter (if "Limit Allowed Tags" is enabled) to include
the class attribute.

**Usage**
---------

Follow these steps to enable and use the Highlight Js text filter:

1. Create new content or edit an existing one, ensuring CKEditor 5 is enabled.
2. When the Highlight.js icon is clicked within the editor, a dialog box will
   appear. This dialog box includes options to select a programming language and
   a text area field labeled "Source Code" where you can input the code you wish
   to have syntax highlighted.

**Highlight Js Supported Languages**
------------------------------------

Highlight Js supports a vast array of languages and themes. You can choose from
240+ languages and more than 250 themes, which will be available in the 
CKEditor5 Highlight Js dialog. Simply check the ones you need to include.

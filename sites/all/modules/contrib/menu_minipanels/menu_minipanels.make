; Drush Make integration.
; This will automatically download the necessary library and place it in the
; sites/all/libraries directory.

api = 2
core = 7.x

; Download the qTip v1 library.
libraries[qtip][download][type] = "get"
libraries[qtip][download][url] = "http://craigsworks.com/projects/qtip/download/package/production/development/"
libraries[qtip][directory_name] = "qtip"
libraries[qtip][destination] = "libraries"

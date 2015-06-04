# resourcespace_get_resources
Resourcespace Plugin to get rersources by Collections including writing of metadata
Tested with:
Resourcespace versions 7.1 and 7.2 on Ubuntu 14.04 with Apache2, PHP Version 5.5.9-1ubuntu4.9, MySQL 5.5.43, ExifTool 9.46

What it is:

1. When the plugin is activated you will find a new line in the Team Center called: "Copy resources from one or more collections including metadata"

2. A click opens a new page where you see a checkboxes with all collections (exept "My Collection")

3. The choosed collections will then be copied from the filestore, rename the files to the original filenames, and enter all new informations edited in RS into the files.

4. All files will be stored in folders called like the Collections

5. Optionally you can select to put the Photoshop Copyright Flag into the files

6. Optionally you can select to write zip-files for each Collection (then the files and the folders will be deleted and just the zip is kept)

7. A logfile is written

8. All resources will be copied into the ../filestore/tmp folder
9. Separated function for easy altering or correct metadatas, e.g. put (c) --> © in copyright

How to install:

1. Simply download and put all files (including the folders) into the Resourcespace plugins folder

2. Activate the plugin in Team Center / System setup / Manage Plugins


What the Plugin doesn´t do:

1. The Plugin is not tested on Windows Server. Especially writing folder names might be a problem. (remember the folders will be called like the collection names)

2. There is no sanitising of Collection Names -> Folder Names 

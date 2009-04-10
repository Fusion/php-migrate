This is a sub-project that I extracted from the PHP Framework I called 'Lenses' (http://github.com/Fusion/lenses/tree)
This project lets you perform database migrations, based on the YAML syntax, in PHP.



LICENSE:
========

See LICENSE.txt



USING THIS PROJECT:
===================

The simplest path is to use the 'please' command-line shell script or, alternatively, access console.php through your web browser.
Of course, you may wish to integrate this code in your own project; simply include console.php and adapt to your liking.



SETUP:
======

You need the adodb abstraction library; download it (http://adodb.sourceforge.net/) and drop it in libs/adodb/
You also need spyc to parse YAML syntax; download the package (http://spyc.sourceforge.net/) and drop it in libs/spyc/
Configure your database information in config.php



THE MIGRATIONS/ DIRECTORY:
==========================

Contains all your migration files in YAML format.
Take a look at the example file that comes with this project.
Files are numbered to reflect version numbers.

Each file contains two main sections:
up > describes what happens when migrating to a more recent version
down > describes what happens when reverting to an older version

Each section may contain three sub-sections:
drop, create and execute

Note: a special table, called 'system' will keep track of migrations.



HELP!
=====

A support community for everything VoilaWeb-related (that's my company) including open-source projects is available at:
http://getsatisfaction.com/voilaweb
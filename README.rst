**********
Surrogator
**********

Simple open source Libravatar__ compatible avatar image server written in PHP.

__ http://wiki.libravatar.org/api/


=====
Setup
=====

1. Copy ``data/surrogator.config.php.dist`` to ``data/surrogator.config.php``
   (remove the ``.dist``)
2. Adjust the config file to your needs
3. Create a default image and put it into the raw folder, name it ``default.png``
4. Setup your web server and set the document root to the ``www/`` directory.
   Make sure you allow ``.htaccess`` file and have ``mod_rewrite`` activated.


=====
Usage
=====

1. Put images in ``raw/`` folder.
   Name has to be email address + image file extension, for example
   ``foo@example.org.png``.
   Surrogator supports ``.png`` and ``.jpg``.
2. Run ``php surrogator.php``.
   The small files get generated.
3. You will get more information with ``-v``
4. When you run ``surrogator.php`` again, it will only generate small files
   when the raw file is newer than the "square" file in the var folder.
   You can force the update with ``--force``.


=======
License
=======
Surrogator is licensed under the `AGPL v3`__ or later.

__ http://www.gnu.org/licenses/agpl.html


======
Author
======
Written by Christian Weiske, cweiske@cweiske.de

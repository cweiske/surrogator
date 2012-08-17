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
   Make sure you allow the ``.htaccess`` file and have ``mod_rewrite`` activated.
5. Add DNS entries for ``_avatars._tcp`` and ``_avatars-sec._tcp``.
   A bind config file excerpt would look like this::

    _avatars._tcp.example.org.     IN SRV 0 0 80  avatars.example.org
    _avatars-sec._tcp.example.org. IN SRV 0 0 443 avatars.example.org

   This makes the avatar server ``avatars.example.org`` responsible for
   the domain ``example.org``, on ports 80 (HTTP) and 443 (HTTPS).

It is possible to use an existing domain as avatar server.
Just copy ``avatar.php`` into its document root dir and copy the rewrite rule
from ``.htaccess`` into the domain's ``.htaccess`` file if one exists.
If not, copy the whole ``.htaccess`` file.
After that, you have to point the ``$cfgFile`` path at the beginning of
the ``avatar.php`` file to the correct location.


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

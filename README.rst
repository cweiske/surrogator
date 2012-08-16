**********
Surrogator
**********

Open source Libravatar__ compatible avatar image server written in PHP.

__ http://wiki.libravatar.org/api/



Steps
=====

1. Put images in ``raw/`` folder.
   Name has to be email address + image file extension, for example
   ``foo@example.org.png``.
2. Generate/update big square files.
3. Generate images for each size that is defined in ``$sizes``.

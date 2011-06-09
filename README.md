Teeny Nagios
========================

ABSTRACT
------------------------

Teeny web interface for Nagios with smartphone(iPhone, Android).

* https://github.com/hirose31/teeny-nagios

INSTALLATION
------------------------

* requires
  * Nagios 3.1, 3.2
  * PHP

<pre>
    $ vi index.php
      specify $STATUS_FILE and $COMMAND_FILE for your environment and check
      permission of $COMMAND_FILE to write by executive user of PHP.
    $ cp index.php nagios.png YOUR_WEB_ROOT/teeny/
</pre>

COPYRIGHT & LICENSE
------------------------

Copyright HIROSE Masaaki

Apache License, Version 2.0

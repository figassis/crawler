1. This crawler requires a LAMP installation.
2. In order to run the crawler, first create a MySQL database and add the credentials in functions.php
3. A database export file is included in the file crawler.sql and can be imported directly into MySQL.
4. Decide which node will be the master (preferably the first one in which the code is downloaded), and modify the variable $master in Crawler.php, to the IP of the node.
5. The crawler can be limited to a local network (in this case, the IP will be private), or the internet, in which case it will be public.
6. Run the crawler by executing the command:

php /path/to/Crawler.php

Notes:
There are other settings that can be configured, and all are located in the first 13 lines of the Crawler.php. Read the comments.

When the master node runs for the first time, it will clear the url frontier table and fill it with the urls in the seed.txt file.

After the master node runs for the first time, it will rename the seed.txt to seed.old so that when the crawl job stops, it won't pull the same urls again.
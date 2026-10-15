<?php
/*
 * PHP QR Code encoder
 *
 * Config file, feel free to modify
 */
     
    define('QR_CACHEABLE', false);
    define('QR_CACHE_DIR', false);
    define('QR_LOG_DIR', false);

    define('QR_FIND_BEST_MASK', false);
    define('QR_FIND_FROM_RANDOM', false);
    define('QR_DEFAULT_MASK', 2);
                                                  
    define('QR_PNG_MAXIMUM_SIZE',  1024);                                                       // maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images
                                                  
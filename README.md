xenforo-amqp
============

AMQP client library for XenForo to use servers like RabbitMQ from XenForo

Installation
------------

Library follows PSR4 so installation is a bit different from what we can see in all XenForo addons

So, to use this library you can directly include the script into your source or do the following:

 - Install composer into your system or into XenForo root: https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx
 - Run ```composer init``` in your XenForo root to create composer.json: https://getcomposer.org/doc/03-cli.md#init
 - Run ```composer require forumhouseteam/xenforo-amqp:dev-master --no-dev``` to install this package 
 (--no-dev skips packages added for syntax highlighting ony like Zend Framework): 
 https://getcomposer.org/doc/03-cli.md#require
 - Put a line ```require_once(__DIR__ . '/../vendor/autoload.php');``` into your library/config.php to add composer 
 autoloader into XenForo's autoloading chain
 - Now you can use all composer packages in your development process and easily manage them using composer executable
 
 
Configuration
------------

Put the following lines at the end of your ```library/config.php```:

```php
//============ AMQP connector settings ============
$config['amqp'] = array(
    'host' => '192.168.1.1.1',          // The host where your AMPQ-compatible server runs
    'port' => '5672',                   // Port, your server runs on
    'user' => 'user',                   // Authentication user name 
    'password' => 'password',           // Authentication password
    'queues' => array(                  // Queues configuration
        'auth_ban' => array(            // The name of the queue
            'queue_flags' => array(     // Queue flags.
                'durable' => true,      // 'durable' means the queue will survice server reboot
            ), 
        ),
    ),
);
```

Usage:
------------

 - Create manager instance:
 
```php
$manager = new \Forumhouse\XenForoAmqp\QueueManager();
```

 - Push a message to queue:
 
```php
$manager->pushMessage(
    'my_queue_name',                // The name of the queue. Must be in configuration file (see above)
    array('data' => 'test_data'),   // The data to send to the queue. Will be json_encode'd if array is provided
    array('delivery_mode' => 2)     // Message properties. 'delivery_mode' => 2 makes message persistent
);
```
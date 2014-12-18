<?php

namespace Forumhouse\XenForoAmqp;

use DateTime;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use XenForo_Application;
use Zend_Config;

/**
 * Manager to work with AMQP messaging
 */
class QueueManager
{
    /**
     * @var AMQPChannel Channel used in communication
     */
    protected $channel;

    /**
     * @var AMQPConnection Connection used in communication
     */
    protected $connection;

    /**
     * Establishes connection to AMQP server. Call to this method can be omitted for lazy connections to be performed.
     * In every push() call, connect() is called, if needed
     */
    public function connect()
    {
        $config = XenForo_Application::getConfig()->amqp;
        $this->connection = new AMQPConnection($config->host, $config->port, $config->user, $config->password);
        $this->channel = $this->connection->channel();
    }

    /**
     * Disconnects from AMPQ server. This is completely optional since disconnect will occur at the end of the script
     */
    public function disconnect()
    {
        $this->connection->close();
        $this->connection = null;
    }

    /**
     * Pushes message to AMQP queue
     *
     * @param string       $queueName         Queue name to push message to. Check config file for queue configuration
     * @param string|array $body              Message body. If array is provided, it will be json_encoded
     * @param array|null   $messageProperties Message properties. Ensure persistence is on - array('delivery_mode' => 2)
     */
    public function pushMessage($queueName, $body, $messageProperties = null)
    {
        if (null === $this->connection) {
            $this->connect();
        }

        $queueConfig = XenForo_Application::getConfig()->amqp->queues->$queueName;
        $queueName = $this->declareQueue($queueName, $queueConfig);

        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        $message = new AMQPMessage($body, $messageProperties);
        $this->channel->basic_publish($message, $queueConfig->exchange_name, $queueName);
    }

    /**
     * Pushes message into the specified queue after specified delay or at the specific date in future
     *
     * @param DateTime|int $delay             If DateTime value is provided, message is delayed until this date, if int
     *                                        value is provided, message is delayed for this number of seconds
     * @param string       $queueName         Queue name to push delayed message into
     * @param string|array $body              Body of the message
     * @param array|null   $messageProperties Message properties. Ensure persistence is on - array('delivery_mode' => 2)
     */
    public function pushDelayed($delay, $queueName, $body, $messageProperties = null)
    {
        if (null === $this->connection) {
            $this->connect();
        }

        if ($delay instanceof \DateTime) {
            $delay = $delay->getTimestamp() - time();
        }

        $queueConfig = XenForo_Application::getConfig()->amqp->queues->$queueName;
        $queueName = $this->declareDelayedQueue($queueName, $queueConfig, $delay);

        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        $message = new AMQPMessage($body, $messageProperties);
        $this->channel->basic_publish($message, $queueConfig->exchange_name, $queueName);
    }

    /**
     * Declares a queue with a given name
     *
     * @param string      $queueName
     * @param Zend_Config $queueConfig
     *
     * @return string Queue name (returned as passed, just for symmetry with delayed queues functionality
     */
    protected function declareQueue($queueName, Zend_Config $queueConfig)
    {
        $autoDelete =
            $queueConfig->queue_flags->auto_delete === null ? true : (bool)$queueConfig->queue_flags->auto_delete;

        $this->channel->queue_declare(
            $queueName,
            (bool)$queueConfig->queue_flags->passive,
            (bool)$queueConfig->queue_flags->durable,
            (bool)$queueConfig->queue_flags->exclusive,
            $autoDelete,
            (bool)$queueConfig->queue_flags->nowait,
            $queueConfig->queue_flags->arguments,
            $queueConfig->queue_flags->ticket
        );

        return $queueName;
    }

    /**
     * Declares delayed queue to the AMQP library
     *
     * @param string      $destinationQueueName Queue destination
     * @param Zend_Config $queueConfig          Queue configuration
     * @param int         $delay                Queue delay in seconds
     *
     * @return string Deferred queue name for the specified delay and specified target queue
     */
    protected function declareDelayedQueue($destinationQueueName, Zend_Config $queueConfig, $delay)
    {
        $autoDelete =
            $queueConfig->queue_flags->auto_delete === null ? true : (bool)$queueConfig->queue_flags->auto_delete;

        $deferredQueueName = $destinationQueueName . '_deferred_' . $delay;
        $flags = array_replace(array(
            'queue' => '',
            'passive' => false,
            'durable' => false,
            'exclusive' => false,
            'auto_delete' => true,
            'nowait' => false,
            'arguments' => null,
            'ticket' => null,
        ), $queueConfig->queue_flags->toArray(), array(
            'queue' => $deferredQueueName,
            'durable' => true,
            'arguments' => array(
                'x-dead-letter-exchange' => array('S', ''),
                'x-dead-letter-routing-key' => array('S', $destinationQueueName),
                'x-message-ttl' => array('I', $delay * 1000),
            ),
        ));
        call_user_func_array(array($this->channel, 'queue_declare'), $flags);
        return $deferredQueueName;
    }
}

<?php

use Phalcon\Di;
use Phalcon\Events\Event;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\DiInterface;

class DbListener  implements InjectionAwareInterface
{
    /**
     * @var DiInterface
     */
    protected $_di;

    public function setDi(DiInterface $di)
    {
        $this->_di = $di;
    }

    public function getDi()
    {
        return $this->_di;
    }

    public function __construct()
    {
        $this->_logger = new FileAdapter("../api/logs/db.log");
    }
    public function beforeQuery(Event $event, $connection)
    {
        if( $this->_di->get('config')->recordSqlLog ) {
            //$connection->getSqlVariables()
            //$mgr = $test->getModelsManager();
            //$mgr->setModelSource('Favorite', 'source2');

            $this->_logger->log(__FUNCTION__ . json_encode($connection->getSQLStatement(), JSON_UNESCAPED_UNICODE));
        }
    }
}

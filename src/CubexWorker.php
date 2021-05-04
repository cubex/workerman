<?php
namespace Cubex\Workerman;

use Cubex\Cubex;
use Packaged\Context\Context;
use Packaged\Routing\Handler\Handler;
use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

class CubexWorker extends Worker
{
  protected $_projectRoot;
  protected $_loader;
  /**
   * @var callable
   */
  protected $_handler;

  public function __construct($socketName = '', array $contextOption = [])
  {
    parent::__construct($socketName, $contextOption);
    $this->onMessage = [$this, 'onMessage'];
  }

  public static function create(
    $projectRoot, $loader, callable $handleGenerator, $socketName = '', array $contextOption = []
  )
  {
    $worker = new static($socketName, $contextOption);
    $worker->_projectRoot = $projectRoot;
    $worker->_handler = $handleGenerator;
    $worker->_loader = $loader;
    return $worker;
  }

  protected function _makeHandler(): ?Handler
  {
    $gen = $this->_handler;
    return $gen();
  }

  public function setCount(int $count)
  {
    $this->count = $count;
    return $this;
  }

  public function start()
  {
    Worker::runAll();
  }

  public function onMessage(ConnectionInterface $connection, Request $request)
  {
    $cReq = \Packaged\Http\Request::create(
      $request->uri(),
      $request->method(),
      $request->method() === 'POST' ? $request->post() : $request->get(),
      (array)$request->cookie(),
      $request->file(),
      [],
      $request->rawBody()
    );
    $cubex = new Cubex($this->_projectRoot, $this->_loader);
    $cubex->share(Context::class, $cubex->prepareContext(new Context($cReq)));
    $response = $cubex->handle($this->_makeHandler(), false);

    // Send data to client
    $connection->send($response->getContent());

    $cubex->shutdown();
  }

}

<?php
namespace Cubex\Workerman;

use Cubex\Context\Context as CubexContext;
use Cubex\Cubex;
use Packaged\Context\Context;
use Packaged\Routing\Handler\Handler;
use Symfony\Component\HttpFoundation\Response;
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
  protected $_contextClass = CubexContext::class;

  public function __construct($socketName = '', array $contextOption = [])
  {
    parent::__construct($socketName, $contextOption);
    $this->onMessage = [$this, 'onMessage'];
  }

  public static function create(
    $projectRoot, $loader, callable $handleGenerator, $socketName = '', array $contextOption = [], $contextClass = null
  )
  {
    $worker = new static($socketName, $contextOption);
    $worker->_projectRoot = $projectRoot;
    $worker->_handler = $handleGenerator;
    $worker->_loader = $loader;
    if($contextClass != null)
    {
      $worker->_contextClass = $contextClass;
    }
    return $worker;
  }

  protected function _makeHandler(Cubex $cubex): ?Handler
  {
    $gen = $this->_handler;
    return $gen($cubex);
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
    $cReq->headers->add($request->header());

    $cubex = new Cubex($this->_projectRoot, $this->_loader);
    $ctx = $cubex->prepareContext(new $this->_contextClass($cReq));
    $cubex->share(Context::class, $ctx);
    $response = $cubex->handle($this->_makeHandler($cubex), false);

    // Send data to client
    $connection->send($this->_generateResponse($response), true);

    $cubex->shutdown();
  }

  protected function _generateResponse(Response $response)
  {
    $msg = 'HTTP/' . $response->getProtocolVersion() . ' ' .
      $response->getStatusCode() . ' ' . Response::$statusTexts[$response->getStatusCode()] . "\r\n";

    $headers = $response->headers;
    $content = $response->getContent();

    if(!$headers->has('Transfer-Encoding') && !$headers->has('Content-Length'))
    {
      $msg .= 'Content-Length: ' . strlen($content) . "\r\n";
    }
    if(!$headers->has('Content-Type'))
    {
      $msg .= "Content-Type: text/html\r\n";
    }
    if(!$headers->has('Connection'))
    {
      $msg .= "Connection: keep-alive\r\n";
    }
    if(!$headers->has('Server'))
    {
      $msg .= "Server: workerman\r\n";
    }
    $msg .= "X-Cubex: Workerman\r\n";
    foreach($headers->all() as $name => $values)
    {
      $msg .= "$name: " . implode(', ', $values) . "\r\n";
    }

    $msg = "$msg\r\n";

    $msg .= $content;

    return $msg;
  }

}

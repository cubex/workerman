#Upgrading an existing cubex project

Within your public/index.php you will need to upgrade

    $cubex = new Cubex(dirname(__DIR__), $loader);
    $cubex->handle(new Application());

to

    use Cubex\Workerman\CubexWorker;

    $worker = CubexWorker::create(
      dirname(__DIR__),
      $loader,
      function () { return new Application(); },
      'http://0.0.0.0:3000'
    )->setCount(4);

this will run workerman on port 3000 with 4 processes

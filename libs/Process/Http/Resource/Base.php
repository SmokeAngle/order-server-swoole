<?php
namespace OrderServer\Libs\Process\Http\Resource;

class Base {
    
    /**
     * @var \swoole_http_request
     */
    public $request = NULL;
    /**
     * @var string
     */
    public $gameSymbol = NULL;
    /**
     * @var int
     */
    public $gameId = NULL;
    /**
     * @var \swoole_http_server 
     */
    public $server = NULL;
    
    public $appConfig = NULL;

    public function __construct(\Swoole\Http\Server $server,  \swoole_http_request $request, $gameSymbol, \Noodlehaus\Config $appConfig ) {
        $this->request = $request;
        $this->gameSymbol = $gameSymbol;
        $this->server = $server;
        $this->appConfig = $appConfig;
        $commonConfig = $this->appConfig->get('common');
        $this->gameId = $commonConfig[sprintf('%s.game_id', $this->gameSymbol)];
    }
}

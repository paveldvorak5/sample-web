<?php
declare( strict_types=1 );

use Gps\Api\Route\ApiRouteManager;
use Gps\Application\Request\ErrorRequest;
use ReadyPhp\Api\ApiApplication;
use ReadyPhp\Api\Request\ApiRequest;
use ReadyPhp\Application\ApplicationException;
use ReadyPhp\Config\Config;
use ReadyPhp\Database\Connection\ConnectionException;
use ReadyPhp\Logger\DebugLog;
use ReadyPhp\Logger\Request\LogPreparation;

if( is_file( __DIR__ . '/../vendor/autoload.php' ) === true ) {
	/** @noinspection PhpIncludeInspection */
	require __DIR__ . '/../vendor/autoload.php';
} else {
	require __DIR__ . '/../../vendor/autoload.php';
}

try {
	$httpData = new \ReadyPhp\Application\Request\HttpData();
	$httpData->init();

	try {
		$frontendLog = LogPreparation::createFrontEndLogPreparation();
		$request = ApiRouteManager::getInstance()->getRoute( \ReadyPhp\Application\Request\HttpData::path() );

	} catch( ApplicationException $e ) {// todo apiexcetpion, poslat api error response
		$request = new ErrorRequest();
		$frontendLog = LogPreparation::createFrontEndLogPreparation();
		$frontendLog->setError( $e );
	}

	$application = ApiApplication::getInstance();

	ApiRequest::setCurrentRequest( $request );
	$response = $application->handle( $request );
	$response->process();
	$response->send();
	$application->terminate( $response );

} catch( Throwable $throwable ) {
	\ReadyPhp\Logger\Log\ProjectLog::getInstance( 'api' )->addThrowable( $throwable );
	if( $throwable instanceof ConnectionException ) {
		if( Config::isModeDevelop() ) {
			echo PHP_EOL . $throwable->getMessage() . PHP_EOL . PHP_EOL;
			DebugLog::printThrowableBacktrace( $throwable );
		}
		exit( 1 );
	}
	\ReadyPhp\Logger\Request\LogStorage::getLogPreparation()->closeAndSend();
}
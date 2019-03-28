<?php
declare( strict_types=1 );

use Gps\Application\Application;
use Gps\Application\Request\ErrorRequest;
use ReadyPhp\Application\ApplicationException;
use ReadyPhp\Application\Request\Request;
use ReadyPhp\Config\Config;
use ReadyPhp\Database\Connection\ConnectionException;
use ReadyPhp\Lang\International;
use ReadyPhp\Lang\LangRepository;
use ReadyPhp\Logger\DebugLog;
use ReadyPhp\Logger\Request\LogPreparation;

if( is_file( __DIR__ . '/../vendor/autoload.php' ) === true ) {
	/** @noinspection PhpIncludeInspection */
	require __DIR__ . '/../vendor/autoload.php';
} else {
	require __DIR__ . '/../../vendor/autoload.php';
}

/** @noinspection BadExceptionsProcessingInspection */
try {
	ReadyPhp\Database\Connection\ConnectionMySQL::getInstance( 'gps' )->query( \ReadyPhp\Database\GeneralQuery::getInstanceUse()->setTable( \ReadyPhp\Database\QueryComponent\QueryField::getInstanceDatabase( 'gps' ) ) ); // todo chyba AccessChecker nema v sql dotazech definovanou db

	//\ReadyPhp\Html\Header\Cookie\CookieJar::createInstance( new \ReadyPhp\Support\Encryption\Encryption( \Dho\Config::getInstance()->getCookieKey() ) ); todo
	$httpData = new \ReadyPhp\Application\Request\HttpData();
	$httpData->init();

	\ReadyPhp\Account\Session\SessionManager::start();

	$user = \ReadyPhp\Account\UserRepository::getCurrentUser();
	if( $user->isGuest() ) {
		\ReadyPhp\Lang\LangRepository::setCurrentLang( \ReadyPhp\Lang\LangRepository::getDefaultLang() );
		International::setLocale( LangRepository::getCurrentLang()->getLocale() ); // todo z bworseru Locale::acceptFromHttp
	} else {
		\ReadyPhp\Lang\LangRepository::setCurrentLang( $user->getLang() );
		// todo nastavit jiny locale pokud zvoleny
	}

	\ReadyPhp\Account\Access\AccessChecker::getInstance()->setCurrentUserAccessData();

	try {
		$request = \Gps\Application\Route\RouteManager::getInstance()->getRoute( \ReadyPhp\Application\Request\HttpData::path() );
		$frontendLog = LogPreparation::createFrontEndLogPreparation();
	} catch( ApplicationException $e ) {
		$request = new ErrorRequest( $e );
		$frontendLog = LogPreparation::createFrontEndLogPreparation();
		$frontendLog->setError( $e );
	}

	$application = Application::getInstance();

	Request::setCurrentRequest( $request );
	$response = $application->handle( $request );
	$response->process();
	$response->send();
	$application->terminate( $response );

} catch( Throwable $throwable ) {
	\ReadyPhp\Logger\Log\ProjectLog::getInstance( '' )->addThrowable( $throwable );
	$logPreparation = \ReadyPhp\Logger\Request\LogStorage::getLogPreparation();
	$logPreparation->setError();
	$logPreparation->closeAndSend();

	if( $throwable instanceof ConnectionException ) {
		if( Config::isModeDevelop() ) {
			echo PHP_EOL . $throwable->getMessage() . PHP_EOL . PHP_EOL;
			DebugLog::printThrowableBacktrace( $throwable );
		}
		exit( 1 );
	}

	$frontendLog = LogPreparation::createFrontEndLogPreparation();
	$request = new ErrorRequest( $throwable );
	Request::setCurrentRequest( $request );
	$response = $application->handle( $request );
	$response->process();
	$response->send();
	$application->terminate( $response );
}
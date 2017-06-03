<?php

namespace SMW\MediaWiki\Jobs;

use Hooks;
use SMW\ApplicationFactory;
use SMW\DIWikiPage;
use SMW\DIProperty;
use SMW\EventHandler;
use Title;

/**
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class ChangePropagationJob extends JobBase {

	/**
	 * Size of chunks
	 */
	const CHUNK_SIZE = 500;

	/**
	 * @since 3.0
	 *
	 * @param Title $title
	 * @param array $params job parameters
	 */
	public function __construct( Title $title, $params = array() ) {
		parent::__construct( 'SMW\ChangePropagationJob', $title, $params );
	}

	/**
	 * @see Job::run
	 *
	 * @since 3.0
	 */
	public function run() {

		$applicationFactory = ApplicationFactory::getInstance();
		$store = $applicationFactory->getStore();

		$dataItems = array();
		$subject = DIWikiPage::newFromTitle( $this->getTitle() );

		$applicationFactory->getMediaWikiLogger()->info(
			'ChangePropagationJob on ' . $subject->getHash()
		);

		if ( $this->getTitle()->getNamespace() === SMW_NS_PROPERTY ) {

			$property = DIProperty::newFromUserLabel(
				$this->getTitle()->getText()
			);

			$dataItems = $store->getAllPropertySubjects( $property );

		$applicationFactory->getMediaWikiLogger()->info(
			'$dataItems ' . serialize( $dataItems )
		);

			$this->chunkAndDispatch( $dataItems );

			// Those with errors
			$dataItems = $store->getPropertySubjects(
				new DIProperty( DIProperty::TYPE_ERROR ),
				$subject
			);

			// After all is done, refresh the property page once more
			$dataItems[] = $subject;

			$this->chunkAndDispatch( $dataItems );
		}

		// Release the property page and add the new data after
		// the dispatch has been scheduled
		$this->runUpdateJob( $subject );

		return true;
	}

	private function chunkAndDispatch( $dataItems ) {

		if ( $dataItems === array() || $dataItems === null ) {
			return;
		}

		foreach ( array_chunk( $dataItems, self::CHUNK_SIZE, true ) as $dataItemList ) {
			$this->addUpdateDispatcherJob( $dataItemList );
		}
	}

	private function addUpdateDispatcherJob( $dataItems ) {

		$jobList = array();

		foreach ( $dataItems as $dataItem ) {
			$jobList[] = $dataItem->asBase()->getSerialization();
		}

		$hash = md5( json_encode( $jobList ) );


		ApplicationFactory::getInstance()->getMediaWikiLogger()->info(
			'$jobList ' . serialize( $jobList )
		);

		$updateDispatcherJob = new UpdateDispatcherJob(
			Title::newFromText( 'ChangePropagationChunkedJobList:' . $hash ),
			array(
				UpdateDispatcherJob::JOB_LIST => $jobList
			)
		);

		$updateDispatcherJob->insert();
	}

	private function runUpdateJob( $subject ) {

		$updateJob = new UpdateJob(
			$this->getTitle(),
			array(
				UpdateJob::CHANGE_PROP => $subject->getSerialization()
			)
		);

		$updateJob->run();
	}

}

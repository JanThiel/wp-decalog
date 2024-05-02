<?php
/**
 * Sematext handler for Monolog
 *
 * Handles all features of Sematext handler for Monolog.
 *
 * @package Handlers
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace Decalog\Handler;

use Decalog\Formatter\SematextFormatter;
use DLMonolog\Formatter\FormatterInterface;
use DLMonolog\Handler\ElasticsearchHandler;
use DLMonolog\Logger;
use Elastic\Elasticsearch\Common\Exceptions\RuntimeException as ElasticsearchRuntimeException;

/**
 * Define the Monolog Sematext handler.
 *
 * Handles all features of Sematext handler for Monolog.
 *
 * @package Handlers
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class SematextHandler extends ElasticsearchHandler {

	/**
	 * @param string     $host      The logsene host (for location selection).
	 * @param string     $token     The logs app token.
	 * @param int|string $level     The minimum logging level at which this handler will be triggered.
	 * @param bool       $bubble    Whether the messages that are handled can bubble up the stack or not.
	 */
	public function __construct( string $host, string $token, $level = Logger::DEBUG, bool $bubble = true ) {
		$client  = \Elastic\Elasticsearch\ClientBuilder::create()->setHosts( [ 'https://' . $host . ':443' ] )->build();
		$options = [
			'index' => $token,
			'type'  => 'wordpress_decalog',
		];
		parent::__construct( $client, $options, $level, $bubble );
	}

	/**
	 * Use Elasticsearch bulk API to send list of documents
	 *
	 * @param  array             $records
	 * @throws \RuntimeException
	 */
	protected function bulkSend( array $records ): void {
		try {
			$params = [
				'body' => [],
			];

			foreach ( $records as $record ) {
				$params['body'][] = [
					'index' => [
						'_index' => $record['_index'],
						'_type'  => $record['_type'],
					],
				];
				unset( $record['_index'], $record['_type'] );

				$params['body'][] = $record;
			}

			$responses = $this->client->bulk( $params );

			if ( $responses['errors'] === true ) {
				throw $this->createExceptionFromResponses( $responses );
			}
		} catch ( \Throwable $e ) {
			if ( 'Waiting did not resolve future' !== $e->getMessage() && ! $this->options['ignore_error'] ) {
				throw new \RuntimeException( 'Error sending messages to Elasticsearch', 0, $e );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getDefaultFormatter(): FormatterInterface {
		return new SematextFormatter( $this->options['index'], $this->options['type'] );
	}
}

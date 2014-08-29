<?php
namespace Cassandra\Request;
use Cassandra\Protocol\Frame;

class Execute extends Request{
	/**
	 * EXECUTE
	 *
	 * Executes a prepared query. The body of the message must be:
	 * <id><n><value_1>....<value_n><consistency>
	 * where:
	 * - <id> is the prepared query ID. It's the [short bytes] returned as a
	 * response to a PREPARE message.
	 * - <n> is a [short] indicating the number of following values.
	 * - <value_1>...<value_n> are the [bytes] to use for bound variables in the
	 * prepared query.
	 * - <consistency> is the [consistency] level for the operation.
	 * Note that the consistency is ignored by some (prepared) queries (USE, CREATE,
	 * ALTER, TRUNCATE, ...).
	 * The response from the server will be a RESULT message.
	 *
	 * @param array $prepareData
	 * @param array $values
	 * @param int $consistency
	 */
	public function __construct(array $prepareData, array $values, $consistency, $serialConsistency) {
		$body = pack('n', strlen($prepareData['id'])) . $prepareData['id'];

		$body .= Query::queryParameters($consistency, $serialConsistency, $prepareData, $values);
		
		parent::__construct(Frame::OPCODE_EXECUTE, $body);
	}
	
}
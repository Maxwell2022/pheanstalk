<?php

namespace Pheanstalk\Command;
use Pheanstalk\IResponseParser;
use Pheanstalk\Exception;

/**
 * The 'put' command.
 * Inserts a job into the client's currently used tube.
 * @see Pheanstalk_Command_UseCommand
 *
 * @author Paul Annesley
 * @package Pheanstalk
 * @licence http://www.opensource.org/licenses/mit-license.php
 */
class PutCommand extends AbstractCommand implements IResponseParser
{
	private $_data;
	private $_priority;
	private $_delay;
	private $_ttr;

	/**
	 * Puts a job on the queue
	 * @param string $data The job data
	 * @param int $priority From 0 (most urgent) to 0xFFFFFFFF (least urgent)
	 * @param int $delay Seconds to wait before job becomes ready
	 * @param int $ttr Time To Run: seconds a job can be reserved for
	 */
	public function __construct($data, $priority, $delay, $ttr)
	{
		$this->_data = $data;
		$this->_priority = $priority;
		$this->_delay = $delay;
		$this->_ttr = $ttr;

        $json = json_encode($this->_data);
        $this->_data = false !== $json ? $json : $this->_data;
	}

	/* (non-phpdoc)
	 * @see \Pheanstalk\ICommand::getCommandLine()
	 */
	public function getCommandLine()
	{
		return sprintf(
			'put %d %d %d %d',
			$this->_priority,
			$this->_delay,
			$this->_ttr,
			$this->getDataLength()
		);
	}

	/* (non-phpdoc)
	 * @see \Pheanstalk\ICommand::hasData()
	 */
	public function hasData()
	{
		return true;
	}

	/* (non-phpdoc)
	 * @see \Pheanstalk\ICommand::getData()
	 */
	public function getData()
	{
		return $this->_data;
	}

	/* (non-phpdoc)
	 * @see \Pheanstalk\ICommand::getDataLength()
	 */
	public function getDataLength()
	{
		return mb_strlen($this->_data, "latin1");
	}

	/* (non-phpdoc)
	 * @see \Pheanstalk\IResponseParser::parseRespose()
	 */
	public function parseResponse($responseLine, $responseData)
	{
		if (preg_match('#^INSERTED (\d+)$#', $responseLine, $matches))
		{
			return $this->_createResponse('INSERTED', array(
				'id' => (int)$matches[1]
			));
		}
		elseif (preg_match('#^BURIED (\d)+$#', $responseLine, $matches))
		{
			throw new Exception(sprintf(
				'%s: server ran out of memory trying to grow the priority queue data structure.',
				$responseLine
			));
		}
		elseif (preg_match('#^JOB_TOO_BIG$#', $responseLine))
		{
			throw new Exception(sprintf(
				'%s: job data exceeds server-enforced limit',
				$responseLine
			));
		}
		elseif (preg_match('#^EXPECTED_CRLF#', $responseLine))
		{
			throw new Exception(sprintf(
				'%s: CRLF expected',
				$responseLine
			));
		}
		else
		{
			throw new Exception(sprintf(
				'Unhandled response: %s',
				$responseLine
			));
		}
	}
}

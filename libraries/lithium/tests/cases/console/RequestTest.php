<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2009, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\console;

use \lithium\console\Request;

class RequestTest extends \lithium\test\Unit {

	public $streams;

	protected $_backups = array();

	public function setUp() {
		$this->streams = array(
			'input' => LITHIUM_APP_PATH . '/tmp/input.txt',
		);

		$this->_backups['cwd'] = getcwd();
		$this->_backups['_SERVER'] = $_SERVER;
		$_SERVER['argv'] = array();
	}

	public function tearDown() {
		foreach ($this->streams as $path) {
			if (file_exists($path)) {
				unlink($path);
			}
		}
		$_SERVER = $this->_backups['_SERVER'];
		chdir($this->_backups['cwd']);
	}

	public function testConstructWithoutConfig() {
		$request = new Request();

		$expected = array();
		$result = $request->args;
		$this->assertEqual($expected, $result);

		$result = $request->env();
		$this->assertTrue(!empty($result));

		$expected = getcwd();
		$result = $result['working'];
		$this->assertEqual($expected, $result);
	}

	public function testEnvWorking() {
		chdir(LITHIUM_APP_PATH . '/tmp');
		$request = new Request();

		$expected = LITHIUM_APP_PATH . '/tmp';
		$result = $request->env('working');
		$this->assertEqual($expected, $result);
	}

	public function testConstructWithServer() {
		$_SERVER['argv'] = array('/path/to/lithium.php','one', 'two');
		$request = new Request();

		$expected = '/path/to/lithium.php';
		$result = $request->env('script');
		$this->assertEqual($expected, $result);

		$expected = array('one', 'two');
		$result = $request->args;
		$this->assertEqual($expected, $result);
	}

	public function testConstructWithConfigArgv() {
		$request = new Request(array(
			'args' => array('/path/to/lithium.php', 'wrong')
		));

		$expected = array('/path/to/lithium.php', 'wrong');
		$result = $request->args;
		$this->assertEqual($expected, $result);

		$_SERVER['argv'] = array('/path/to/lithium.php');
		$request = new Request(array(
			'args' => array('one', 'two')
		));

		$expected = '/path/to/lithium.php';
		$result = $request->env('script');
		$this->assertEqual($expected, $result);

		$expected = array('one', 'two');
		$result = $request->args;
		$this->assertEqual($expected, $result);
	}

	public function testConstructWithConfigArgs() {
		$request = new Request(array(
			'args' => array('ok')
		));
		$expected = array('ok');
		$this->assertEqual($expected, $request->args);

		$request = new Request(array(
			'env' => array('script' => '/path/to/lithium.php'),
			'args' => array('one', 'two', 'three', 'four')
		));

		$expected = '/path/to/lithium.php';
		$result = $request->env('script');
		$this->assertEqual($expected, $result);

		$expected = array('one', 'two', 'three', 'four');
		$this->assertEqual($expected, $request->args);
	}

	public function testConstructWithEnv() {
		chdir(LITHIUM_APP_PATH . '/tmp');
		$request = new Request(array(
			'env' => array('working' => '/some/other/path')
		));

		$expected = '/some/other/path';
		$result = $request->env('working');
		$this->assertEqual($expected, $result);
	}

	public function testInput() {
		$stream = fopen($this->streams['input'], 'w+');
		$request = new Request(array(
			'input' => $stream
		));
		$this->assertTrue(is_resource($request->input));
		$this->assertEqual($stream, $request->input);


		$expected = 2;
		$result = fwrite($request->input, 'ok');
		$this->assertEqual($expected, $result);
		rewind($request->input);

		$expected = 'ok';
		$result = $request->input();
		$this->assertEqual($expected, $result);
	}
}
?>
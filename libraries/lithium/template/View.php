<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\template;

use lithium\core\Libraries;
use lithium\template\TemplateException;

/**
 * As one of the three pillars of the Model-View-Controller design pattern, the `View` class
 * (along with other supporting classes) is responsible for taking the data passed from the
 * request and/or controller, inserting this into the requested template/layout, and then returning
 * the rendered content.
 *
 * The `View` class interacts with a variety of other classes in order to achieve maximum
 * flexibility and configurability at all points in the view rendering and presentation
 * process. The `Loader` class is tasked with locating and reading template files which are then
 * passed to the `Renderer` adapter subclass.
 *
 * The `View` class operates on _processes_, which define the steps to render a completed view. For
 * example, the default process, which renders a template wrapped in a layout, is comprised of two
 * _steps_: the first step renders the main template and captures it to the rendering context, where
 * it is embedded in the layout in the second step. See the `$_steps` and `$_processes` properties
 * for more information.
 *
 * Using steps and processes, you can create rendering scenarios to suit very complex needs.
 *
 * By default, the `View` class is called during the course of the framework's dispatch cycle by the
 * `Media` class. However, it is also possible to instantiate and call `View` directly, in cases
 * where you wish to bypass all other parts of the framework and simply return rendered content.
 *
 * A simple example, using the `Simple` renderer/loader for string templates:
 *
 * {{{
 * $view = new View(array('loader' => 'Simple', 'renderer' => 'Simple'));
 * echo $view->render('element', array('name' => "Robert"), array('element' => 'Hello, {:name}!'));
 *
 * // Output:
 * "Hello, Robert!";
 * }}}
 *
 *  _Note_: This is easily adapted for XML templating.
 *
 * Another example, this time of something that could be used in an appliation
 * error handler:
 *
 * {{{
 * $view = new View(array(
 *     'paths' => array(
 *         'template' => '{:library}/views/errors/{:template}.{:type}.php',
 *         'layout'   => '{:library}/views/layouts/{:layout}.{:type}.php',
 *     )
 * ));
 *
 * echo $View->render('all', array('content' => $info), array(
 *     'template' => '404',
 *     'layout' => 'error'
 * ));
 * }}}
 *
 * @see lithium\template\view\Renderer
 * @see lithium\template\view\adapter
 * @see lithium\net\http\Media
 */
class View extends \lithium\core\Object {

	/**
	 * Output filters for view rendering.
	 *
	 * @var array List of filters.
	 */
	public $outputFilters = array();

	/**
	 * Holds the details of the current request that originated the call to this view, if
	 * applicable.  May be empty if this does not apply.  For example, if the View class is
	 * created to render an email.
	 *
	 * @see lithium\action\Request
	 * @var object `Request` object instance.
	 */
	protected $_request = null;

	/**
	 * Holds a reference to the `Response` object that will be returned at the end of the current
	 * dispatch cycle. Allows headers and other response attributes to be assigned in the templating
	 * layer.
	 *
	 * @see lithium\action\Response
	 * @var object `Response` object instance.
	 */
	protected $_response = null;

	/**
	 * The object responsible for loading template files.
	 *
	 * @var object Loader object.
	 */
	protected $_loader = null;

	/**
	 * Object responsible for rendering output.
	 *
	 * @var objet Renderer object.
	 */
	protected $_renderer = null;

	/**
	 * View processes are aggregated lists of steps taken to to create a complete, rendered view.
	 * For example, the default process, `'all'`, renders a template, then renders a layout, using
	 * the rendered template content. A process can be defined using one or more steps defined in
	 * the `$_steps` property.
	 *
	 * @see lithium\template\View::$_steps
	 * @see lithium\template\View::render()
	 * @var array
	 */
	protected $_processes = array(
		'all' => array('template', 'layout'),
		'template' => array('template'),
		'element' => array('element')
	);

	/**
	 * The list of available rendering steps. Each step contains instructions for how to render one
	 * piece of a multi-step view rendering. The `View` class combines multiple steps into
	 * _processes_ to create the final output.
	 *
	 * @var array
	 */
	protected $_steps = array(
		'template' => array('path' => 'template', 'capture' => array('context' => 'content')),
		'layout' => array(
			'path' => 'layout', 'conditions' => 'layout', 'multi' => true, 'capture' => array(
				'context' => 'content'
			)
		),
		'element' => array('path' => 'element')
	);

	/**
	 * Auto-configuration parameters.
	 *
	 * @var array Objects to auto-configure.
	 */
	protected $_autoConfig = array(
		'request', 'response', 'processes' => 'merge', 'steps' => 'merge'
	);

	/**
	 * Constructor.
	 *
	 * @param array $config Configuration parameters.
	 *        The available options are:
	 *          - `'loader'` _mixed_: For locating/reading view, layout and element
	 *            templates. Defaults to `File`.
	 *          - `'renderer'` _mixed_: Populates the view/layout with the data set from the
	 *            controller. Defaults to `'File'`.
	 *          - `request`: The request object to be made available in the view. Defalts to `null`.
	 *          - `vars`: Defaults to `array()`.
	 * @return void
	 */
	public function __construct(array $config = array()) {
		$defaults = array(
			'request' => null,
			'response' => null,
			'vars' => array(),
			'loader' => 'File',
			'renderer' => 'File',
			'steps' => array(),
			'processes' => array(),
			'outputFilters' => array()
		);
		parent::__construct($config + $defaults);
	}

	/**
	 * Perform initialization of the View.
	 *
	 * @return void
	 */
	protected function _init() {
		parent::_init();

		foreach (array('loader', 'renderer') as $key) {
			if (is_object($this->_config[$key])) {
				$this->{'_' . $key} = $this->_config[$key];
				continue;
			}
			$class = $this->_config[$key];
			$config = array('view' => $this) + $this->_config;
			$this->{'_' . $key} = Libraries::instance('adapter.template.view', $class, $config);
		}
		$encoding = 'UTF-8';

		if ($this->_response) {
			$encoding =& $this->_response->encoding;
		}

		$h = function($data) use (&$encoding) {
			return htmlspecialchars((string) $data, ENT_QUOTES, $encoding);
		};
		$this->outputFilters += compact('h') + $this->_config['outputFilters'];
	}

	public function render($process, array $data = array(), array $options = array()) {
		$defaults = array(
			'type' => 'html',
			'layout' => null,
			'template' => null,
			'context' => array(),
		);
		$options += $defaults;

		$data += isset($options['data']) ? (array) $options['data'] : array();
		$paths = isset($options['paths']) ? (array) $options['paths'] : array();
		unset($options['data'], $options['paths']);
		$params = array_filter($options, function($val) { return $val && is_string($val); });
		$result = null;

		foreach ($this->_process($process, $params) as $name => $step) {
			if (!$this->_conditions($step, $params, $data, $options)) {
				continue;
			}
			if (isset($step['multi']) && $step['multi'] && isset($options[$name])) {
				foreach ((array) $options[$name] as $value) {
					$params[$name] = $value;
					$result = $this->_step($step, $params, $data, $options);
				}
				continue;
			}
			$result = $this->_step((array) $step, $params, $data, $options);
		}
		return $result;
	}

	protected function _conditions($step, $params, $data, $options) {
		if (!isset($step['conditions']) || !$conditions = $step['conditions']) {
			return true;
		}
		if (is_callable($conditions) && !$conditions($params, $data, $options)) {
			return false;
		}
		if (is_string($conditions) && !(isset($options[$conditions]) && $options[$conditions])) {
			return false;
		}
		return true;
	}

	protected function _step(array $step, array $params, array &$data, array &$options = array()) {
		$step += array('path' => null, 'capture' => null);
		$_renderer = $this->_renderer;
		$_loader = $this->_loader;
		$filters = $this->outputFilters;
		$params = compact('step', 'params', 'options') + array('data' => $data + $filters);

		$filter = function($self, $params) use (&$_renderer, &$_loader) {
			$template = $_loader->template($params['step']['path'], $params['params']);
			return $_renderer->render($template, $params['data'], $params['options']);
		};
		$result = $this->_filter(__METHOD__, $params, $filter);

		if (is_array($step['capture'])) {
			switch (key($step['capture'])) {
				case 'context':
					$options['context'][current($step['capture'])] = $result;
				break;
				case 'data':
					$data[current($step['capture'])] = $result;
				break;
			}
		}
		return $result;
	}

	/**
	 * Converts a process name to an array containing the rendering steps to be executed for each
	 * process.
	 *
	 * @param string $process A named set of rendering steps.
	 * @param array $params
	 * @return array A 2-dimensional array that defines the rendering process. The first dimension
	 *         is a numerically-indexed array containing each rendering step. The second dimension
	 *         represents the parameters for each step.
	 */
	protected function _process($process, &$params) {
		$defaults = array('conditions' => null, 'multi' => false);

		if (!is_array($process)) {
			if (!isset($this->_processes[$process])) {
				throw new TemplateException("Undefined rendering process '{$process}'.");
			}
			$process = $this->_processes[$process];
		}
		if (is_string(key($process))) {
			return $this->_convertSteps($process, $params) + $defaults;
		}
		$result = array();

		foreach ($process as $step) {
			if (is_array($step)) {
				$result[] = $step + $defaults;
				continue;
			}
			if (!isset($this->_steps[$step])) {
				throw new TemplateException("Undefined rendering step '{$step}'.");
			}
			$result[$step] = $this->_steps[$step] + $defaults;
		}
		return $result;
	}

	protected function _convertSteps($command, &$params) {
		if (count($command) == 1) {
			$params['template'] = current($command);
			return array(array('path' => key($command)));
		}
		return $command;
	}
}

?>
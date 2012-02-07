<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Simple extension Kohana_Exception. Allows us to use the PHP 'instanceof'
 * keyword to determine the type of exception that occurred.
 *
 * @package    Kohana/Kyoto Tycoon Client
 * @category   Extension
 * @author     Kohana Team
 * @copyright  (c) 2011-2012 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Kyoto_Tycoon_Exception extends Kohana_Exception {}

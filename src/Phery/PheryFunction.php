<?php
/**
 * The MIT License (MIT)
 *
 * Copyright © 2010-2013 Paulo Cesar, http://phery-php-ajax.net/
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the “Software”), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge,
 * publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR
 * OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * @link       http://phery-php-ajax.net/
 * @author     Paulo Cesar
 * @version    2.7.2
 * @license    http://opensource.org/licenses/MIT MIT License
 */

namespace Phery;

/**
 * Create an anonymous function for use on Javascript callbacks
 *
 * @package    Phery
 */
class PheryFunction {

    /**
     * Parameters that will be replaced inside the response
     * @var array
     */
    protected $parameters = array();
    /**
     * The function string itself
     * @var array
     */
    protected $value = null;

    /**
     * Sets new raw parameter to be passed, that will be eval'ed.
     * If you don't pass the function(){ } it will be appended
     *
     * <code>
     * $raw = new PheryFunction('function($val){ return $val; }');
     * // or
     * $raw = new PheryFunction('alert("done");'); // turns into function(){ alert("done"); }
     * </code>
     *
     * @param   string|array  $value      Raw function string. If you pass an array,
     *                                    it will be joined with a line feed \n
     * @param   array         $parameters You can pass parameters that will be replaced
     *                                    in the $value when compiling
     */
    public function __construct($value, $parameters = array())
    {
        if (!empty($value))
        {
            // Set the expression string
            if (is_array($value))
            {
                $this->value = join("\n", $value);
            }
            elseif (is_string($value))
            {
                $this->value = $value;
            }

            if (!preg_match('/^\s*function/im', $this->value))
            {
                $this->value = 'function(){' . $this->value . '}';
            }

            $this->parameters = $parameters;
        }
    }

    /**
     * Bind a variable to a parameter.
     *
     * @param   string  $param  parameter key to replace
     * @param   mixed   $var    variable to use
     * @return  PheryFunction
     */
    public function bind($param, & $var)
    {
        $this->parameters[$param] =& $var;

        return $this;
    }

    /**
     * Set the value of a parameter.
     *
     * @param   string  $param  parameter key to replace
     * @param   mixed   $value  value to use
     * @return  PheryFunction
     */
    public function param($param, $value)
    {
        $this->parameters[$param] = $value;

        return $this;
    }

    /**
     * Add multiple parameter values.
     *
     * @param   array   $params list of parameter values
     * @return  PheryFunction
     */
    public function parameters(array $params)
    {
        $this->parameters = $params + $this->parameters;

        return $this;
    }

    /**
     * Get the value as a string.
     *
     * @return  string
     */
    public function value()
    {
        return (string) $this->value;
    }

    /**
     * Return the value of the expression as a string.
     *
     * <code>
     *     echo $expression;
     * </code>
     *
     * @return  string
     */
    public function __toString()
    {
        return $this->value();
    }

    /**
     * Compile function and return it. Replaces any parameters with
     * their given values.
     *
     * @return  string
     */
    public function compile()
    {
        $value = $this->value();

        if ( ! empty($this->parameters))
        {
            $params = $this->parameters;
            $value = strtr($value, $params);
        }

        return $value;
    }

    /**
     * Static instantation for PheryFunction
     *
     * @param string|array $value
     * @param array        $parameters
     *
     * @return PheryFunction
     */
    public static function factory($value, $parameters = array())
    {
        return new PheryFunction($value, $parameters);
    }
}
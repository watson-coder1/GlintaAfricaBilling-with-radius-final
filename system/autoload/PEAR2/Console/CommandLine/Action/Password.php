<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file is part of the PEAR2\Console\CommandLine package.
 *
 * PHP version 5
 *
 * LICENSE: This source file is subject to the MIT license that is available
 * through the world-wide-web at the following URI:
 * http://opensource.org/licenses/mit-license.php
 *
 * @category  Console
 * @package   PEAR2\Console\CommandLine
 * @author    David JEAN LOUIS <izimobil@gmail.com>
 * @copyright 2007-2009 David JEAN LOUIS
 * @license   http://opensource.org/licenses/mit-license.php MIT License
 * @version   0.2.3
 * @link      http://pear2.php.net/PEAR2_Console_CommandLine
 * @since     File available since release 0.1.0
 *
 * @filesource
 */

namespace PEAR2\Console\CommandLine\Action;

use PEAR2\Console\CommandLine;

/**
 * Class that represent the Password action, a special action that allow the
 * user to specify the password on the commandline or to be prompted for
 * entering it.
 *
 * @category  Console
 * @package   PEAR2\Console\CommandLine
 * @author    David JEAN LOUIS <izimobil@gmail.com>
 * @copyright 2007-2009 David JEAN LOUIS
 * @license   http://opensource.org/licenses/mit-license.php MIT License
 * @link      http://pear2.php.net/PEAR2_Console_CommandLine
 * @since     Class available since release 0.1.0
 */
class Password extends CommandLine\Action
{
    // execute() {{{

    /**
     * Executes the action with the value entered by the user.
     *
     * @param mixed $value  The option value
     * @param array $params An array of optional parameters
     *
     * @return string
     */
    public function execute($value = false, $params = array())
    {
        $this->setResult(empty($value) ? $this->_promptPassword() : $value);
    }
    // }}}
    // _promptPassword() {{{

    /**
     * Prompts the password to the user without echoing it.
     *
     * @return string
     *
     * @todo not echo-ing the password does not work on windows is there a way
     *       to make this work ?
     */
    private function _promptPassword()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            fwrite(
                STDOUT,
                $this->parser->message_provider->get('PASSWORD_PROMPT_ECHO')
            );
            @flock(STDIN, LOCK_EX);
            $passwd = fgets(STDIN);
            @flock(STDIN, LOCK_UN);
        } else {
            fwrite(STDOUT, $this->parser->message_provider->get('PASSWORD_PROMPT'));
            // disable echoing
            system('stty -echo');
            @flock(STDIN, LOCK_EX);
            $passwd = fgets(STDIN);
            @flock(STDIN, LOCK_UN);
            system('stty echo');
        }
        return trim($passwd);
    }
    // }}}
}

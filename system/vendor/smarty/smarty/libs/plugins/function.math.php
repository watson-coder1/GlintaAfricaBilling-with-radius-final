<?php
/**
 * Smarty plugin
 * This plugin is only for Smarty2 BC
 *
 * @package    Smarty
 * @subpackage PluginsFunction
 */
/**
 * Smarty {math} function plugin
 * Type:     function
 * Name:     math
 * Purpose:  handle math computations in template
 *
 * @link   https://www.smarty.net/manual/en/language.function.math.php {math}
 *           (Smarty online manual)
 * @author Monte Ohrt <monte at ohrt dot com>
 *
 * @param array                    $params   parameters
 * @param Smarty_Internal_Template $template template object
 *
 * @return string|null
 */
function smarty_function_math($params, $template)
{
    static $_allowed_funcs =
        array(
            'int'   => true,
            'abs'   => true,
            'ceil'  => true,
            'acos'   => true,
            'acosh'   => true,
            'cos'   => true,
            'cosh'   => true,
            'deg2rad'   => true,
            'rad2deg'   => true,
            'exp'   => true,
            'floor' => true,
            'log'   => true,
            'log10' => true,
            'max'   => true,
            'min'   => true,
            'pi'    => true,
            'pow'   => true,
            'rand'  => true,
            'round' => true,
            'asin'   => true,
            'asinh'   => true,
            'sin'   => true,
            'sinh'   => true,
            'sqrt'  => true,
            'srand' => true,
            'atan'   => true,
            'atanh'   => true,
            'tan'   => true,
            'tanh'   => true
        );

    // be sure equation parameter is present
    if (empty($params[ 'equation' ])) {
        trigger_error("math: missing equation parameter", E_USER_WARNING);
        return;
    }
    $equation = $params[ 'equation' ];

    // Remove whitespaces
    $equation = preg_replace('/\s+/', '', $equation);

    // Adapted from https://www.php.net/manual/en/function.eval.php#107377
    $number = '-?(?:\d+(?:[,.]\d+)?|pi|π)'; // What is a number
    $functionsOrVars = '((?:0x[a-fA-F0-9]+)|([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))';
    $operators = '[,+\/*\^%-]'; // Allowed math operators
    $regexp = '/^(('.$number.'|'.$functionsOrVars.'|('.$functionsOrVars.'\s*\((?1)*\)|\((?1)*\)))(?:'.$operators.'(?1))?)+$/';

    if (!preg_match($regexp, $equation)) {
        trigger_error("math: illegal characters", E_USER_WARNING);
        return;
    }

    // make sure parenthesis are balanced
    if (substr_count($equation, '(') !== substr_count($equation, ')')) {
        trigger_error("math: unbalanced parenthesis", E_USER_WARNING);
        return;
    }

    // disallow backticks
    if (strpos($equation, '`') !== false) {
        trigger_error("math: backtick character not allowed in equation", E_USER_WARNING);
        return;
    }

    // also disallow dollar signs
    if (strpos($equation, '$') !== false) {
        trigger_error("math: dollar signs not allowed in equation", E_USER_WARNING);
        return;
    }
    foreach ($params as $key => $val) {
        if ($key !== 'equation' && $key !== 'format' && $key !== 'assign') {
            // make sure value is not empty
            if (strlen($val) === 0) {
                trigger_error("math: parameter '{$key}' is empty", E_USER_WARNING);
                return;
            }
            if (!is_numeric($val)) {
                trigger_error("math: parameter '{$key}' is not numeric", E_USER_WARNING);
                return;
            }
        }
    }
    // match all vars in equation, make sure all are passed
    preg_match_all('!(?:0x[a-fA-F0-9]+)|([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)!', $equation, $match);
    foreach ($match[ 1 ] as $curr_var) {
        if ($curr_var && !isset($params[ $curr_var ]) && !isset($_allowed_funcs[ $curr_var ])) {
            trigger_error(
                "math: function call '{$curr_var}' not allowed, or missing parameter '{$curr_var}'",
                E_USER_WARNING
            );
            return;
        }
    }
    foreach ($params as $key => $val) {
        if ($key !== 'equation' && $key !== 'format' && $key !== 'assign') {
            $equation = preg_replace("/\b$key\b/", " \$params['$key'] ", $equation);
        }
    }
    $smarty_math_result = null;
    eval("\$smarty_math_result = " . $equation . ";");

    if (empty($params[ 'format' ])) {
        if (empty($params[ 'assign' ])) {
            return $smarty_math_result;
        } else {
            $template->assign($params[ 'assign' ], $smarty_math_result);
        }
    } else {
        if (empty($params[ 'assign' ])) {
            printf($params[ 'format' ], $smarty_math_result);
        } else {
            $template->assign($params[ 'assign' ], sprintf($params[ 'format' ], $smarty_math_result));
        }
    }
}

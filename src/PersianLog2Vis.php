<?php

//----------------------------------------------------------------------
// Persian Log2Vis version 2
//----------------------------------------------------------------------
// Copyright (c) 2012 Oxygen Web Solutions <http://oxygenws.com>
//----------------------------------------------------------------------
// This program is under the terms of the GENERAL PUBLIC LICENSE (GPL)
// as published by the FREE SOFTWARE FOUNDATION. The GPL is available
// through the world-wide-web at http://www.gnu.org/copyleft/gpl.html
//----------------------------------------------------------------------
// Authors: Omid Mottaghi Rad <webmaster@oxygenws.com>
// Thanks to TCPDF project @ http://www.tecnick.com/
//----------------------------------------------------------------------

namespace Ghost098\PersianLog2Vis;

/**
 * Class PersianLog2Vis
 * 
 * @package Ghost098\PersianLog2Vis
 */
class PersianLog2Vis
{
    /**
     * A function to change persian or arabic text from its logical condition to visual
     *
     * @author        Omid Mottaghi Rad
     * @param        string    Main text you want to change it
     * @param        boolean    Apply e'raab characters or not? default is true
     * @param        boolean    Which encoding? default it "utf8"
     * @param        boolean    Do you want to change special characters like "allah" or "lam+alef" or "lam+hamza", default is true
     */
    public static function persian_log2vis(&$str)
    {
        require_once(__DIR__ . '/bidi.php');

        $bidi = new bidi();

        $text = explode("\n", $str);

        $str = array();

        foreach ($text as $line) {
            $chars = $bidi->utf8Bidi($bidi->UTF8StringToArray($line), 'R');
            $line = '';
            foreach ($chars as $char) {
                $line .= $bidi->unichr($char);
            }

            $str[] = $line;
        }

        $str = implode("\n", $str);
    }
}
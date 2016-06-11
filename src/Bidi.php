<?php

namespace Ghost098\PersianLog2Vis;

/**
 * Class bidi
 *
 * @package Ghost098\PersianLog2Vis
 */
class Bidi
{
    /**
     * Returns the unicode caracter specified by UTF-8 code
     * @param int $utf8Code UTF-8 code
     * @return string Returns the specified character.
     * @author Miguel Perez, Nicola Asuni
     * @since 2.3.000 (2008-03-05)
     */
    public function unichr($utf8Code)
    {
        if ($utf8Code <= 0x7F) {
            // one byte
            return chr($utf8Code);
        } else {
            if ($utf8Code <= 0x7FF) {
                // two bytes
                return chr(0xC0 | $utf8Code >> 6) . chr(0x80 | $utf8Code & 0x3F);
            } else {
                if ($utf8Code <= 0xFFFF) {
                    // three bytes
                    return chr(0xE0 | $utf8Code >> 12) . chr(0x80 | $utf8Code >> 6 & 0x3F) . chr(0x80 | $utf8Code & 0x3F);
                } else {
                    if ($utf8Code <= 0x10FFFF) {
                        // four bytes
                        return chr(0xF0 | $utf8Code >> 18) . chr(0x80 | $utf8Code >> 12 & 0x3F) . chr(0x80 | $utf8Code >> 6 & 0x3F) . chr(0x80 | $utf8Code & 0x3F);
                    } else {
                        return "";
                    }
                }
            }
        }
    }

    /**
     * Converts UTF-8 strings to codepoints array.<br>
     * Invalid byte sequences will be replaced with 0xFFFD (replacement character)<br>
     * Based on: http://www.faqs.org/rfcs/rfc3629.html
     * <pre>
     *      Char. number range  |        UTF-8 octet sequence
     *       (hexadecimal)    |              (binary)
     *    --------------------+-----------------------------------------------
     *    0000 0000-0000 007F | 0xxxxxxx
     *    0000 0080-0000 07FF | 110xxxxx 10xxxxxx
     *    0000 0800-0000 FFFF | 1110xxxx 10xxxxxx 10xxxxxx
     *    0001 0000-0010 FFFF | 11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
     *    ---------------------------------------------------------------------
     *
     *   ABFN notation:
     *   ---------------------------------------------------------------------
     *   UTF8-octets = *( UTF8-char )
     *   UTF8-char   = UTF8-1 / UTF8-2 / UTF8-3 / UTF8-4
     *   UTF8-1      = %x00-7F
     *   UTF8-2      = %xC2-DF UTF8-tail
     *
     *   UTF8-3      = %xE0 %xA0-BF UTF8-tail / %xE1-EC 2( UTF8-tail ) /
     *                 %xED %x80-9F UTF8-tail / %xEE-EF 2( UTF8-tail )
     *   UTF8-4      = %xF0 %x90-BF 2( UTF8-tail ) / %xF1-F3 3( UTF8-tail ) /
     *                 %xF4 %x80-8F 2( UTF8-tail )
     *   UTF8-tail   = %x80-BF
     *   ---------------------------------------------------------------------
     * </pre>
     * @param string $str string to process.
     * @return array containing codepoints (UTF-8 characters values)
     * @author Nicola Asuni
     * @since 1.53.0.TC005 (2005-01-05)
     */
    public function UTF8StringToArray($str)
    {
        $unicode = []; // array containing unicode values
        $bytes = []; // array containing single character byte sequences
        $numBytes = 1; // number of octetc needed to represent the UTF-8 character

        $str .= ""; // force $str to be a string
        $length = strlen($str);

        for ($i = 0; $i < $length; $i++) {
            $char = ord($str{$i}); // get one string character at time
            if (count($bytes) == 0) { // get starting octect
                if ($char <= 0x7F) {
                    $unicode[] = $char; // use the character "as is" because is ASCII
                    $numBytes = 1;
                } elseif (($char >> 0x05) == 0x06) { // 2 bytes character (0x06 = 110 BIN)
                    $bytes[] = ($char - 0xC0) << 0x06;
                    $numBytes = 2;
                } elseif (($char >> 0x04) == 0x0E) { // 3 bytes character (0x0E = 1110 BIN)
                    $bytes[] = ($char - 0xE0) << 0x0C;
                    $numBytes = 3;
                } elseif (($char >> 0x03) == 0x1E) { // 4 bytes character (0x1E = 11110 BIN)
                    $bytes[] = ($char - 0xF0) << 0x12;
                    $numBytes = 4;
                } else {
                    // use replacement character for other invalid sequences
                    $unicode[] = 0xFFFD;
                    $bytes = [];
                    $numBytes = 1;
                }
            } elseif (($char >> 0x06) == 0x02) { // bytes 2, 3 and 4 must start with 0x02 = 10 BIN
                $bytes[] = $char - 0x80;
                if (count($bytes) == $numBytes) {
                    // compose UTF-8 bytes to a single unicode value
                    $char = $bytes[0];
                    for ($j = 1; $j < $numBytes; $j++) {
                        $char += ($bytes[$j] << (($numBytes - $j - 1) * 0x06));
                    }
                    if ((($char >= 0xD800) AND ($char <= 0xDFFF)) OR ($char >= 0x10FFFF)) {
                        /* The definition of UTF-8 prohibits encoding character numbers between
                        U+D800 and U+DFFF, which are reserved for use with the UTF-16
                        encoding form (as surrogate pairs) and do not directly represent
                        characters. */
                        $unicode[] = 0xFFFD; // use replacement character
                    } else {
                        $unicode[] = $char; // add char to array
                    }
                    // reset data for next char
                    $bytes = [];
                    $numBytes = 1;
                }
            } else {
                // use replacement character for other invalid sequences
                $unicode[] = 0xFFFD;
                $bytes = [];
                $numBytes = 1;
            }
        }
        return $unicode;
    }

    /**
     * Reverse the RLT substrings using the Bidirectional Algorithm (http://unicode.org/reports/tr9/).
     * @param array $ta array of characters composing the string.
     * @param bool $forceRtl if 'R' forces RTL, if 'L' forces LTR
     * @return array
     * @author Nicola Asuni
     * @since 2.4.000 (2008-03-06)
     */
    public function utf8Bidi($ta, $forceRtl = false)
    {
        $unicode = UnicodeData::$unicode;
        $unicode_mirror = UnicodeData::$unicode_mirror;
        $unicode_arlet = UnicodeData::$unicode_arlet;
        $laa_array = UnicodeData::$laa_array;
        $diacritics = UnicodeData::$diacritics;

        // paragraph embedding level
        $pel = 0;
        // max level
        $maxLevel = 0;

        // get number of chars
        $numChars = count($ta);

        if ($forceRtl == 'R') {
            $pel = 1;
        } elseif ($forceRtl == 'L') {
            $pel = 0;
        } else {
            // P2. In each paragraph, find the first character of type L, AL, or R.
            // P3. If a character is found in P2 and it is of type AL or R, then set the paragraph embedding level to one; otherwise, set it to zero.
            for ($i = 0; $i < $numChars; $i++) {
                $type = $unicode[$ta[$i]];
                if ($type == 'L') {
                    $pel = 0;
                    break;
                } elseif (($type == 'AL') OR ($type == 'R')) {
                    $pel = 1;
                    break;
                }
            }
        }

        // Current Embedding Level
        $cel = $pel;
        // directional override status
        $dos = 'N';
        $remember = [];
        // start-of-level-run
        $sor = $pel % 2 ? 'R' : 'L';
        $eor = $sor;

        //$levels = array(array('level' => $cel, 'sor' => $sor, 'eor' => '', 'chars' => []));
        //$current_level = &$levels[count( $levels )-1];

        // Array of characters data
        $charData = [];

        // X1. Begin by setting the current embedding level to the paragraph embedding level. Set the directional override status to neutral. Process each character iteratively, applying rules X2 through X9. Only embedding levels from 0 to 61 are valid in this phase.
        // 	In the resolution of levels in rules I1 and I2, the maximum embedding level of 62 can be reached.
        for ($i = 0; $i < $numChars; $i++) {
            if ($ta[$i] == UnicodeData::K_RLE) {
                // X2. With each RLE, compute the least greater odd embedding level.
                //	a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to neutral.
                //	b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                $next_level = $cel + ($cel % 2) + 1;
                if ($next_level < 62) {
                    $remember[] = array('num' => UnicodeData::K_RLE, 'cel' => $cel, 'dos' => $dos);
                    $cel = $next_level;
                    $dos = 'N';
                    $sor = $eor;
                    $eor = $cel % 2 ? 'R' : 'L';
                }
            } elseif ($ta[$i] == UnicodeData::K_LRE) {
                // X3. With each LRE, compute the least greater even embedding level.
                //	a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to neutral.
                //	b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                $next_level = $cel + 2 - ($cel % 2);
                if ($next_level < 62) {
                    $remember[] = array('num' => UnicodeData::K_LRE, 'cel' => $cel, 'dos' => $dos);
                    $cel = $next_level;
                    $dos = 'N';
                    $sor = $eor;
                    $eor = $cel % 2 ? 'R' : 'L';
                }
            } elseif ($ta[$i] == UnicodeData::K_RLO) {
                // X4. With each RLO, compute the least greater odd embedding level.
                //	a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to right-to-left.
                //	b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                $next_level = $cel + ($cel % 2) + 1;
                if ($next_level < 62) {
                    $remember[] = array('num' => UnicodeData::K_RLO, 'cel' => $cel, 'dos' => $dos);
                    $cel = $next_level;
                    $dos = 'R';
                    $sor = $eor;
                    $eor = $cel % 2 ? 'R' : 'L';
                }
            } elseif ($ta[$i] == UnicodeData::K_LRO) {
                // X5. With each LRO, compute the least greater even embedding level.
                //	a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to left-to-right.
                //	b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                $next_level = $cel + 2 - ($cel % 2);
                if ($next_level < 62) {
                    $remember[] = array('num' => UnicodeData::K_LRO, 'cel' => $cel, 'dos' => $dos);
                    $cel = $next_level;
                    $dos = 'L';
                    $sor = $eor;
                    $eor = $cel % 2 ? 'R' : 'L';
                }
            } elseif ($ta[$i] == UnicodeData::K_PDF) {
                // X7. With each PDF, determine the matching embedding or override code. If there was a valid matching code, restore (pop) the last remembered (pushed) embedding level and directional override.
                if (count($remember)) {
                    $last = count($remember) - 1;
                    if (($remember[$last]['num'] == UnicodeData::K_RLE) OR
                        ($remember[$last]['num'] == UnicodeData::K_LRE) OR
                        ($remember[$last]['num'] == UnicodeData::K_RLO) OR
                        ($remember[$last]['num'] == UnicodeData::K_LRO)
                    ) {
                        $match = array_pop($remember);
                        $cel = $match['cel'];
                        $dos = $match['dos'];
                        $sor = $eor;
                        $eor = ($cel > $match['cel'] ? $cel : $match['cel']) % 2 ? 'R' : 'L';
                    }
                }
            } elseif (($ta[$i] != UnicodeData::K_RLE) AND
                ($ta[$i] != UnicodeData::K_LRE) AND
                ($ta[$i] != UnicodeData::K_RLO) AND
                ($ta[$i] != UnicodeData::K_LRO) AND
                ($ta[$i] != UnicodeData::K_PDF)
            ) {
                // X6. For all types besides RLE, LRE, RLO, LRO, and PDF:
                //	a. Set the level of the current character to the current embedding level.
                //	b. Whenever the directional override status is not neutral, reset the current character type to the directional override status.
                if ($dos != 'N') {
                    $chardir = $dos;
                } else {
                    $chardir = $unicode[$ta[$i]];
                }
                // stores string characters and other information
                $charData[] = array(
                    'char' => $ta[$i],
                    'level' => $cel,
                    'type' => $chardir,
                    'sor' => $sor,
                    'eor' => $eor
                );
            }
        } // end for each char

        // X8. All explicit directional embeddings and overrides are completely terminated at the end of each paragraph. Paragraph separators are not included in the embedding.
        // X9. Remove all RLE, LRE, RLO, LRO, PDF, and BN codes.
        // X10. The remaining rules are applied to each run of characters at the same level. For each run, determine the start-of-level-run (sor) and end-of-level-run (eor) type, either L or R. This depends on the higher of the two levels on either side of the boundary (at the start or end of the paragraph, the level of the �other� run is the base embedding level). If the higher level is odd, the type is R; otherwise, it is L.

        // 3.3.3 Resolving Weak Types
        // Weak types are now resolved one level run at a time. At level run boundaries where the type of the character on the other side of the boundary is required, the type assigned to sor or eor is used.
        // Nonspacing marks are now resolved based on the previous characters.
        $numChars = count($charData);

        // W1. Examine each nonspacing mark (NSM) in the level run, and change the type of the NSM to the type of the previous character. If the NSM is at the start of the level run, it will get the type of sor.
        $prevLevel = -1; // track level changes
        $levCount = 0; // counts consecutive chars at the same level
        for ($i = 0; $i < $numChars; $i++) {
            if ($charData[$i]['type'] == 'NSM') {
                if ($levCount) {
                    $charData[$i]['type'] = $charData[$i]['sor'];
                } elseif ($i > 0) {
                    $charData[$i]['type'] = $charData[($i - 1)]['type'];
                }
            }
            if ($charData[$i]['level'] != $prevLevel) {
                $levCount = 0;
            } else {
                $levCount++;
            }
            $prevLevel = $charData[$i]['level'];
        }

        // W2. Search backward from each instance of a European number until the first strong type (R, L, AL, or sor) is found. If an AL is found, change the type of the European number to Arabic number.
        $prevLevel = -1;
        $levCount = 0;
        for ($i = 0; $i < $numChars; $i++) {
            if ($charData[$i]['char'] == 'EN') {
                for ($j = $levCount; $j >= 0; $j--) {
                    if ($charData[$j]['type'] == 'AL') {
                        $charData[$i]['type'] = 'AN';
                    } elseif (($charData[$j]['type'] == 'L') OR ($charData[$j]['type'] == 'R')) {
                        break;
                    }
                }
            }
            if ($charData[$i]['level'] != $prevLevel) {
                $levCount = 0;
            } else {
                $levCount++;
            }
            $prevLevel = $charData[$i]['level'];
        }

        // W3. Change all ALs to R.
        for ($i = 0; $i < $numChars; $i++) {
            if ($charData[$i]['type'] == 'AL') {
                $charData[$i]['type'] = 'R';
            }
        }

        // W4. A single European separator between two European numbers changes to a European number. A single common separator between two numbers of the same type changes to that type.
        $prevLevel = -1;
        $levCount = 0;
        for ($i = 0; $i < $numChars; $i++) {
            if (($levCount > 0) AND (($i + 1) < $numChars) AND ($charData[($i + 1)]['level'] == $prevLevel)) {
                if (($charData[$i]['type'] == 'ES') AND ($charData[($i - 1)]['type'] == 'EN') AND ($charData[($i + 1)]['type'] == 'EN')) {
                    $charData[$i]['type'] = 'EN';
                } elseif (($charData[$i]['type'] == 'CS') AND ($charData[($i - 1)]['type'] == 'EN') AND ($charData[($i + 1)]['type'] == 'EN')) {
                    $charData[$i]['type'] = 'EN';
                } elseif (($charData[$i]['type'] == 'CS') AND ($charData[($i - 1)]['type'] == 'AN') AND ($charData[($i + 1)]['type'] == 'AN')) {
                    $charData[$i]['type'] = 'AN';
                }
            }
            if ($charData[$i]['level'] != $prevLevel) {
                $levCount = 0;
            } else {
                $levCount++;
            }
            $prevLevel = $charData[$i]['level'];
        }

        // W5. A sequence of European terminators adjacent to European numbers changes to all European numbers.
        $prevLevel = -1;
        $levCount = 0;
        for ($i = 0; $i < $numChars; $i++) {
            if ($charData[$i]['type'] == 'ET') {
                if (($levCount > 0) AND ($charData[($i - 1)]['type'] == 'EN')) {
                    $charData[$i]['type'] = 'EN';
                } else {
                    $j = $i + 1;
                    while (($j < $numChars) AND ($charData[$j]['level'] == $prevLevel)) {
                        if ($charData[$j]['type'] == 'EN') {
                            $charData[$i]['type'] = 'EN';
                            break;
                        } elseif ($charData[$j]['type'] != 'ET') {
                            break;
                        }
                        $j++;
                    }
                }
            }
            if ($charData[$i]['level'] != $prevLevel) {
                $levCount = 0;
            } else {
                $levCount++;
            }
            $prevLevel = $charData[$i]['level'];
        }

        // W6. Otherwise, separators and terminators change to Other Neutral.
        $prevLevel = -1;
        $levCount = 0;
        for ($i = 0; $i < $numChars; $i++) {
            if (($charData[$i]['type'] == 'ET') OR ($charData[$i]['type'] == 'ES') OR ($charData[$i]['type'] == 'CS')) {
                $charData[$i]['type'] = 'ON';
            }
            if ($charData[$i]['level'] != $prevLevel) {
                $levCount = 0;
            } else {
                $levCount++;
            }
            $prevLevel = $charData[$i]['level'];
        }

        //W7. Search backward from each instance of a European number until the first strong type (R, L, or sor) is found. If an L is found, then change the type of the European number to L.
        $prevLevel = -1;
        $levCount = 0;
        for ($i = 0; $i < $numChars; $i++) {
            if ($charData[$i]['char'] == 'EN') {
                for ($j = $levCount; $j >= 0; $j--) {
                    if ($charData[$j]['type'] == 'L') {
                        $charData[$i]['type'] = 'L';
                    } elseif ($charData[$j]['type'] == 'R') {
                        break;
                    }
                }
            }
            if ($charData[$i]['level'] != $prevLevel) {
                $levCount = 0;
            } else {
                $levCount++;
            }
            $prevLevel = $charData[$i]['level'];
        }

        // N1. A sequence of neutrals takes the direction of the surrounding strong text if the text on both sides has the same direction. European and Arabic numbers act as if they were R in terms of their influence on neutrals. Start-of-level-run (sor) and end-of-level-run (eor) are used at level run boundaries.
        $prevLevel = -1;
        $levCount = 0;
        for ($i = 0; $i < $numChars; $i++) {
            if (($levCount > 0) AND (($i + 1) < $numChars) AND ($charData[($i + 1)]['level'] == $prevLevel)) {
                if (($charData[$i]['type'] == 'N') AND ($charData[($i - 1)]['type'] == 'L') AND ($charData[($i + 1)]['type'] == 'L')) {
                    $charData[$i]['type'] = 'L';
                } elseif (($charData[$i]['type'] == 'N') AND
                    (($charData[($i - 1)]['type'] == 'R') OR ($charData[($i - 1)]['type'] == 'EN') OR ($charData[($i - 1)]['type'] == 'AN')) AND
                    (($charData[($i + 1)]['type'] == 'R') OR ($charData[($i + 1)]['type'] == 'EN') OR ($charData[($i + 1)]['type'] == 'AN'))
                ) {
                    $charData[$i]['type'] = 'R';
                } elseif ($charData[$i]['type'] == 'N') {
                    // N2. Any remaining neutrals take the embedding direction
                    $charData[$i]['type'] = $charData[$i]['sor'];
                }
            } elseif (($levCount == 0) AND (($i + 1) < $numChars) AND ($charData[($i + 1)]['level'] == $prevLevel)) {
                // first char
                if (($charData[$i]['type'] == 'N') AND ($charData[$i]['sor'] == 'L') AND ($charData[($i + 1)]['type'] == 'L')) {
                    $charData[$i]['type'] = 'L';
                } elseif (($charData[$i]['type'] == 'N') AND
                    (($charData[$i]['sor'] == 'R') OR ($charData[$i]['sor'] == 'EN') OR ($charData[$i]['sor'] == 'AN')) AND
                    (($charData[($i + 1)]['type'] == 'R') OR ($charData[($i + 1)]['type'] == 'EN') OR ($charData[($i + 1)]['type'] == 'AN'))
                ) {
                    $charData[$i]['type'] = 'R';
                } elseif ($charData[$i]['type'] == 'N') {
                    // N2. Any remaining neutrals take the embedding direction
                    $charData[$i]['type'] = $charData[$i]['sor'];
                }
            } elseif (($levCount > 0) AND ((($i + 1) == $numChars) OR (($i + 1) < $numChars) AND ($charData[($i + 1)]['level'] != $prevLevel))) {
                //last char
                if (($charData[$i]['type'] == 'N') AND ($charData[($i - 1)]['type'] == 'L') AND ($charData[$i]['eor'] == 'L')) {
                    $charData[$i]['type'] = 'L';
                } elseif (($charData[$i]['type'] == 'N') AND
                    (($charData[($i - 1)]['type'] == 'R') OR ($charData[($i - 1)]['type'] == 'EN') OR ($charData[($i - 1)]['type'] == 'AN')) AND
                    (($charData[$i]['eor'] == 'R') OR ($charData[$i]['eor'] == 'EN') OR ($charData[$i]['eor'] == 'AN'))
                ) {
                    $charData[$i]['type'] = 'R';
                } elseif ($charData[$i]['type'] == 'N') {
                    // N2. Any remaining neutrals take the embedding direction
                    $charData[$i]['type'] = $charData[$i]['sor'];
                }
            } elseif ($charData[$i]['type'] == 'N') {
                // N2. Any remaining neutrals take the embedding direction
                $charData[$i]['type'] = $charData[$i]['sor'];
            }
            if ($charData[$i]['level'] != $prevLevel) {
                $levCount = 0;
            } else {
                $levCount++;
            }
            $prevLevel = $charData[$i]['level'];
        }

        // I1. For all characters with an even (left-to-right) embedding direction, those of type R go up one level and those of type AN or EN go up two levels.
        // I2. For all characters with an odd (right-to-left) embedding direction, those of type L, EN or AN go up one level.
        for ($i = 0; $i < $numChars; $i++) {
            $odd = $charData[$i]['level'] % 2;
            if ($odd) {
                if (($charData[$i]['type'] == 'L') OR ($charData[$i]['type'] == 'AN') OR ($charData[$i]['type'] == 'EN')) {
                    $charData[$i]['level'] += 1;
                }
            } else {
                if ($charData[$i]['type'] == 'R') {
                    $charData[$i]['level'] += 1;
                } elseif (($charData[$i]['type'] == 'AN') OR ($charData[$i]['type'] == 'EN')) {
                    $charData[$i]['level'] += 2;
                }
            }
            $maxLevel = max($charData[$i]['level'], $maxLevel);
        }

        // L1. On each line, reset the embedding level of the following characters to the paragraph embedding level:
        //	1. Segment separators,
        //	2. Paragraph separators,
        //	3. Any sequence of whitespace characters preceding a segment separator or paragraph separator, and
        //	4. Any sequence of white space characters at the end of the line.
        for ($i = 0; $i < $numChars; $i++) {
            if (($charData[$i]['type'] == 'B') OR ($charData[$i]['type'] == 'S')) {
                $charData[$i]['level'] = $pel;
            } elseif ($charData[$i]['type'] == 'WS') {
                $j = $i + 1;
                while ($j < $numChars) {
                    if ((($charData[$j]['type'] == 'B') OR ($charData[$j]['type'] == 'S')) OR
                        (($j == ($numChars - 1)) AND ($charData[$j]['type'] == 'WS'))
                    ) {
                        $charData[$i]['level'] = $pel;;
                        break;
                    } elseif ($charData[$j]['type'] != 'WS') {
                        break;
                    }
                    $j++;
                }
            }
        }

        // Arabic Shaping
        // Cursively connected scripts, such as Arabic or Syriac, require the selection of positional character shapes that depend on adjacent characters. Shaping is logically applied after the Bidirectional Algorithm is used and is limited to characters within the same directional run. 
        $endedLetter = array(1569, 1570, 1571, 1572, 1573, 1575, 1577, 1583, 1584, 1585, 1586, 1608, 1688);
        $alfLetter = array(1570, 1571, 1573, 1575);
        $charData2 = $charData;
        $laaLetter = false;
        $charAL = [];
        $x = 0;
        for ($i = 0; $i < $numChars; $i++) {
            if (($unicode[$charData[$i]['char']] == 'AL') OR ($unicode[$charData[$i]['char']] == 'WS')) {
                $charAL[$x] = $charData[$i];
                $charAL[$x]['i'] = $i;
                $charData[$i]['x'] = $x;
                $x++;
            }
        }
        $numAL = $x;

        for ($i = 0; $i < $numChars; $i++) {
            $thisChar = $charData[$i];
            if ($i > 0) {
                $prevChar = $charData[($i - 1)];
            } else {
                $prevChar = false;
            }

            if (($i + 1) < $numChars) {
                $nextChar = $charData[($i + 1)];
            } else {
                $nextChar = false;
            }

            if ($unicode[$thisChar['char']] == 'AL') {
                $x = $thisChar['x'];
                if ($x > 0) {
                    $prevChar = $charAL[($x - 1)];
                } else {
                    $prevChar = false;
                }
                if (($x + 1) < $numAL) {
                    $nextChar = $charAL[($x + 1)];
                } else {
                    $nextChar = false;
                }
                // if laa letter
                if (($prevChar !== false) AND ($prevChar['char'] == 1604) AND (in_array($thisChar['char'],
                        $alfLetter))
                ) {
                    $arabicArr = $laa_array;
                    $laaLetter = true;
                    if ($x > 1) {
                        $prevChar = $charAL[($x - 2)];
                    } else {
                        $prevChar = false;
                    }
                } else {
                    $arabicArr = $unicode_arlet;
                    $laaLetter = false;
                }
                if (($prevChar !== false) AND ($nextChar !== false) AND
                    (($unicode[$prevChar['char']] == 'AL') OR ($unicode[$prevChar['char']] == 'NSM')) AND
                    (($unicode[$nextChar['char']] == 'AL') OR ($unicode[$nextChar['char']] == 'NSM')) AND
                    ($prevChar['type'] == $thisChar['type']) AND
                    ($nextChar['type'] == $thisChar['type']) AND
                    ($nextChar['char'] != 1567)
                ) {
                    if (in_array($prevChar['char'], $endedLetter)) {
                        if (isset($arabicArr[$thisChar['char']][2])) {
                            // initial
                            $charData2[$i]['char'] = $arabicArr[$thisChar['char']][2];
                        }
                    } else {
                        if (isset($arabicArr[$thisChar['char']][3])) {
                            // medial
                            $charData2[$i]['char'] = $arabicArr[$thisChar['char']][3];
                        }
                    }
                } elseif (($nextChar !== false) AND
                    (($unicode[$nextChar['char']] == 'AL') OR ($unicode[$nextChar['char']] == 'NSM')) AND
                    ($nextChar['type'] == $thisChar['type']) AND
                    ($nextChar['char'] != 1567)
                ) {
                    if (isset($arabicArr[$charData[$i]['char']][2])) {
                        // initial
                        $charData2[$i]['char'] = $arabicArr[$thisChar['char']][2];
                    }
                } elseif ((($prevChar !== false) AND
                        (($unicode[$prevChar['char']] == 'AL') OR ($unicode[$prevChar['char']] == 'NSM')) AND
                        ($prevChar['type'] == $thisChar['type'])) OR
                    (($nextChar !== false) AND ($nextChar['char'] == 1567))
                ) {
                    // final
                    if (($i > 1) AND ($thisChar['char'] == 1607) AND
                        ($charData[$i - 1]['char'] == 1604) AND
                        ($charData[$i - 2]['char'] == 1604)
                    ) {
                        //Allah Word
                        // mark characters to delete with false
                        $charData2[$i - 2]['char'] = false;
                        $charData2[$i - 1]['char'] = false;
                        $charData2[$i]['char'] = 65010;
                    } else {
                        if (($prevChar !== false) AND in_array($prevChar['char'], $endedLetter)) {
                            if (isset($arabicArr[$thisChar['char']][0])) {
                                // isolated
                                $charData2[$i]['char'] = $arabicArr[$thisChar['char']][0];
                            }
                        } else {
                            if (isset($arabicArr[$thisChar['char']][1])) {
                                // final
                                $charData2[$i]['char'] = $arabicArr[$thisChar['char']][1];
                            }
                        }
                    }
                } elseif (isset($arabicArr[$thisChar['char']][0])) {
                    // isolated
                    $charData2[$i]['char'] = $arabicArr[$thisChar['char']][0];
                }
                // if laa letter
                if ($laaLetter) {
                    // mark characters to delete with false
                    $charData2[($charAL[($x - 1)]['i'])]['char'] = false;
                }
            } // end if AL (Arabic Letter)
        } // end for each char

        // remove marked characters
        foreach ($charData2 as $key => $value) {
            if ($value['char'] === false) {
                unset($charData2[$key]);
            }
        }
        $charData = array_values($charData2);
        $numChars = count($charData);
        unset($charData2);
        unset($arabicArr);
        unset($laaLetter);
        unset($charAL);

        // L2. From the highest level found in the text to the lowest odd level on each line, including intermediate levels not actually present in the text, reverse any contiguous sequence of characters that are at that level or higher.
        for ($j = $maxLevel; $j > 0; $j--) {
            $ordArray = [];
            $revArr = [];
            $onLevel = false;
            for ($i = 0; $i < $numChars; $i++) {
                if ($charData[$i]['level'] >= $j) {
                    $onLevel = true;
                    if (isset($unicode_mirror[$charData[$i]['char']])) {
                        // L4. A character is depicted by a mirrored glyph if and only if (a) the resolved directionality of that character is R, and (b) the Bidi_Mirrored property value of that character is true.
                        $charData[$i]['char'] = $unicode_mirror[$charData[$i]['char']];
                    }
                    $revArr[] = $charData[$i];
                } else {
                    if ($onLevel) {
                        $revArr = array_reverse($revArr);
                        $ordArray = array_merge($ordArray, $revArr);
                        $revArr = [];
                        $onLevel = false;
                    }
                    $ordArray[] = $charData[$i];
                }
            }
            if ($onLevel) {
                $revArr = array_reverse($revArr);
                $ordArray = array_merge($ordArray, $revArr);
            }
            $charData = $ordArray;
        }

        $ordArray = [];
        for ($i = 0; $i < $numChars; $i++) {
            $ordArray[] = $charData[$i]['char'];
        }

        return $ordArray;
    }
}

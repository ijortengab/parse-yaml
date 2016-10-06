<?php

namespace IjorTengab\ParseYML;

use IjorTengab\Tools\Abstracts\AbstractAnalyzeCharacter;
use IjorTengab\Tools\Functions\CamelCase;
use IjorTengab\Tools\Functions\ArrayDimensional;

/**
 *
 */
class ParseYML extends AbstractAnalyzeCharacter
{
    protected $current_step = 'init';
    protected $next_step;
    protected $has_parsed = false;
    protected $data = [];
    protected $lines = [];
    protected $keys = [];
    protected $keys_temporary = [];
    protected $indents_temporary = [];
    protected $sequence_of_scalar = [];
    protected $is_ongoing_wrapped_by_quote = false;
    protected $quote_is_single = false;
    protected $quote_is_double = false;
    /**
     * Kondisi looping saat ini.
     */
    protected $current_array_dimension = 0;
    protected $current_array_dimension_is_indexed = false;
    protected $current_array_dimension_is_associative = false;
    /**
     * Kondisi character saat ini.
     */
    protected $is_alphanumeric = false;
    protected $is_space = false; // All whitespace except \r \n.
    protected $is_quote = false;
    protected $is_separator = false;
    protected $is_commentsign = false;

    /**
     * Melakukan parsing.
     */
    public function parse()
    {
        if (false === $this->has_parsed) {
            $this->has_parsed = true;
            return $this->analyze();
        }
    }

    /**
     *
     */
    public function getResult()
    {
        return $this->data;
    }

    /**
     *
     */
    protected function analyzeCurrentLine()
    {
        $this->debug(__METHOD__, '__METHOD__');
        $this->debug($this->current_array_dimension, '$this->current_array_dimension', 1);
        $this->debug($this->current_array_dimension_is_indexed, '$this->current_array_dimension_is_indexed', 1);
        $this->debug($this->current_array_dimension_is_associative, '$this->current_array_dimension_is_associative', 1);
        $this->debug($this->indents_temporary, '$this->indents_temporary', 1);
        if ($this->current_step !== 'init') {
            return;
        }
        if ($this->current_line_string === '') {
            return;
        }
        $current_line_string = $this->current_line_string;
        // Jika semuanya adalah spasi
        if (ctype_space($current_line_string)) {
            $this->setCharacter('key_prepend', $current_line_string);
            $this->current_character += strlen($current_line_string);
            return;
        }
        // Jika isinya cuma comment.
        if (preg_match('/^(?<leading_space>[\s]*)(?<comment>#.*)/', $current_line_string, $match)) {
            $this->debug($match, '$match', 2);
            return $this->analyzeLineComment($match);
        }
        // Jika line merupakan sequence of scalar (array indexed).
        if (preg_match('/^(?<leading_space>[\s]*)(?<sequence_sign>-[\s])(?<other>.*)/', $current_line_string, $match)) {
            $this->debug($match, '$match', 2);
            return $this->analyzeLineSequenceOfScalar($match);
        }
        // Jika line merupakan mapping of scalar (array associative).
        if (preg_match('/^(?<leading_space>[\s]*)(?<key>[^\s].*):/', $current_line_string, $match)) {
            $this->debug($match, '$match', 2);
            return $this->analyzeLineMappingOfScalar($match);
        }
        // Gak ada yang cocok? Lempar.
        throw new RuntimeException('Unable to parse the line. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
    }

    /**
     *
     */
    protected function analyzeLineComment($match)
    {
        $add = 0;
        if (!empty($match['leading_space'])) {
            $this->verifyIndent('tab', $match['leading_space']);
            $this->setCharacter('key_prepend', $match['leading_space']);
            $add += strlen($match['leading_space']);
        }
        $this->setCharacter('comment', $match['comment']);
        $add += strlen($match['comment']);
        $this->current_character += $add;
        $this->current_column += $add;
    }

    /**
     *
     */
    protected function analyzeLineSequenceOfScalar($match)
    {
        if (!empty($match['leading_space'])) {
            $this->verifyIndent('tab', $match['leading_space']);
            $this->verifyIndent('dimension', $match['leading_space']);
        }
        $current_indent_string = $match['leading_space'];
        $current_indent_length = strlen($current_indent_string);
        if ($this->current_array_dimension === 0) {
            $this->nextDimension('indexed', $current_indent_string);
        }
        else {
            // mulai  dari sini.
            $current_dimension_indent_length = $this->indents_temporary[$this->current_array_dimension]['length'];
            $current_dimension_indent_array_type = $this->indents_temporary[$this->current_array_dimension]['array_type'];
            if ($current_indent_length > $current_dimension_indent_length) {
                // Registrasi indent.
                $this->nextDimension('indexed', $current_indent_string);
            }
            elseif ($current_indent_length < $current_dimension_indent_length) {
                $this->prevDimension('indexed', $current_indent_string);
            }
            else { // Equals.
                // Pada kasus
                // ```
                // sedang: entah
                // - makan: cinta
                // ```
                // Baris pertama merupakan mapping of scalar
                // Sementara baris kedua merupakan sequence of scalar.
                // Karena itu tidak valid, maka saat kita sedang
                // menganalisis baris kedua, jika current dimension
                // merupakan mapping of scalar (associative array)
                // kita perlu mengecek apakah baris sebelumnya
                // ada value atau tidak.
                if ($this->current_array_dimension_is_associative) {
                    $prev_line = $this->current_line - 1;
                    $dimension = $this->current_array_dimension;
                    if (array_key_exists('value', $this->lines[$prev_line][$dimension]['segmen'])) {
                        throw new RuntimeException('Rule5: You cannot define a sequence item when in a mapping. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
                    }
                }
                // Pada kasus
                // ```
                // -  sedang:
                //    - makan: cinta
                //    - kita: bisa
                // ```
                // Perhatikan bahwa length indent dari dimensi ke-2
                // sama dengan length indent dari dimensi ke-3,
                // tapi dimensi ke 2 adalah associative sementara
                // dimensi ke-3 adalah indexed.
                // Jadi meski sama lengthnya
                // kita tetap perlu mengubah menjadi dimensi berikutnya.
                if ($this->current_array_dimension_is_associative) {
                    $this->nextDimension('indexed', $current_indent_string);
                }
                else {
                    $this->setCurrentArrayAs('indexed');
                }
            }
        }
        $add = 0;
        if (!empty($match['leading_space'])) {
            $this->setCharacter('value_prepend', $match['leading_space']);
            $add += strlen($match['leading_space']);
        }
        // 2 = strlen($match['sequence_sign']).
        $add += 2;
        $this->current_character += $add;
        $this->current_column += $add;
        $this->setCharacter('value_prepend', $match['sequence_sign']);
        $this->current_step = 'build_value_prepend';
    }

    /**
     *
     */
    protected function beforeAnalyze()
    {
        if (false === strpos($this->raw, "\r") && false === strpos($this->raw, "\n")) {
            $this->current_step = 'build_value_prepend';
        }
    }

    /**
     *
     */
    protected function afterAnalyze()
    {
        $this->debug(__METHOD__, '__METHOD__');
        $this->debug($this->lines, '$this->lines');
        // if (0 === $dimension) {
                    // $this->data = $value;
                    // break 2;
                // }
        
        
        
        $this->buildData();
    }

    /**
     *
     */
    protected function analyzeLineMappingOfScalar($match)
    {
        if (!empty($match['leading_space'])) {
            // Leading space hanya boleh jika dimensi lebih dari 1
            $this->verifyIndent('tab', $match['leading_space']);
            $this->verifyIndent('dimension', $match['leading_space']);
        }
        $current_indent_string = $match['leading_space'];
        $current_indent_length = strlen($current_indent_string);
        if ($this->current_array_dimension === 0) {
            // Registrasi indent.
            $this->nextDimension('associative', $current_indent_string);
        }
        else {
            $current_dimension_indent_length = $this->indents_temporary[$this->current_array_dimension]['length'];
            if ($current_indent_length > $current_dimension_indent_length) {
                // Todo. Berikan contoh:
                $this->nextDimension('associative', $current_indent_string);
            }
            elseif ($current_indent_length < $current_dimension_indent_length) {
                $this->prevDimension('associative', $current_indent_string);
            }
            else { // Equals.
                // Pada kasus
                // ```
                // - aa
                // - cc
                // dd: xxx
                // ```
                if ($this->current_array_dimension_is_indexed) {
                    throw new RuntimeException('Rule6: You cannot define a mapping item when in a sequence. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
                }
                else {
                    $this->setCurrentArrayAs('associative');
                }
            }
        }
        $add = 0;
        if (!empty($match['leading_space'])) {
            $this->setCharacter('key_prepend', $match['leading_space']);
            $add += strlen($match['leading_space']);
        }
        $this->current_character += $add;
        $this->current_column += $add;
        $this->current_step = 'build_key_prepend';
    }

    /**
     * Bermain dengan escape karakter.
     */
    protected function manipulateCurrentCharacter()
    {
        parent::manipulateCurrentCharacter();
        $this->debug(__METHOD__, '__METHOD__');
        $ch = $this->current_character_string;
        $nch = $this->next_character_string;
        // Mendefinisikan character quote yang di-escape agar tidak bentrok
        // dengan $this->is_quote.
        if ($this->is_ongoing_wrapped_by_quote) {
            if ($ch === '\\') {
                $quote = $this->quote_is_single ? "'" : '"';
                // Key seperti contoh dibawah hasilnya failed:
                // ```
                // 'a\'a': bb
                // ```
                if ($quote == "'" && $nch == "'") {
                    throw new RuntimeException('Unable to parse the line. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
                }
                $x = $this->current_character;
                $ch = '\\' . $nch;
                $nch = isset($this->raw[$x+1]) ? $this->raw[$x+2] : false;
                $this->current_character_string = $ch;
                $this->next_character_string = $nch;
                $this->current_character++;
            }
        }
        // Debug.
        $this->debug($this->current_character_string, '$this->current_character_string', 1);
        $this->debug($this->next_character_string, '$this->next_character_string', 1);
        $this->debug($this->prev_character_string, '$this->prev_character_string', 1);
    }

    /**
     *
     */
    protected function assignCurrentCharacter()
    {
        parent::assignCurrentCharacter();
        $this->debug(__METHOD__, '__METHOD__');
        $ch = $this->current_character_string;
        if (ctype_alnum($ch)) {
            $this->is_alphanumeric = true;
        }
        elseif (ctype_space($ch) && !in_array($ch, ["\r", "\n", "\r\n"])) {
            $this->is_space = true;
        }
        elseif (in_array($ch, ["'", '"'])) {
            $this->is_quote = true;
        }
        elseif ($ch === ':') {
            $this->is_separator = true;
        }
        elseif ($ch === '#') {
            $this->is_commentsign = true;
        }
    }

    /**
     *
     */
    protected function analyzeCurrentCharacter()
    {
        $this->debug(__METHOD__, '__METHOD__');
        $this->debug($this->is_last, '$this->is_last', 1);
        $this->debug($this->is_alphanumeric, '$this->is_alphanumeric', 1);
        $this->debug($this->is_break, '$this->is_break', 1);
        $this->debug($this->is_space, '$this->is_space', 1);
        $this->debug($this->is_quote, '$this->is_quote', 1);
        $this->debug($this->is_separator, '$this->is_separator', 1);
        $this->debug($this->is_commentsign, '$this->is_commentsign', 1);
        $this->runStep();
    }

    /**
     *
     */
    protected function prepareNextLoop()
    {
        parent::prepareNextLoop();
        $this->debug(__METHOD__, '__METHOD__');
        $this->debug($this->next_step, '$this->next_step');
        if ($this->next_step !== null) {
            $this->debug($this->next_step, '$this->next_step', 1);
            $this->current_step = $this->next_step;
            $this->next_step = null;
        }
    }

    /**
     *
     */
    protected function resetAssignCharacter()
    {
        parent::resetAssignCharacter();
        $this->is_alphanumeric = false;
        $this->is_space = false;
        $this->is_quote = false;
        $this->is_separator = false;
        $this->is_commentsign = false;
    }

    /**
     *
     */
    protected function runStep()
    {
        $this->debug(__METHOD__, '__METHOD__');
        $this->debug($this->current_step, '$this->current_step', 1);
        $current_step = $this->current_step;
        $_method = 'analyze_step_' . $this->current_step;
        $method = CamelCase::convertFromUnderScore($_method);
        $this->{$method}();
    }

    /**
     * Copy dari IjorTengab\Tools\Functions\CamelCase::convertFromUnderScore()
     * untuk menghindari require ijortengab/tools.
     */
    protected function setCharacter($key, $value)
    {
        $this->debug(__METHOD__, '__METHOD__');
        $this->debug($key, '$key', 1);
        $this->debug($value, '$value', 1);
        if (!isset($this->lines[$this->current_line][$this->current_array_dimension]['segmen'][$key])) {
            $this->lines[$this->current_line][$this->current_array_dimension]['segmen'][$key] = '';
        }
        $this->lines[$this->current_line][$this->current_array_dimension]['segmen'][$key] .= $value;
    }

    /**
     *
     */
    protected function setCurrentCharacterAs($key)
    {
        $this->debug(__METHOD__, '__METHOD__');
        return $this->setCharacter($key, $this->current_character_string);
    }

    /**
     *
     */
    protected function getCharacter($key)
    {
        $this->debug(__METHOD__, '__METHOD__');
        $this->debug($key, '$key', 1);
        if (isset($this->lines[$this->current_line][$this->current_array_dimension]['segmen'][$key])) {
            return $this->lines[$this->current_line][$this->current_array_dimension]['segmen'][$key];
        }
    }

    /**
     *
     */
    protected function delCharacter($key)
    {
        $this->debug(__METHOD__, '__METHOD__');
        $this->debug($key, '$key', 1);
        unset($this->lines[$this->current_line][$this->current_array_dimension]['segmen'][$key]);
    }

    /**
     *
     */
    protected function analyzeStepInit()
    {
        $this->debug(__METHOD__, '__METHOD__');
        if ($this->is_break) {
            $this->setCurrentCharacterAs('eol');
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildKeyPrepend()
    {
        $this->debug(__METHOD__, '__METHOD__');
        if ($this->is_alphanumeric) {
            $this->setCurrentCharacterAs('key');
            $this->next_step = 'build_key';
        }
        elseif ($this->is_quote) {
            $this->setCurrentCharacterAs('quote_key');
            $this->next_step = 'build_key';
            $this->toggleQuote(true);
        }
        else {
            $this->error('Unexpected characters.');
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildKey()
    {
        $this->debug(__METHOD__, '__METHOD__');
        $default = false;
        if ($this->is_alphanumeric) {
            $default = true;
        }
        elseif ($this->is_separator) {
            if ($this->is_ongoing_wrapped_by_quote) {
                $default = true;
            }
            else {                
                // Rule1: Karakter sesudah separator harus whitespace.
                if (!ctype_space($this->next_character_string)) {
                    throw new RuntimeException('Rule1: Karakter sesudah separator harus whitespace. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
                }
                $this->setCurrentCharacterAs('separator');
                $this->next_step = 'build_value_prepend';
            }
        }
        elseif ($this->is_quote) {
            if ($this->is_ongoing_wrapped_by_quote) {
               $quote = $this->quote_is_single ? "'" : '"'; 
               // $debugname = 'default'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
               
               
               
               if ($quote === $this->current_character_string) {
                   $this->next_step = 'build_key_append';
                    $this->toggleQuote(false);
               }
               else {                   
                    $default = true;
               }
               // $debugname = 'default'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
               // die('xx' . $quote);
            }
            else {
                $default = true;
            }
        }
        else {
            $default = true;
        }
        if ($default) {
            $this->setCurrentCharacterAs('key');
        }
        // Last 
        if ($this->is_last) {
            if ($this->is_ongoing_wrapped_by_quote) {
                $this->error('Malformat.');
            }
            else {
                
                $this->setCurrentCharacterAs('key');
            }
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildKeyAppend()
    {
        $this->debug(__METHOD__, '__METHOD__');
        if ($this->is_separator) {
            // Rule1: Karakter sesudah separator harus whitespace.
            if (!ctype_space($this->next_character_string)) {
                throw new RuntimeException('Rule1: Karakter sesudah separator harus whitespace. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            }
            $this->setCurrentCharacterAs('separator');
            $this->next_step = 'build_value_prepend';
        }
        elseif ($this->is_space) {
            $this->setCurrentCharacterAs('key_append');
        }
        else {
            $this->error('Unexpected characters.');
        }
        if ($this->is_last) {
            die('kjsahdkfasljflas');
            
            if ($this->is_ongoing_wrapped_by_quote) {
                $this->error('Malformat.');
            }
            else {
                $this->setCurrentCharacterAs('key');
            }
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildValuePrepend()
    {
        $this->debug(__METHOD__, '__METHOD__');
        if ($this->is_space) {
            $this->setCurrentCharacterAs('value_prepend');
        }
        elseif ($this->is_quote) {
            $this->setCurrentCharacterAs('quote_value');
            $this->next_step = 'build_value';
            $this->toggleQuote(true);
        }
        elseif ($this->is_commentsign) {
            $this->setCurrentCharacterAs('comment');
            $this->next_step = 'build_comment';
        }
        elseif ($this->is_separator) {
            $this->error('Unexpected colon inside value.');
        }
        elseif ($this->is_break) {
            $this->setCurrentCharacterAs('eol');
            $this->next_step = 'init';
        }
        else {
            $this->setCurrentCharacterAs('value');
            $this->next_step = 'build_value';
        }        
    }

    /**
     *
     */
    protected function analyzeStepBuildValue()
    {
        $this->debug(__METHOD__, '__METHOD__');
        $default = false;
        if ($this->is_alphanumeric) {
            $default = true;
        }
        elseif ($this->is_break) {
            if ($this->is_ongoing_wrapped_by_quote) {
                $default = true;
            }
            else {
                $this->cleaningTrailingWhiteSpace('value');
                $this->setCurrentCharacterAs('eol');
                $this->next_step = 'init';
            }
        }
        elseif ($this->is_commentsign) {
            if ($this->is_ongoing_wrapped_by_quote) {
                $default = true;
            }
            else {
                $this->setCurrentCharacterAs('comment');
                $this->next_step = 'build_comment';
            }
        }
        elseif ($this->is_quote && $this->is_ongoing_wrapped_by_quote) {
            $quote = $this->quote_is_single ? "'" : '"';
            $this->debug($quote, '$quote', 1);
            if ($quote === $this->current_character_string) {
                $this->next_step = 'build_value_append';
                $this->toggleQuote(false);
            }
            else {
                $default = true;
            }
        }
        elseif ($this->is_separator) {
            if ($this->is_ongoing_wrapped_by_quote) {
                $default = true;
            }
            else {
                // Pada kasus seperti ini:
                // ```
                // - aa
                // - bb: cc:dd:
                // ```
                $key = $this->getCharacter('key');
                if (null === $key) {
                    $this->correctingCharactersMovingDimension();
                }
                else {
                    if (ctype_space($this->next_character_string)) {
                        $this->error('Unexpected colon inside value.');
                    }
                    else {
                        $default = true;
                    }
                }
            }
        }
        else {
            $default = true;
        }
        if ($default) {
            $this->setCurrentCharacterAs('value');
        }
        // Contoh, ada tiga spasi di akhir:
        // ```
        // aku: sayang[\r][\n]
        // kamu: cinta[space][space][space]
        // ```
        if ($this->is_last) {
            $this->cleaningTrailingWhiteSpace('value');
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildValueAppend()
    {
        $this->debug(__METHOD__, '__METHOD__');
        if ($this->is_break) {
            $this->setCurrentCharacterAs('eol');
            $this->next_step = 'init';
        }
        elseif ($this->is_space) {
            $this->setCurrentCharacterAs('value_append');
        }
        elseif ($this->is_separator) {
            // Contoh kasus:
            // ```
            // - "tempe"  :
            // ```
            // Ini berarti prediksi awal sebagai value, maka
            // perlu di jadikan sebagai key pada kedalaman
            // dimensi berikutnya.
            $this->correctingCharactersMovingDimension();
        }
        elseif ($this->is_commentsign) {
            $this->setCurrentCharacterAs('comment');
            $this->next_step = 'build_comment';
        }
        else {
            $this->error('Unexpected characters.');
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildComment()
    {
        $this->debug(__METHOD__, '__METHOD__');
        $default = false;
        if ($this->is_break) {
            $this->setCurrentCharacterAs('eol');
            $this->next_step = 'init';
        }
        else {
            $default = true;
        }
        if ($default) {
            $this->setCurrentCharacterAs('comment');
        }
    }

    /**
     *
     */
    protected function setCurrentArrayAs($type)
    {
        $this->debug(__METHOD__, '__METHOD__');
        switch ($type) {
            case 'indexed':
                $this->current_array_dimension_is_indexed = true;
                $this->current_array_dimension_is_associative = false;
                break;
            case 'associative':
                $this->current_array_dimension_is_indexed = false;
                $this->current_array_dimension_is_associative = true;
                break;
        }
        $this->lines[$this->current_line][$this->current_array_dimension]['array_type'] = $type;
    }

    /**
     *
     */
    public function toggleQuote($bool)
    {
        switch ($bool) {
            case true:
                $this->is_ongoing_wrapped_by_quote = true;
                switch ($this->current_character_string) {
                    case '"':
                        $this->quote_is_double = true;
                        $this->quote_is_single = false;
                        break;

                    case "'":
                        $this->quote_is_double = false;
                        $this->quote_is_single = true;
                        break;
                }
                break;

            case false:
                $this->is_ongoing_wrapped_by_quote = false;
                $this->quote_is_double = false;
                $this->quote_is_single = false;
                break;
        }
    }


    /**
     *
     */
    protected function verifyIndent($about, $string)
    {
        $this->debug(__METHOD__, '__METHOD__');
        switch ($about) {
            case 'tab':
                if (strpos($string, "\t") !== false) {
                    throw new RuntimeException('Rule3: Indent tidak boleh mengandung tab. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
                }
                break;
            case 'dimension':
                if ($this->current_array_dimension === 0) {
                    throw new RuntimeException('Rule4: Tidak boleh leading space. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
                }
                break;
        }
    }

    /**
     *
     */
    protected function cleaningTrailingWhiteSpace($type)
    {
        $this->debug(__METHOD__, '__METHOD__');        
        $quote = $this->getCharacter('quote_' . $type);
        if (!empty($quote)) {
            return;
        }
        $current = $this->getCharacter($type);
        $test = rtrim($current);
        if ($current !== $test) {
            $this->lines[$this->current_line][$this->current_array_dimension]['segmen'][$type . '_append'] = substr($current, strlen($test));
            $this->lines[$this->current_line][$this->current_array_dimension]['segmen'][$type] = $test;
        }
    }

    /**
     *
     */
    protected function correctingCharactersMovingDimension()
    {
        $this->debug(__METHOD__, '__METHOD__');
        // Get.
        $quote_value = $this->getCharacter('quote_value');
        $value = $this->getCharacter('value');
        $value_append = $this->getCharacter('value_append');
        // Del.
        $this->delCharacter('quote_value');
        $this->delCharacter('value');
        $this->delCharacter('value_append');
        $value_prepend = $this->getCharacter('value_prepend');
        $this->nextDimension('associative', $value_prepend);
        // Set.
        if (strlen($quote_value) > 0) {
            $this->setCharacter('quote_key', $quote_value);
        }
        $this->setCharacter('key', $value);
        if (strlen($value_append) > 0) {
            $this->setCharacter('key_append', $value_append);
        }
        $this->cleaningTrailingWhiteSpace('key');
        $this->setCurrentCharacterAs('separator');
        $this->next_step = 'build_value_prepend';
    }

    /**
     *
     */
    protected function prevDimension($type, $indent_string)
    {
        $this->debug(__METHOD__, '__METHOD__');
        $current_array_dimension = $this->current_array_dimension;
        $found = false;
        while (--$current_array_dimension) {
            $same_type = $this->indents_temporary[$current_array_dimension]['array_type'] == $type;
            $same_string = $this->indents_temporary[$current_array_dimension]['string'] == $indent_string;
            if ($same_type && $same_string) {
                $this->current_array_dimension = $current_array_dimension;
                $this->setCurrentArrayAs($type);
                $found = true;
                break;
            }
        }
        if ($found === false) {
            throw new RuntimeException('Gagal menemukan indent yang sesuai dengan yang sebelumnya. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
        }
    }

    /**
     *
     */
    protected function nextDimension($type, $indent_string)
    {
        $this->debug(__METHOD__, '__METHOD__');
        $this->current_array_dimension++;
        $this->setCurrentArrayAs($type);
        $this->indents_temporary[$this->current_array_dimension] = [
            'string' => $indent_string,
            'length' => strlen($indent_string),
            'array_type' => $type,
        ];
        // Hapus indent yang lama.
        $current_array_dimension = $this->current_array_dimension;
        while (isset($this->indents_temporary[++$current_array_dimension])) {
            unset($this->indents_temporary[$current_array_dimension]);
        }
        $this->debug($this->indents_temporary, '$this->indents_temporary', 1);
    }

    /**
     *
     */
    protected function buildData()
    {
        $lines = $this->lines;
        $current_array_dimension = 1;
        $indents = [];
        do {
            $line = key($lines);
            $line_info = $lines[$line];
            do {
                $dimension = key($line_info);
                $array_type = isset($line_info[$dimension]['array_type']) ? $line_info[$dimension]['array_type'] : null;
                $key = isset($line_info[$dimension]['segmen']['key']) ? $line_info[$dimension]['segmen']['key']: null;
                $value = isset($line_info[$dimension]['segmen']['value']) ? $line_info[$dimension]['segmen']['value']: null;
                
                switch ($array_type) {
                    case 'associative':
                        $key = $this->getKey($key, $dimension);
                        $this->keys[$key] = [
                            'line' => $line,
                            'dimension' => $dimension,
                            'value' => $value,
                            'array_type' => $array_type,
                        ];
                        break;
                    case 'indexed':
                        $key = $this->getSequence($dimension);
                        $this->keys[$key] = [
                            'line' => $line,
                            'dimension' => $dimension,
                            'value' => $value,
                            'array_type' => $array_type,
                        ];
                        break;
                }
                if (isset($value)) {
                    if (isset($this->lines[$line][$dimension]['segmen']['quote_value'])) {
                        $quote = $this->lines[$line][$dimension]['segmen']['quote_value'];
                        if ($quote == '"') {
                            $value = $this->unEscapeString($value);
                        }
                    }
                    else {
                        $value = $this->convertStringValue($value);
                    }
                    if (isset($this->lines[$line][$dimension]['segmen']['quote_key'])) {
                        $quote = $this->lines[$line][$dimension]['segmen']['quote_key'];
                        if ($quote == '"') {
                            $key = $this->unEscapeString($key);
                        }
                    }
                    $data_expand = ArrayDimensional::expand([$key => $value]);
                    $this->data = array_replace_recursive($this->data, $data_expand);
                }
            }
            while (next($line_info));
        }
        while (next($lines));
    }

    /**
     *
     */
    protected function getKey($key, $dimension)
    {
        if ($dimension === 1) {
            $this->keys_temporary[$dimension] = $key;
            return $key;
        }
        $temporary = $this->keys_temporary;
        $_key = '';
        $_dimension = $dimension;
        while (--$_dimension) {
            if (isset($this->keys_temporary[$_dimension])) {
                $_key = $this->keys_temporary[$_dimension] . '[' . $key . ']';
                break;
            }
        }
        $this->keys_temporary[$dimension] = $_key;
        // Hapus yang lama.
        $_dimension = $dimension;
        while (isset($this->keys_temporary[++$_dimension])) {
            unset($this->keys_temporary[$_dimension]);
        }
        return $_key;
    }

    /**
     *
     */
    protected function getSequence($dimension)
    {
        if ($dimension === 1) {
            $key = '[]';
        }
        else {
            $_dimension = $dimension;
            while (--$_dimension) {
                if (isset($this->keys_temporary[$_dimension])) {
                    $key = $this->keys_temporary[$_dimension] . '[]';
                    break;
                }
            }
        }
        $count = array_count_values($this->sequence_of_scalar);
        $c = isset($count[$key]) ? $count[$key] : 0;
        if ($dimension === 1) {
            $_key = $c;
        }
        else {
            $_key = $this->keys_temporary[$_dimension] . '[' . $c . ']';
        }
        $this->sequence_of_scalar[] = $key;
        $this->keys_temporary[$dimension] = $_key;
        // Hapus yang lama.
        $_dimension = $dimension;
        while (isset($this->keys_temporary[++$_dimension])) {
            unset($this->keys_temporary[$_dimension]);
        }
        return $_key;
    }

    /**
     *
     */
    protected function convertStringValue($string)
    {
        switch ($string) {
            case 'NULL':
            case 'null':
                return null;
            case 'TRUE':
            case 'true':
                return true;
            case 'FALSE':
            case 'false':
                return false;
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                return (int) $string;
        }
        if (is_numeric($string)) {
            return (int) $string;
        }
        return $string;
    }

    /**
     *
     */
    protected function unEscapeString($value)
    {
        $find = '\\';
        $x = strpos($value, $find);
        while ($x !== false) {
            $y = $value[$x + 1];
            $y = $this->unEscapeChar($y);
            $before = substr($value, 0, $x);
            $after = substr($value, $x + 2);
            $value = $before . $y . $after;
            $x = strpos($value, $find);
        }
        return $value;
    }

    /**
     *
     */
    protected function unEscapeChar($value)
    {
        switch ($value) {
            case '"':
                return '"';

            default:
                throw new RuntimeException('Found unknown escape character "' . $value . '".');
        }
        return $value;
    }

    /**
     *
     */
    public function error($msg)
    {
        switch ($msg) {
            case 'Unexpected characters.':
                throw new RuntimeException('Unexpected characters "' . $this->current_character_string . '". Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Unexpected colon inside value.':
                throw new RuntimeException('A colon cannot be used in an unquoted mapping value. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Malformat.':
                throw new RuntimeException('Malformed inline YAML string. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');


            default:
                throw new RuntimeException($msg);
                
        }
    }

}
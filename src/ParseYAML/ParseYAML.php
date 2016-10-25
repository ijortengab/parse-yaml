<?php

namespace IjorTengab\ParseYAML;

use IjorTengab\Tools\Abstracts\AbstractAnalyzeCharacter;
use IjorTengab\Tools\Functions\CamelCase;
use IjorTengab\Tools\Functions\ArrayDimensional;

/**
 *
 */
class ParseYAML extends AbstractAnalyzeCharacter
{
    /**
     * Informasi step saat ini yang digunakan untuk menganalisis karakter saat
     * ini. Property ini akan menjadi acuan untuk method yang digunakan.
     * Contoh jika nilai property ini adalah 'init', maka method yang digunakan
     * adalah 'analyzeStepInit()', jika nilai property ini adalah 'build_value',
     * maka method yang digunakan adalah 'analyzeStepBuildValue()'.
     */
    protected $current_step = 'init';

    /**
     * Informasi untuk step berikutnya. Nilai ini diubah oleh method-method
     * dengan prefix 'analyzeStep'. Nilai dari property ini akan mengisi
     * property $current_step ketika akan menganalisis karakter berikutnya.
     */
    protected $next_step;

    /**
     * Informasi bahwa property parent::$raw telah dilakukan parsing.
     */
    protected $has_parsed = false;

    /**
     * Hasil dari parsing.
     */
    protected $data;

    /**
     * Menyimpan segmentasi informasi yang ada pada property parent::$raw.
     */
    protected $segmen = [];

    /**
     * Mapping antara key data dengan line pada file.
     * Array sederhana satu dimensi, dimana key merupakan array simplify
     * dan value merupakan baris pada file.
     * Contoh:
     * [
     *   "key[child][0]" => 1,
     *   "key[child][1]" => 2,
     *   "key[child][2]" => 3,
     *   "other-key" => 4,
     * ]
     */
    protected $keys = [];

    /**
     * Penampungan key yang bersifat sequence, untuk nantinya dihitung
     * index autoincrement saat dilakukan parsing.
     * Contoh:
     * [
     *   "key[child][]",
     *   "key[child][]",
     *   "key[child][]",
     * ]
     * Pada array diatas, maka key[child][2] adalah index trtinggi.
     */
    protected $sequence_of_scalar = [];

    /**
     * Kondisi dimensi saat ini.
     */
    protected $current_array_dimension = 0;
    protected $current_array_dimension_is_indexed = false;
    protected $current_array_dimension_is_associative = false;
    protected $current_line_populate_segmen = 1;

    /**
     * Kondisi character saat ini.
     */
    protected $is_alphanumeric = false;
    protected $is_space = false; // All whitespace except \r \n.
    protected $is_quote = false;
    protected $is_quote_single = false;
    protected $is_quote_double = false;
    protected $is_separator = false;
    protected $is_commentsign = false;

    /**
     * Property Sementara.
     */
    protected $keys_temporary = [];
    protected $indents_temporary = [];
    protected $is_ongoing_wrapped_by_quote = false;
    protected $quote_wrapper;

    /**
     * Melakukan parsing.
     */
    public function parse()
    {
        if (false === $this->has_parsed) {
            $this->has_parsed = true;
            return $this->looping();
        }
    }

    /**
     * Mengembalikan nilai dari property $data.
     */
    public function getResult()
    {
        return $this->data;
    }

    /**
     *
     */
    protected function beforeLooping()
    {
    }

    /**
     *
     */
    protected function afterLooping()
    {
        $this->buildData();
    }

    /**
     *
     */
    protected function beforeAnalyze()
    {
    }

    /**
     *
     */
    protected function afterAnalyze()
    {
        if ($this->is_last) {
            switch ($this->current_step) {
                case 'build_key':
                    if ($this->is_ongoing_wrapped_by_quote) {
                        $this->error('Malformat.');
                    }
                    else {
                        $this->cleaningTrailingWhiteSpace('key');
                    }
                    break;

                case 'build_value':
                    // Contoh, ada tiga spasi di akhir:
                    // ```
                    // aku: sayang[\r][\n]
                    // kamu: cinta[space][space][space]
                    // ```
                    if ($this->is_ongoing_wrapped_by_quote) {
                        $this->error('Malformat.');
                    }
                    else {
                        $this->cleaningTrailingWhiteSpace('value');
                    }
                    break;
            }
        }
    }

    /**
     * Implements abstact analyzeCurrentLine().
     */
    protected function analyzeCurrentLine()
    {
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
            return $this->analyzeLineComment($match);
        }
        // Jika line merupakan sequence of scalar (array indexed).
        if (preg_match('/^(?<leading_space>[\s]*)(?<sequence_sign>-[\s])(?<other>.*)/', $current_line_string, $match)) {
            return $this->analyzeLineSequenceOfScalar($match);
        }
        // Jika line merupakan mapping of scalar (array associative).
        if (preg_match('/^(?<leading_space>[\s]*)(?<key>[^\s].*):/', $current_line_string, $match)) {
            return $this->analyzeLineMappingOfScalar($match);
        }
        // Untuk kasus
        // ```yml
        // # Bla-bla
        // cinta
        // ```
        // maka solusinya adalah:
        if ($this->current_array_dimension === 0) {
            return;
        }
        // Gak ada yang cocok? Lempar.
        return $this->error('Unable to parse the line.');
    }

    /**
     * Menganalisis line yang dikenali sebagai comment.
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
     * Menganalisis line yang dikenali sebagai sequence of scalar
     * (indexed array).
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
                    if (array_key_exists('value', $this->segmen[$prev_line][$dimension]['segmen'])) {
                        return $this->error('Currently is mapping.');
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
     * Menganalisis line yang dikenali sebagai mapping of scalar
     * (associative array).
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
                    return $this->error('Currently is sequence.');
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

        $ch = $this->current_character_string;
        $nch = $this->next_character_string;
        // Mendefinisikan character quote yang di-escape agar tidak bentrok
        // dengan $this->is_quote.
        if ($this->is_ongoing_wrapped_by_quote) {
            if ($ch === '\\') {
                $quote = $this->quote_wrapper;
                // Key seperti contoh dibawah hasilnya failed:
                // ```
                // 'a\'a': bb
                // ```
                if ($quote == "'" && $nch == "'") {
                    return $this->error('Unable to parse the line.');
                }
                $x = $this->current_character;
                $ch = '\\' . $nch;
                $nch = isset($this->raw[$x+2]) ? $this->raw[$x+2] : false;
                $this->current_character_string = $ch;
                $this->next_character_string = $nch;
                $this->current_character++;
            }
        }
    }

    /**
     *
     */
    protected function assignCurrentCharacter()
    {
        parent::assignCurrentCharacter();
        $ch = $this->current_character_string;
        if (ctype_alnum($ch)) {
            $this->is_alphanumeric = true;
        }
        elseif (ctype_space($ch) && !in_array($ch, ["\r", "\n", "\r\n"])) {
            $this->is_space = true;
        }
        elseif ($ch === '"') {
            $this->is_quote = true;
            $this->is_quote_double = true;
        }
        elseif ($ch === "'") {
            $this->is_quote = true;
            $this->is_quote_single = true;
        }
        elseif ($ch === ':') {
            $this->is_separator = true;
        }
        elseif ($ch === '#') {
            $this->is_commentsign = true;
        }
    }

    /**
     * Implements of abstract analyzeCurrentCharacter().
     */
    protected function analyzeCurrentCharacter()
    {
        $this->runStep();
    }

    /**
     *
     */
    protected function prepareNextLoop()
    {
        parent::prepareNextLoop();
        if ($this->next_step !== null) {
            $this->current_step = $this->next_step;
            $this->next_step = null;
        }

        if ($this->is_break && $this->current_step != 'build_value') {
            $this->current_line_populate_segmen = $this->current_line;
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
        $this->is_quote_single = false;
        $this->is_quote_double = false;
        $this->is_separator = false;
        $this->is_commentsign = false;
    }

    /**
     * Build method.
     */
    protected function runStep()
    {
        static $cache;
        if (isset($cache[$this->current_step])) {
            return $this->{$cache[$this->current_step]}();
        }
        $_method = 'analyze_step_' . $this->current_step;
        $method = CamelCase::convertFromUnderScore($_method);
        $cache[$this->current_step] = $method;
        $this->{$method}();
    }

    /**
     *
     */
    protected function setCharacter($key, $value)
    {
        if (!isset($this->segmen[$this->current_line_populate_segmen][$this->current_array_dimension]['segmen'][$key])) {
            $this->segmen[$this->current_line_populate_segmen][$this->current_array_dimension]['segmen'][$key] = '';
        }
        $this->segmen[$this->current_line_populate_segmen][$this->current_array_dimension]['segmen'][$key] .= $value;
    }

    /**
     *
     */
    protected function setCurrentCharacterAs($key)
    {
        return $this->setCharacter($key, $this->current_character_string);
    }

    /**
     *
     */
    protected function getCharacter($key)
    {
        if (isset($this->segmen[$this->current_line_populate_segmen][$this->current_array_dimension]['segmen'][$key])) {
            return $this->segmen[$this->current_line_populate_segmen][$this->current_array_dimension]['segmen'][$key];
        }
    }

    /**
     *
     */
    protected function delCharacter($key)
    {
        unset($this->segmen[$this->current_line_populate_segmen][$this->current_array_dimension]['segmen'][$key]);
    }

    /**
     *
     */
    protected function analyzeStepInit()
    {
        if ($this->is_alphanumeric) {
            $this->setCurrentCharacterAs('value');
            $this->next_step = 'build_value';
        }
        elseif ($this->is_quote) {
            $this->setCurrentCharacterAs('quote_value');
            $this->next_step = 'build_value';
            $this->toggleQuote();
        }
        elseif ($this->is_break) {
            $this->setCurrentCharacterAs('eol');
        }
        elseif ($this->is_space) {
            $this->error('Cannot leading space.');
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildKeyPrepend()
    {
        if ($this->is_alphanumeric) {
            $this->setCurrentCharacterAs('key');
            $this->next_step = 'build_key';
        }
        elseif ($this->is_quote) {
            $this->setCurrentCharacterAs('quote_key');
            $this->next_step = 'build_key';
            $this->toggleQuote();
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
                    return $this->error('Unexpected colon inside key.');
                }
                $this->cleaningTrailingWhiteSpace('key');
                $this->setCurrentCharacterAs('separator');
                $this->next_step = 'build_value_prepend';
            }
        }
        elseif ($this->is_quote) {
            $default = true;
            if ($this->is_ongoing_wrapped_by_quote) {
               $quote = $this->quote_wrapper;
               if ($quote === $this->current_character_string) {
                   $this->next_step = 'build_key_append';
                    $this->toggleQuote();
                    $default = false;
               }
            }
        }
        else {
            $default = true;
        }
        if ($default) {
            $this->setCurrentCharacterAs('key');
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildKeyAppend()
    {
        if ($this->is_separator) {
            // Rule1: Karakter sesudah separator harus whitespace.
            if (!ctype_space($this->next_character_string)) {
                return $this->error('Malformat colon.');
            }
            $this->setCurrentCharacterAs('separator');
            $this->next_step = 'build_value_prepend';
        }
        elseif ($this->is_space) {
            $this->setCurrentCharacterAs('key_append');
        }
        else {
            return $this->error('Unexpected characters.');
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildValuePrepend()
    {
        if ($this->is_space) {
            $this->setCurrentCharacterAs('value_prepend');
        }
        elseif ($this->is_quote) {
            $this->setCurrentCharacterAs('quote_value');
            $this->next_step = 'build_value';
            $this->toggleQuote();
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
                $this->cleaningTrailingWhiteSpace('value');
                $this->setCurrentCharacterAs('comment');
                $this->next_step = 'build_comment';
            }
        }
        elseif ($this->is_quote && $this->is_ongoing_wrapped_by_quote) {
            $quote = $this->quote_wrapper;
            if ($quote === $this->current_character_string) {
                // Pada kasus sepert ini:
                // ```
                // description: ''
                // ```
                // Maka solusinya adalah tambah value kosong.
                $this->setCharacter('value', '');
                $this->next_step = 'build_value_append';
                $this->toggleQuote();
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
    }

    /**
     *
     */
    protected function analyzeStepBuildValueAppend()
    {
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
        $this->segmen[$this->current_line][$this->current_array_dimension]['array_type'] = $type;
    }

    /**
     *
     */
    protected function toggleQuote()
    {
        $current = $this->is_ongoing_wrapped_by_quote;
        $toggle = !$current;
        switch ($toggle) {
            case true:
                $this->is_ongoing_wrapped_by_quote = true;
                $this->quote_wrapper = $this->current_character_string;
                break;

            case false:
                $this->is_ongoing_wrapped_by_quote = false;
                $this->quote_wrapper = null;
                break;
        }
    }

    /**
     *
     */
    protected function verifyIndent($about, $string)
    {
        switch ($about) {
            case 'tab':
                if (strpos($string, "\t") !== false) {
                    $this->error('Indent contains tab.');
                }
                break;
            case 'dimension':
                if ($this->current_array_dimension === 0) {
                    $this->error('Cannot leading space.');
                }
                break;
        }
    }

    /**
     *
     */
    protected function cleaningTrailingWhiteSpace($type)
    {
        $quote = $this->getCharacter('quote_' . $type);
        if (!empty($quote)) {
            return;
        }
        $current = $this->getCharacter($type);
        $test = rtrim($current);
        if ($current !== $test) {
            $this->segmen[$this->current_line][$this->current_array_dimension]['segmen'][$type . '_append'] = substr($current, strlen($test));
            $this->segmen[$this->current_line][$this->current_array_dimension]['segmen'][$type] = $test;
        }
    }

    /**
     *
     */
    protected function correctingCharactersMovingDimension()
    {
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
            return $this->error('Previous Indent not found.');
        }
    }

    /**
     *
     */
    protected function nextDimension($type, $indent_string)
    {
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
    }

    /**
     *
     */
    protected function buildData()
    {
        $segmen = $this->segmen;
        do {
            $line = key($segmen);
            $line_info = $segmen[$line];
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
                    if (isset($this->segmen[$line][$dimension]['segmen']['quote_value'])) {
                        $quote = $this->segmen[$line][$dimension]['segmen']['quote_value'];
                        if ($quote == '"') {
                            $value = $this->unescapeString($value);
                        }
                    }
                    else {
                        $value = $this->convertStringValue($value);
                    }
                    if (isset($this->segmen[$line][$dimension]['segmen']['quote_key'])) {
                        $quote = $this->segmen[$line][$dimension]['segmen']['quote_key'];
                        if ($quote == '"') {
                            $key = $this->unescapeString($key);
                        }
                    }
                    // Untuk kasus
                    // ```yml
                    // # Bla-bla
                    // cinta
                    // ```
                    // maka variable $key akan bernilai null.
                    if (null === $key) {
                        $this->data = $value;
                    }
                    else {
                        $data_expand = ArrayDimensional::expand([$key => $value]);
                        $this->data = array_replace_recursive((array) $this->data, $data_expand);
                    }
                }
            }
            while (next($line_info));
        }
        while (next($segmen));
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
    protected function unescapeString($value)
    {
        $find = '\\';
        $x = strpos($value, $find);
        while ($x !== false) {
            $y = $value[$x + 1];
            $y = $this->unescapeCharacter($y);
            $before = substr($value, 0, $x);
            $after = substr($value, $x + 2);
            $value = $before . $y . $after;
            $x = strpos($value, $find);
        }
        return $value;
    }

    /**
     * Sumber symfony/yaml Unescaper.php
     */
    protected function unescapeCharacter($value)
    {
        switch ($value) {
            case '0':
                return "\x0";
            case 'a':
                return "\x7";
            case 'b':
                return "\x8";
            case 't':
                return "\t";
            case "\t":
                return "\t";
            case 'n':
                return "\n";
            case 'v':
                return "\xB";
            case 'f':
                return "\xC";
            case 'r':
                return "\r";
            case 'e':
                return "\x1B";
            case ' ':
                return ' ';
            case '"':
                return '"';
            case '/':
                return '/';
            case '\\':
                return '\\';
            case 'N':
                // U+0085 NEXT LINE
                return "\xC2\x85";
            case '_':
                // U+00A0 NO-BREAK SPACE
                return "\xC2\xA0";
            case 'L':
                // U+2028 LINE SEPARATOR
                return "\xE2\x80\xA8";
            case 'P':
                // U+2029 PARAGRAPH SEPARATOR
                return "\xE2\x80\xA9";
                // Todo, resolve below.
            // case 'x':
                // return self::utf8chr(hexdec(substr($value, 2, 2)));
            // case 'u':
                // return self::utf8chr(hexdec(substr($value, 2, 4)));
            // case 'U':
                // return self::utf8chr(hexdec(substr($value, 2, 8)));
            default:
                return $this->error('Found unknown escape character: "' . $value . '".');
        }
    }

    /**
     *
     */
    protected function error($msg)
    {
        switch ($msg) {
            case 'Unexpected characters.':
                throw new RuntimeException('Unexpected characters "' . $this->current_character_string . '". Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Unexpected colon inside value.':
                throw new RuntimeException('A colon cannot be used in an unquoted mapping value. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Malformat.':
                throw new RuntimeException('Malformed inline YAML string. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Unable to parse the line.':
                throw new RuntimeException('Unable to parse the line. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Currently is mapping.':
                throw new RuntimeException('You cannot define a sequence item when in a mapping. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Currently is sequence.':
                throw new RuntimeException('You cannot define a mapping item when in a sequence. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Indent contains tab.':
                throw new RuntimeException('Indent tidak boleh mengandung tab. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Cannot leading space.':
                throw new RuntimeException('Tidak boleh leading space. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Unexpected colon inside key.':
                throw new RuntimeException('A colon cannot be used in an unquoted key. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Malformat colon.':
                throw new RuntimeException('Karakter sesudah colon harus whitespace. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            case 'Previous Indent not found.':
                throw new RuntimeException('Gagal menemukan indent yang sesuai dengan yang sebelumnya. Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            default:
                // 'Found unknown escape character: "' . $value . '".'
                throw new RuntimeException($msg);

        }
    }
//
}
